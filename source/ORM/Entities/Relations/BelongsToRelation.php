<?php
/**
 * Spiral, Core Components
 *
 * @author Wolfy-J
 */

namespace Spiral\ORM\Entities\Relations;

use Spiral\ORM\CommandInterface;
use Spiral\ORM\Commands\NullCommand;
use Spiral\ORM\ContextualCommandInterface;
use Spiral\ORM\Entities\Relations\Traits\LookupTrait;
use Spiral\ORM\Exceptions\RelationException;
use Spiral\ORM\Record;

/**
 * Complex relation with ability to mount inner_key context into parent save command.
 */
class BelongsToRelation extends SingularRelation
{
    use LookupTrait;

    /**
     * Always saved before parent.
     */
    const LEADING_RELATION = true;

    /**
     * No placeholder for belongs to.
     */
    const CREATE_PLACEHOLDER = false;

    /**
     * {@inheritdoc}
     */
    public function setRelated($value)
    {
        //Make sure value is accepted
        $this->assertValid($value);

        $this->loaded = true;
        $this->instance = $value;
    }

    /**
     * @param ContextualCommandInterface $parentCommand
     *
     * @return CommandInterface
     *
     * @throws RelationException
     */
    public function queueCommands(ContextualCommandInterface $parentCommand): CommandInterface
    {
        if (!empty($this->instance)) {
            return $this->queueRelated($parentCommand);
        }

        if (!$this->schema[Record::NULLABLE]) {
            throw new RelationException("No data presented in non nullable relation");
        }

        $parentCommand->addContext($this->schema[Record::INNER_KEY], null);

        return new NullCommand();
    }

    /**
     * Store related instance
     *
     * @param ContextualCommandInterface $parentCommand
     *
     * @return ContextualCommandInterface
     */
    private function queueRelated(
        ContextualCommandInterface $parentCommand
    ): ContextualCommandInterface {
        //Command or command set needed to store
        $innerCommand = $this->instance->queueStore(true);

        if (!$this->isSynced($this->parent, $this->instance)) {
            //Syncing FKs before primary command been executed
            $innerCommand->onExecute(function ($innerCommand) use ($parentCommand) {
                $parentCommand->addContext(
                    $this->key(Record::INNER_KEY),
                    $this->lookupKey(Record::OUTER_KEY, $this->parent, $innerCommand)
                );
            });
        }

        return $innerCommand;
    }
}