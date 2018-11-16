<?php
/**
 * Spiral, Core Components
 *
 * @author Wolfy-J
 */

namespace Spiral\ORM\Entities\Relations;

use Spiral\Database\Builders\SelectQuery;
use Spiral\Database\Entities\Table;
use Spiral\ORM\CommandInterface;
use Spiral\ORM\Commands\ContextualDeleteCommand;
use Spiral\ORM\Commands\InsertCommand;
use Spiral\ORM\Commands\TransactionalCommand;
use Spiral\ORM\Commands\UpdateCommand;
use Spiral\ORM\ContextualCommandInterface;
use Spiral\ORM\Entities\Loaders\ManyToManyLoader;
use Spiral\ORM\Entities\Loaders\RelationLoader;
use Spiral\ORM\Entities\Nodes\PivotedRootNode;
use Spiral\ORM\Entities\RecordIterator;
use Spiral\ORM\Entities\Relations\Traits\LookupTrait;
use Spiral\ORM\Exceptions\RelationException;
use Spiral\ORM\Helpers\AliasDecorator;
use Spiral\ORM\ORMInterface;
use Spiral\ORM\Record;
use Spiral\ORM\RecordInterface;

/**
 * Provides ability to create pivot map between parent record and multiple objects with ability to
 * link them, link and create, update pivot, unlink or sync send. Relation support partial mode.
 */
class ManyToManyRelation extends MultipleRelation implements \IteratorAggregate, \Countable
{
    use LookupTrait;

    /**
     * @var Table|null
     */
    private $pivotTable = null;

    /**
     * @var \SplObjectStorage
     */
    private $pivotData;

    /**
     * Linked but not saved yet records.
     *
     * @var array
     */
    private $scheduled = [];

    /**
     * Record which pivot data was updated, record must still present in linked array.
     *
     * @var array
     */
    private $updated = [];

    /**
     * Records scheduled to be de-associated.
     *
     * @var RecordInterface[]
     */
    private $unlinked = [];

    /**
     * When target role is null parent role to be used. Redefine this variable to revert behaviour
     * of ManyToMany relation.
     *
     * @see ManyToMorphedRelation
     * @var string|null
     */
    private $targetRole = null;

    /**
     * @param string                   $class
     * @param array                    $schema
     * @param \Spiral\ORM\ORMInterface $orm
     * @param string|null              $targetRole
     */
    public function __construct($class, array $schema, ORMInterface $orm, string $targetRole = null)
    {
        parent::__construct($class, $schema, $orm);
        $this->pivotData = new \SplObjectStorage();
        $this->targetRole = $targetRole;
    }

    /**
     * {@inheritdoc}
     *
     * Pivot data must be set separatelly.
     */
    public function setRelated($value)
    {
        $this->loadData(true);

        if (is_null($value)) {
            $value = [];
        }

        if (!is_array($value)) {
            throw new RelationException("HasMany relation can only be set with array of entities");
        }

        //Do not add items twice
        $matched = [];
        foreach ($value as $index => $record) {
            if (is_null($record)) {
                unset($value[$index]);
                continue;
            }

            $this->assertValid($record);
            if (!empty($instance = $this->matchOne($record))) {
                $matched[] = $instance;
                unset($value[$index]);
            }
        }

        //Unlink records
        foreach (array_diff($this->instances, $matched) as $record) {
            $this->unlink($record);
        }

        //Add new record
        foreach ($value as $record) {
            $this->link($record);
        }
    }

    /**
     * Get all unlinked records.
     *
     * @return \ArrayIterator
     */
    public function getUnlinked()
    {
        return new \ArrayIterator($this->unlinked);
    }

    /**
     * Get pivot data associated with specific instance.
     *
     * @param RecordInterface $record
     *
     * @return array
     */
    public function getPivot(RecordInterface $record): array
    {
        if (empty($matched = $this->matchOne($record))) {
            return [];
        }

        return $this->pivotData->offsetGet($matched);
    }

    /**
     * Link record with parent entity. Only record instances is accepted.
     *
     * Attention, attached instances MIGHT be de-referenced IF parent object was reselected in a
     * different scope.
     *
     * @param RecordInterface $record
     * @param array           $pivotData
     *
     * @return self
     *
     * @throws RelationException
     */
    public function link(RecordInterface $record, array $pivotData = []): self
    {
        $this->loadData(true);
        $this->assertValid($record);
        $this->assertPivot($pivotData);

        //Ensure reference
        $record = $this->matchOne($record) ?? $record;

        if (in_array($record, $this->instances)) {
            //Merging pivot data
            $this->pivotData->offsetSet($record, $pivotData + $this->getPivot($record));

            if (!in_array($record, $this->updated) && !in_array($record, $this->scheduled)) {
                //Indicating that record pivot data has been changed
                $this->updated[] = $record;
            }

            return $this;
        }

        //New association
        $this->instances[] = $record;
        $this->scheduled[] = $record;
        $this->pivotData->offsetSet($record, $pivotData);

        return $this;
    }

    /**
     * Unlink specific entity from relation. Will load relation data! Record to delete will be
     * automatically matched to a give record.
     *
     * @param RecordInterface $record
     *
     * @return self
     *
     * @throws RelationException When entity not linked.
     */
    public function unlink(RecordInterface $record): self
    {
        $this->loadData(true);

        //Ensure reference
        $record = $this->matchOne($record) ?? $record;

        foreach ($this->instances as $index => $linked) {
            if ($this->match($linked, $record)) {
                //Removing locally
                unset($this->instances[$index]);

                if (!in_array($linked, $this->scheduled, true) || !$this->autoload) {
                    //Scheduling unlink in db when we know relation OR partial mode is on
                    $this->unlinked[] = $linked;
                }
                break;
            }
        }

        $this->instances = array_values($this->instances);

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function queueCommands(ContextualCommandInterface $parentCommand): CommandInterface
    {
        $transaction = new TransactionalCommand();

        foreach ($this->unlinked as $record) {
            //Leading command
            $transaction->addCommand($recordCommand = $record->queueStore(), true);

            //Delete link
            $command = new ContextualDeleteCommand($this->pivotTable(), [
                $this->key(Record::THOUGHT_INNER_KEY) => null,
                $this->key(Record::THOUGHT_OUTER_KEY) => null,
            ]);

            //Make sure command is properly configured with conditions OR create promises
            $command = $this->ensureContext(
                $command,
                $this->parent,
                $parentCommand,
                $record,
                $recordCommand
            );

            $transaction->addCommand($command);
        }

        foreach ($this->instances as $record) {
            //Leading command
            $transaction->addCommand($recordCommand = $record->queueStore(), true);

            //Create or refresh link between records
            if (in_array($record, $this->scheduled)) {
                //Create link
                $command = new InsertCommand(
                    $this->pivotTable(),
                    $this->pivotData->offsetGet($record)
                );
            } elseif (in_array($record, $this->updated)) {
                //Update link (expecting both records to be already loaded)
                $command = new UpdateCommand(
                    $this->pivotTable(),
                    [
                        $this->key(Record::THOUGHT_INNER_KEY) => $this->lookupKey(
                            Record::INNER_KEY,
                            $this->parent
                        ),
                        $this->key(Record::THOUGHT_OUTER_KEY) => $this->lookupKey(
                            Record::OUTER_KEY,
                            $record
                        ),
                    ],
                    $this->pivotData->offsetGet($record)
                );
            } else {
                //Nothing to do
                continue;
            }

            //Syncing pivot data values
            $command->onComplete(function (ContextualCommandInterface $command) use ($record) {
                //Now when we are done we can sync our values with current data
                $this->pivotData->offsetSet(
                    $record,
                    $command->getContext() + $this->getPivot($record)
                );
            });

            //Make sure command is properly configured with conditions OR create promises
            $command = $this->ensureContext(
                $command,
                $this->parent,
                $parentCommand,
                $record,
                $recordCommand
            );

            $transaction->addCommand($command);
        }

        $this->scheduled = [];
        $this->unlinked = [];
        $this->updated = [];

        return $transaction;
    }

    /**
     * Insane method used to properly set pivot command context (where or insert statement) based on
     * parent and outer records AND/OR based on command promises.
     *
     * @param ContextualCommandInterface $pivotCommand
     * @param RecordInterface            $parent
     * @param ContextualCommandInterface $parentCommand
     * @param RecordInterface            $outer
     * @param ContextualCommandInterface $outerCommand
     *
     * @return ContextualCommandInterface
     */
    protected function ensureContext(
        ContextualCommandInterface $pivotCommand,
        RecordInterface $parent,
        ContextualCommandInterface $parentCommand,
        RecordInterface $outer,
        ContextualCommandInterface $outerCommand
    ) {
        //Parent record dependency
        $parentCommand->onExecute(function ($parentCommand) use ($pivotCommand, $parent) {
            $pivotCommand->addContext(
                $this->key(Record::THOUGHT_INNER_KEY),
                $this->lookupKey(Record::INNER_KEY, $parent, $parentCommand)
            );
        });

        //Outer record dependency
        $outerCommand->onExecute(function ($outerCommand) use ($pivotCommand, $outer) {
            $pivotCommand->addContext(
                $this->key(Record::THOUGHT_OUTER_KEY),
                $this->lookupKey(Record::OUTER_KEY, $outer, $outerCommand)
            );
        });

        if (!empty($this->key(Record::MORPH_KEY))) {
            $pivotCommand->addContext($this->key(Record::MORPH_KEY), $this->targetRole());
        }

        return $pivotCommand;
    }

    /**
     * Fetch data from database. Lazy load. Method require a bit of love.
     *
     * @return array
     */
    protected function loadRelated(): array
    {
        $innerKey = $this->parent->getField($this->key(Record::INNER_KEY));
        if (empty($innerKey)) {
            return [];
        }

        //Work with pre-build query
        $query = $this->createQuery($innerKey);

        //Use custom node to parse data
        $node = new PivotedRootNode(
            $this->schema[Record::RELATION_COLUMNS],
            $this->schema[Record::PIVOT_COLUMNS],
            $this->schema[Record::OUTER_KEY],
            $this->schema[Record::THOUGHT_INNER_KEY],
            $this->schema[Record::THOUGHT_OUTER_KEY]
        );

        $iterator = $query->getIterator();
        foreach ($iterator as $row) {
            //Time to parse some data
            $node->parseRow(0, $row);
        }

        //Memory free
        $iterator->close();

        return $node->getResult();
    }

    /**
     * Create query for lazy loading.
     *
     * @param mixed $innerKey
     *
     * @return SelectQuery
     */
    protected function createQuery($innerKey): SelectQuery
    {
        $table = $this->orm->table($this->class);
        $query = $table->select();

        //Loader will take care of query configuration
        $loader = new ManyToManyLoader(
            $this->class,
            $table->getName(),
            $this->schema,
            $this->orm,
            $this->targetRole()
        );

        //This is root loader, we can do self-alias (THIS IS SAFE due loader in POSTLOAD mode)
        $loader = $loader->withContext(
            $loader,
            [
                'alias'      => $table->getName(),
                'pivotAlias' => $table->getName() . '_pivot',
                'method'     => RelationLoader::POSTLOAD
            ]
        );

        //Configuring query using parent inner key value as reference
        /** @var ManyToManyLoader $loader */
        $query = $loader->configureQuery($query, true, [$innerKey]);

        //Additional pivot conditions
        $pivotDecorator = new AliasDecorator($query, 'onWhere', $table->getName() . '_pivot');
        $pivotDecorator->where($this->schema[Record::WHERE_PIVOT]);

        $decorator = new AliasDecorator($query, 'where', 'root');

        //Additional where conditions!
        if (!empty($this->schema[Record::WHERE])) {
            $decorator->where($this->schema[Record::WHERE]);
        }

        if (!empty($this->schema[Record::ORDER_BY])) {
            //Sorting
            $decorator->orderBy((array)$this->schema[Record::ORDER_BY]);
        }

        return $query;
    }

    /**
     * Init relations and populate pivot map.
     *
     * @return self|MultipleRelation
     */
    protected function initInstances(): MultipleRelation
    {
        if (is_array($this->data) && !empty($this->data)) {
            //Iterates and instantiate records
            $iterator = new RecordIterator($this->data, $this->class, $this->orm);

            foreach ($iterator as $pivotData => $item) {
                if (in_array($item, $this->instances, true)) {
                    //Skip duplicates (if any?)
                    continue;
                }

                $this->pivotData->attach($item, $pivotData);
                $this->instances[] = $item;
            }
        }

        //Memory free
        $this->data = [];

        return $this;
    }

    /**
     * @return Table
     */
    private function pivotTable()
    {
        if (empty($this->pivotTable)) {
            $this->pivotTable = $this->orm->database(
                $this->schema[Record::PIVOT_DATABASE]
            )->table(
                $this->schema[Record::PIVOT_TABLE]
            );
        }

        return $this->pivotTable;
    }

    /**
     * Make sure that pivot data in a valid format.
     *
     * @param array $pivotData
     *
     * @throws RelationException
     */
    private function assertPivot(array $pivotData)
    {
        if ($diff = array_diff(array_keys($pivotData), $this->schema[Record::PIVOT_COLUMNS])) {
            throw new RelationException(
                "Invalid pivot data, undefined columns found: " . join(', ', $diff)
            );
        }
    }

    /**
     * Defined role to be used in morphed relations.
     *
     * @return string
     */
    private function targetRole(): string
    {
        return $this->targetRole ?? $this->orm->define(
                get_class($this->parent),
                ORMInterface::R_ROLE_NAME
            );
    }
}
