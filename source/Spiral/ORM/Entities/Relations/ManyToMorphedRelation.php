<?php
/**
 * Spiral, Core Components
 *
 * @author Wolfy-J
 */

namespace Spiral\ORM\Entities\Relations;

use Spiral\ORM\CommandInterface;
use Spiral\ORM\Commands\TransactionalCommand;
use Spiral\ORM\ContextualCommandInterface;
use Spiral\ORM\Exceptions\RelationException;
use Spiral\ORM\ORMInterface;
use Spiral\ORM\Record;

/**
 * ManyToMorphed relation used to aggregate multiple ManyToMany relations based on their role type.
 * In addition it can route some function to specified nested ManyToMany relation based on record
 * role.
 *
 * Works similary to RelationMap and does not work as direct relation but rather bypassed calls to
 * sub relations.
 *
 * Example:
 * dump($tag->tagged->posts->count());
 *
 * @see ManyToMany
 * @see \Spiral\ORM\Schemas\Relations\ManyToMorphedSchema
 */
class ManyToMorphedRelation extends AbstractRelation
{
    /**
     * Set of nested relations.
     *
     * @var ManyToManyRelation[]
     */
    private $nested = [];

    /**
     * {@inheritdoc}
     */
    public function hasRelated(): bool
    {
        throw new RelationException(
            "Unable to check existence of related data in ManyToMorphed relation, use nested relation"
        );
    }

    /**
     * {@inheritdoc}
     */
    public function setRelated($value)
    {
        throw new RelationException(
            "Unable to set related data to ManyToMorphed relation, use nested relation"
        );
    }

    /**
     * {@inheritdoc}
     */
    public function getRelated()
    {
        return $this;
    }

    /**
     * All possible outer variations.
     *
     * @return array
     */
    public function getVariations(): array
    {
        return $this->schema[Record::MORPHED_ALIASES];
    }

    /**
     * Get nested relation for a given variation.
     *
     * @param string $variation
     *
     * @return ManyToManyRelation
     *
     * @throws RelationException
     */
    public function getVariation(string $variation): ManyToManyRelation
    {
        if (isset($this->nested[$variation])) {
            return $this->nested[$variation];
        }

        if (!isset($this->schema[Record::MORPHED_ALIASES][$variation])) {
            throw new RelationException("Undefined morphed variation '{$variation}'");
        }

        $class = $this->schema[Record::MORPHED_ALIASES][$variation];

        $relation = new ManyToManyRelation(
            $class,
            $this->makeSchema($class),
            $this->orm,
            $this->orm->define($class, ORMInterface::R_ROLE_NAME)
        );

        return $this->nested[$variation] = $relation->withContext($this->parent, false);
    }

    /**
     * @param string $name
     *
     * @return ManyToManyRelation
     */
    public function __get(string $name)
    {
        return $this->getVariation($name);
    }

    /**
     * @param string $variation
     * @param mixed  $value
     */
    public function __set(string $variation, $value)
    {
        $this->getVariation($variation)->setRelated($value);
    }

    /**
     * @param string $variation
     *
     * @return bool
     */
    public function __isset(string $variation)
    {
        return $this->getVariation($variation)->hasRelated();
    }

    /**
     * @param string $variation
     */
    public function __unset(string $variation)
    {
        $this->getVariation($variation)->setRelated(null);
    }

    /**
     * {@inheritdoc}
     */
    public function queueCommands(ContextualCommandInterface $parentCommand): CommandInterface
    {
        $transaction = new TransactionalCommand();
        foreach ($this->nested as $relation) {
            $transaction->addCommand($relation->queueCommands($parentCommand));
        }

        return $transaction;
    }

    /**
     * Create relation schema for nested relation.
     *
     * @param string $class
     *
     * @return array
     */
    protected function makeSchema(string $class): array
    {
        //Using as basement
        $schema = $this->schema;
        unset($schema[Record::MORPHED_ALIASES]);

        //We do not have this information in morphed relation but it's required for ManyToMany
        $schema[Record::WHERE] = [];

        //This must be unified in future, for now we can fetch columns directly from there
        $recordSchema = $this->orm->define($class, ORMInterface::R_SCHEMA);
        $schema[Record::RELATION_COLUMNS] = array_keys($recordSchema[Record::SH_DEFAULTS]);

        return $schema;
    }
}