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
use Spiral\ORM\ORMInterface;
use Spiral\ORM\Record;

/**
 * Similar to BelongsTo, however morph key value used to resolve parent record. Record allows
 * mutable parent type.
 */
class BelongsToMorphedRelation extends SingularRelation
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
    public function getClass(): string
    {
        if (empty($parentType = $this->parent->getField($this->key(Record::MORPH_KEY)))) {
            return parent::getClass();
        }

        //Resolve parent using role map
        return $this->schema[Record::MORPHED_ALIASES][$parentType];
    }

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
        $parentCommand->addContext($this->schema[Record::MORPH_KEY], null);

        return new NullCommand();
    }

    /**
     * {@inheritdoc}
     */
    protected function getRoles(): array
    {
        return $this->schema[ORMInterface::R_ROLE_NAME];
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
        /*
         * Only queue parent relations when parent wasn't saved already, attention, this might
         * cause an issues with very deep recursive structures (and it's expected).
         */
        $innerCommand = $this->instance->queueStore(
            !$this->instance->isLoaded()
        );

        if (!$this->isSynced($this->parent, $this->instance)) {
            //Syncing FKs before primary command been executed
            $innerCommand->onExecute(function ($innerCommand) use ($parentCommand) {
                $parentCommand->addContext(
                    $this->key(Record::INNER_KEY),
                    $this->lookupKey(Record::OUTER_KEY, $this->parent, $innerCommand)
                );

                //Morph key value
                $parentCommand->addContext(
                    $this->key(Record::MORPH_KEY),
                    $this->orm->define(get_class($this->instance), ORMInterface::R_ROLE_NAME)
                );
            });
        }

        return $innerCommand;
    }
}