<?php
/**
 * Spiral, Core Components
 *
 * @author Wolfy-J
 */

namespace Spiral\Tests\ORM\Traits;

use Spiral\Core\Container;
use Spiral\Database\DatabaseManager;
use Spiral\Models\Reflections\ReflectionEntity;
use Spiral\ORM\Configs\MutatorsConfig;
use Spiral\ORM\Configs\RelationsConfig;
use Spiral\ORM\Entities\Loaders;
use Spiral\ORM\Entities\Relations;
use Spiral\ORM\Record;
use Spiral\ORM\Schemas;

trait ORMTrait
{
    private $dbConfig;

    /**
     * @return Schemas\SchemaBuilder
     */
    protected function makeBuilder(DatabaseManager $dbal)
    {
        return new Schemas\SchemaBuilder(
            $dbal,
            new Schemas\RelationBuilder($this->relationsConfig(), new Container())
        );
    }

    /**
     * @param string $class
     *
     * @return Schemas\RecordSchema
     */
    protected function makeSchema(string $class): Schemas\RecordSchema
    {
        return new Schemas\RecordSchema(new ReflectionEntity($class), $this->mutatorsConfig());
    }

    /**
     * @return MutatorsConfig
     */
    protected function mutatorsConfig()
    {
        return new MutatorsConfig([
            /*
             * Set of mutators to be applied for specific field types.
             */
            'mutators' => [
                'php:int'    => ['setter' => 'intval', 'getter' => 'intval'],
                'php:float'  => ['setter' => 'floatval', 'getter' => 'floatval'],
                'php:string' => ['setter' => 'strval'],
                'php:bool'   => ['setter' => 'boolval', 'getter' => 'boolval'],
            ],

            'aliases' => []
        ]);
    }

    /**
     * @return RelationsConfig
     */
    protected function relationsConfig()
    {
        return new RelationsConfig([
            Record::BELONGS_TO         => [
                RelationsConfig::SCHEMA_CLASS => Schemas\Relations\BelongsToSchema::class,
                RelationsConfig::LOADER_CLASS => Loaders\BelongsToLoader::class,
                RelationsConfig::ACCESS_CLASS => Relations\BelongsToRelation::class
            ],
            Record::HAS_ONE            => [
                RelationsConfig::SCHEMA_CLASS => Schemas\Relations\HasOneSchema::class,
                RelationsConfig::LOADER_CLASS => Loaders\HasOneLoader::class,
                RelationsConfig::ACCESS_CLASS => Relations\HasOneRelation::class
            ],
            Record::HAS_MANY           => [
                RelationsConfig::SCHEMA_CLASS => Schemas\Relations\HasManySchema::class,
                RelationsConfig::LOADER_CLASS => Loaders\HasManyLoader::class,
                RelationsConfig::ACCESS_CLASS => Relations\HasManyRelation::class

            ],
            Record::MANY_TO_MANY       => [
                RelationsConfig::SCHEMA_CLASS => Schemas\Relations\ManyToManySchema::class,
                RelationsConfig::LOADER_CLASS => Loaders\ManyToManyLoader::class,
                RelationsConfig::ACCESS_CLASS => Relations\ManyToManyRelation::class
            ],
            Record::BELONGS_TO_MORPHED => [
                RelationsConfig::SCHEMA_CLASS => Schemas\Relations\BelongsToMorphedSchema::class,
                RelationsConfig::ACCESS_CLASS => Relations\BelongsToMorphedRelation::class
            ],
            Record::MANY_TO_MORPHED    => [
                RelationsConfig::SCHEMA_CLASS => Schemas\Relations\ManyToMorphedSchema::class,
                RelationsConfig::ACCESS_CLASS => Relations\ManyToMorphedRelation::class
            ]
        ]);
    }
}