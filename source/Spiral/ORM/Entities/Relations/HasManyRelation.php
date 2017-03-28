<?php
/**
 * Spiral, Core Components
 *
 * @author Wolfy-J
 */

namespace Spiral\ORM\Entities\Relations;

use Spiral\ORM\CommandInterface;
use Spiral\ORM\Commands\NullCommand;
use Spiral\ORM\Commands\TransactionalCommand;
use Spiral\ORM\ContextualCommandInterface;
use Spiral\ORM\Entities\RecordSelector;
use Spiral\ORM\Entities\Relations\Traits\LookupTrait;
use Spiral\ORM\Entities\Relations\Traits\SyncedTrait;
use Spiral\ORM\Exceptions\RelationException;
use Spiral\ORM\Helpers\AliasDecorator;
use Spiral\ORM\ORMInterface;
use Spiral\ORM\Record;
use Spiral\ORM\RecordInterface;

/**
 * Attention, this relation delete operation works inside loaded scope!
 *
 * When empty array assigned to relation it will schedule all related instances to be deleted.
 *
 * If you wish to load with relation WITHOUT loading previous records use [] initialization.
 */
class HasManyRelation extends MultipleRelation implements \IteratorAggregate, \Countable
{
    use LookupTrait, SyncedTrait;

    /**
     * Records deleted from list. Potentially pre-schedule command?
     *
     * @var RecordInterface[]
     */
    private $deleteInstances = [];

    /**
     * {@inheritdoc}
     *
     * @throws RelationException
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
        foreach ($value as $index => $item) {
            if (is_null($item)) {
                unset($value[$index]);
                continue;
            }

            $this->assertValid($item);
            if (!empty($instance = $this->matchOne($item))) {
                $matched[] = $instance;
                unset($value[$index]);
            }
        }

        $this->deleteInstances = array_diff($this->instances, $matched);
        $this->instances = $matched + $value;
    }

    /**
     * Iterate over deleted instances.
     *
     * @return \ArrayIterator
     */
    public function getDeleted()
    {
        return new \ArrayIterator($this->deleteInstances);
    }

    /**
     * Add new record into entity set. Attention, usage of this method WILL load relation data
     * unless partial.
     *
     * @param RecordInterface $record
     *
     * @return self
     *
     * @throws RelationException
     */
    public function add(RecordInterface $record): self
    {
        $this->assertValid($record);
        $this->loadData(true)->instances[] = $record;

        return $this;
    }

    /**
     * Delete one record, strict compaction, make sure exactly same instance is given.
     *
     * @param RecordInterface $record
     *
     * @return self
     *
     * @throws RelationException
     */
    public function delete(RecordInterface $record): self
    {
        $this->loadData(true);
        $this->assertValid($record);

        foreach ($this->instances as $index => $instance) {
            if ($this->match($instance, $record)) {
                //Remove from save
                unset($this->instances[$index]);
                $this->deleteInstances[] = $instance;
                break;
            }
        }

        $this->instances = array_values($this->instances);

        return $this;
    }

    /**
     * Detach given object from set of instances but do not delete it in database, use it to
     * transfer object between sets.
     *
     * @param \Spiral\ORM\RecordInterface $record
     *
     * @return \Spiral\ORM\RecordInterface
     *
     * @throws RelationException When object not presented in a set.
     */
    public function detach(RecordInterface $record): RecordInterface
    {
        $this->loadData(true);
        foreach ($this->instances as $index => $instance) {
            if ($this->match($instance, $record)) {
                //Remove from save
                unset($this->instances[$index]);

                return $instance;
            }
        }

        throw new RelationException("Record {$record} not found in HasMany relation");
    }

    /**
     * {@inheritdoc}
     */
    public function queueCommands(ContextualCommandInterface $parentCommand): CommandInterface
    {
        //No autoloading here

        if (empty($this->instances) && empty($this->deleteInstances)) {
            return new NullCommand();
        }

        $transaction = new TransactionalCommand();

        //Delete old instances first
        foreach ($this->deleteInstances as $deleted) {
            //To de-associate use BELONGS_TO relation
            $transaction->addCommand($deleted->queueDelete());
        }

        //Store all instances
        foreach ($this->instances as $instance) {
            $transaction->addCommand($this->queueRelated($parentCommand, $instance));
        }

        //Flushing instances
        $this->deleteInstances = [];

        return $transaction;
    }

    /**
     * @param ContextualCommandInterface $parentCommand
     * @param RecordInterface            $instance
     *
     * @return CommandInterface
     */
    protected function queueRelated(
        ContextualCommandInterface $parentCommand,
        RecordInterface $instance
    ): CommandInterface {
        //Related entity store command
        $innerCommand = $instance->queueStore(true);

        if (!$this->isSynced($this->parent, $instance)) {
            //Delayed linking
            $parentCommand->onExecute(function ($outerCommand) use ($innerCommand) {
                $innerCommand->addContext(
                    $this->key(Record::OUTER_KEY),
                    $this->lookupKey(Record::INNER_KEY, $this->parent, $outerCommand)
                );

                if (!empty($morphKey = $this->key(Record::MORPH_KEY))) {
                    //HasOne relation support additional morph key
                    $innerCommand->addContext(
                        $this->key(Record::MORPH_KEY),
                        $this->orm->define(get_class($this->parent), ORMInterface::R_ROLE_NAME)
                    );
                }
            });
        }

        return $innerCommand;
    }

    /**
     * Fetch data from database. Lazy load.
     *
     * @return array
     */
    protected function loadRelated(): array
    {
        $innerKey = $this->parent->getField($this->key(Record::INNER_KEY));
        if (!empty($innerKey)) {
            return $this->createSelector($innerKey)->fetchData();
        }

        return [];
    }

    /**
     * Create outer selector for a given inner key value.
     *
     * @param mixed $innerKey
     *
     * @return RecordSelector
     */
    protected function createSelector($innerKey): RecordSelector
    {
        $selector = $this->orm->selector($this->class)->where(
            $this->key(Record::OUTER_KEY),
            $innerKey
        );

        $decorator = new AliasDecorator($selector, 'where', $selector->getAlias());
        if (!empty($this->schema[Record::WHERE])) {
            //Configuring where conditions with alias resolution
            $decorator->where($this->schema[Record::WHERE]);
        }

        if (!empty($this->key(Record::MORPH_KEY))) {
            //Morph key
            $decorator->where(
                '{@}.' . $this->key(Record::MORPH_KEY),
                $this->orm->define(get_class($this->parent), ORMInterface::R_ROLE_NAME)
            );
        }

        if (!empty($this->schema[Record::ORDER_BY])) {
            //Sorting
            $decorator->orderBy((array)$this->schema[Record::ORDER_BY]);
        }

        return $selector;
    }
}