<?php
/**
 * Spiral, Core Components
 *
 * @author Wolfy-J
 */

namespace Spiral\ORM\Schemas;

use Spiral\Database\Schemas\Prototypes\AbstractTable;
use Spiral\ORM\Exceptions\SchemaException;
use Spiral\ORM\Schemas\Definitions\IndexDefinition;
use Spiral\ORM\Schemas\Definitions\RelationDefinition;

interface SchemaInterface
{
    /**
     * Related class name.
     *
     * @return string
     */
    public function getClass(): string;

    /**
     * Record role named used widely in relations to generate inner and outer keys, define related
     * class and table in morphed relations and etc. You can define your own role name by using
     * record constant MODEL_ROLE.
     *
     * Example:
     * Record: Records\Post with primary key "id"
     * Relation: HAS_ONE
     * Outer key: post_id
     *
     * @return string
     */
    public function getRole(): string;

    /**
     * Name of class responsible for model instantiation.
     *
     * @return string
     */
    public function getInstantiator(): string;

    /**
     * Name of associated database. Might return null to force default database usage.
     *
     * @return string|null
     *
     * @throws SchemaException
     */
    public function getDatabase();

    /**
     * Name of associated table.
     *
     * @return string
     *
     * @throws SchemaException
     */
    public function getTable(): string;

    /**
     * Get indexes declared by model.
     *
     * @return IndexDefinition[]|\Generator
     *
     * @throws SchemaException
     */
    public function getIndexes();

    /**
     * Get all defined record relations.
     *
     * @return RelationDefinition[]|\Generator
     *
     * @throws SchemaException
     */
    public function getRelations();

    /**
     * Define needed columns, indexes and foreign keys in a record related table.
     *
     * @param AbstractTable $table
     *
     * @return AbstractTable
     *
     * @throws SchemaException
     */
    public function declareTable(AbstractTable $table): AbstractTable;

    /**
     * Pack schema in a form compatible with entity class and selected mapper.
     *
     * @param SchemaBuilder $builder
     * @param AbstractTable $table Associated table.
     *
     * @return array
     *
     * @throws SchemaException
     */
    public function packSchema(SchemaBuilder $builder, AbstractTable $table): array;
}