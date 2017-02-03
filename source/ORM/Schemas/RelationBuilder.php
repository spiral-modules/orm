<?php
/**
 * Spiral, Core Components
 *
 * @author Wolfy-J
 */

namespace Spiral\ORM\Schemas;

use Spiral\Core\FactoryInterface;
use Spiral\ORM\Configs\RelationsConfig;
use Spiral\ORM\Exceptions\DefinitionException;
use Spiral\ORM\Schemas\Definitions\RelationContext;
use Spiral\ORM\Schemas\Definitions\RelationDefinition;

/**
 * Subsection of SchemaBuilder used to configure tables and columns defined by model to model
 * relations.
 */
class RelationBuilder
{
    /**
     * @invisible
     * @var RelationsConfig
     */
    protected $config;

    /**
     * @invisible
     * @var FactoryInterface
     */
    protected $factory;

    /**
     * Set of relation definitions.
     *
     * @var RelationInterface[]
     */
    private $relations = [];

    /**
     * @param RelationsConfig  $config
     * @param FactoryInterface $factory
     */
    public function __construct(RelationsConfig $config, FactoryInterface $factory)
    {
        $this->config = $config;
        $this->factory = $factory;
    }

    /**
     * Registering new relation definition. At this moment function would not check if relation is
     * unique and will redeclare it.
     *
     * @param SchemaBuilder      $builder
     * @param RelationDefinition $definition Relation options (definition).
     *
     * @throws DefinitionException
     */
    public function registerRelation(SchemaBuilder $builder, RelationDefinition $definition)
    {
        if (!$this->config->hasRelation($definition->getType())) {
            throw new DefinitionException(sprintf(
                "Undefined relation type '%s' in '%s'.'%s'",
                $definition->getType(),
                $definition->sourceContext()->getClass(),
                $definition->getName()
            ));
        }

        if ($definition->isLateBinded()) {
            /**
             * Late binded relations locate their parent based on all existed records.
             */
            $definition = $this->locateOuter($builder, $definition);
        }

        $class = $this->config->relationClass(
            $definition->getType(),
            RelationsConfig::SCHEMA_CLASS
        );

        //Creating relation schema
        $relation = $this->factory->make($class, compact('definition'));

        $this->relations[] = $relation;
    }

    /**
     * Create inverse relations where needed.
     *
     * @param SchemaBuilder $builder
     *
     * @throws DefinitionException
     */
    public function inverseRelations(SchemaBuilder $builder)
    {
        /**
         * Inverse process is relation specific.
         */
        foreach ($this->relations as $relation) {
            $definition = $relation->getDefinition();

            if ($definition->needInversion()) {
                if (!$relation instanceof InversableRelationInterface) {
                    throw new DefinitionException(sprintf(
                        "Unable to inverse relation '%s'.'%s', relation schema '%s' is non inversable",
                        $definition->sourceContext()->getClass(),
                        $definition->getName(),
                        get_class($relation)
                    ));
                }

                $inversed = $relation->inverseDefinition($builder, $definition->getInverse());
                foreach ($inversed as $definition) {
                    $this->registerRelation($builder, $definition);
                }
            }
        }
    }

    /**
     * All declared relations.
     *
     * @return RelationInterface[]
     */
    public function getRelations(): array
    {
        return $this->relations;
    }

    /**
     * Declare set of tables for each relation. Method must return Generator of AbstractTable
     * sequentially (attention, non sequential processing will cause collision issues between
     * tables).
     *
     * @param SchemaBuilder $builder
     *
     * @return \Generator
     */
    public function declareTables(SchemaBuilder $builder): \Generator
    {
        foreach ($this->relations as $relation) {
            foreach ($relation->declareTables($builder) as $table) {
                yield $table;
            }
        }
    }

    /**
     * Pack relation schemas for specific model class in order to be saved in memory.
     *
     * @param string        $class
     * @param SchemaBuilder $builder
     *
     * @return array
     */
    public function packRelations(string $class, SchemaBuilder $builder): array
    {
        $result = [];
        foreach ($this->relations as $relation) {
            $definition = $relation->getDefinition();

            if ($definition->sourceContext()->getClass() == $class) {
                //Packing relation, relation schema are given with associated table
                $result[$definition->getName()] = $relation->packRelation($builder);
            }
        }

        return $result;
    }

    /**
     * Populate entity target based on interface or role.
     *
     * @param SchemaBuilder                                      $builder
     * @param \Spiral\ORM\Schemas\Definitions\RelationDefinition $definition
     *
     * @return \Spiral\ORM\Schemas\Definitions\RelationDefinition
     *
     * @throws DefinitionException
     */
    protected function locateOuter(
        SchemaBuilder $builder,
        RelationDefinition $definition
    ): RelationDefinition {
        /**
         * todo: add functionality to resolve database alias
         */

        if (!empty($definition->targetContext())) {
            //Nothing to do, already have outer parent
            return $definition;
        }

        $found = null;
        foreach ($builder->getSchemas() as $schema) {
            if ($this->matchBinded($definition->getTarget(), $schema)) {
                if (!empty($found)) {
                    //Multiple records found
                    throw new DefinitionException(sprintf(
                        "Ambiguous target of '%s' for late binded relation %s.%s",
                        $definition->getTarget(),
                        $definition->sourceContext()->getClass(),
                        $definition->getName()
                    ));
                }

                $found = $schema;
            }
        }

        if (empty($found)) {
            throw new DefinitionException(sprintf(
                "Unable to locate outer record of '%s' for late binded relation %s.%s",
                $definition->getTarget(),
                $definition->sourceContext()->getClass(),
                $definition->getName()
            ));
        }

        return $definition->withContext(
            $definition->sourceContext(),
            RelationContext::createContent(
                $found,
                $builder->requestTable($found->getTable(), $found->getDatabase())
            )
        );
    }

    /**
     * Check if schema matches relation target.
     *
     * @param string                              $target
     * @param \Spiral\ORM\Schemas\SchemaInterface $schema
     *
     * @return bool
     */
    private function matchBinded(string $target, SchemaInterface $schema): bool
    {
        if ($schema->getRole() == $target) {
            return true;
        }

        if (interface_exists($target) && is_a($schema->getClass(), $target, true)) {
            //Match by interface
            return true;
        }

        return false;
    }
}