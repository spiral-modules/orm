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
use Spiral\ORM\Entities\Relations\Traits\LookupTrait;
use Spiral\ORM\ORMInterface;
use Spiral\ORM\Record;
use Spiral\ORM\RecordInterface;

class HasOneRelation extends SingularRelation
{
    use LookupTrait;

    /** Automatically create related model when empty. */
    const CREATE_PLACEHOLDER = true;

    /**
     * Previously binded instance, to be deleted.
     *
     * @var RecordInterface
     */
    private $previous = null;

    /**
     * {@inheritdoc}
     */
    public function setRelated($value)
    {
        //Make sure value is accepted
        $this->assertValid($value);

        $this->loaded = true;
        if (empty($this->previous)) {
            //We are only keeping reference to the oldest (ie loaded) instance
            $this->previous = $this->instance;
        }

        $this->instance = $value;
    }

    /**
     * {@inheritdoc}
     */
    public function queueCommands(ContextualCommandInterface $parentCommand): CommandInterface
    {
        if (!empty($this->previous)) {
            $transaction = new TransactionalCommand();

            //Delete old entity
            $transaction->addCommand($this->previous->queueDelete());

            //Store new entity if any (leading)
            $transaction->addCommand($this->queueRelated($parentCommand), true);

            //We don't need previous reference anymore
            $this->previous = null;

            return $transaction;
        }

        return $this->queueRelated($parentCommand);
    }

    /**
     * Store related instance.
     *
     * @param ContextualCommandInterface $parentCommand
     *
     * @return CommandInterface
     */
    private function queueRelated(ContextualCommandInterface $parentCommand): CommandInterface
    {
        if (empty($this->instance)) {
            return new NullCommand();
        }

        //Related entity store command
        $innerCommand = $this->instance->queueStore(true);

        //Inversed version of BelongsTo
        if (!$this->isSynced($this->parent, $this->instance)) {
            //Syncing FKs after primary command been executed
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
     * Where statement to load outer record.
     *
     * @return array
     */
    protected function whereStatement(): array
    {
        $where = parent::whereStatement();

        if (!empty($morphKey = $this->key(Record::MORPH_KEY))) {
            //HasOne relation support additional morph key
            $where['{@}.' . $this->key(Record::MORPH_KEY)] = $this->orm->define(
                get_class($this->parent),
                ORMInterface::R_ROLE_NAME
            );
        }

        return $where;
    }
}