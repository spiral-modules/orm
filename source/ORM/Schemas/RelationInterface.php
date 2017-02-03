<?php
/**
 * Spiral, Core Components
 *
 * @author Wolfy-J
 */

namespace Spiral\ORM\Schemas;

use Spiral\Database\Schemas\Prototypes\AbstractTable;
use Spiral\ORM\Exceptions\RelationSchemaException;
use Spiral\ORM\Schemas\Definitions\RelationDefinition;

/**
 * Defines behaviour for a relation schemas. Relation schema constructor must accept relation
 * definition as input.
 */
interface RelationInterface
{
    /**
     * Get associated relation definition.
     *
     * @return RelationDefinition
     */
    public function getDefinition(): RelationDefinition;

    /**
     * Method must return set of tables being altered by relation.
     *
     * @param SchemaBuilder $builder
     *
     * @return AbstractTable[]
     *
     * @throws RelationSchemaException
     */
    public function declareTables(SchemaBuilder $builder): array;

    /**
     * Pack relation information info form or immutable array to be used as schema in runtime.
     *
     * @param SchemaBuilder $builder
     *
     * @return array
     *
     * @throws RelationSchemaException
     */
    public function packRelation(SchemaBuilder $builder): array;
}