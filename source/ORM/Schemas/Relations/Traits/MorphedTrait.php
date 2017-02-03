<?php
/**
 * Spiral, Core Components
 *
 * @author Wolfy-J
 */

namespace Spiral\ORM\Schemas\Relations\Traits;

use Spiral\Database\Schemas\Prototypes\AbstractColumn;
use Spiral\ORM\Exceptions\RelationSchemaException;
use Spiral\ORM\ORMInterface;
use Spiral\ORM\Record;
use Spiral\ORM\Schemas\Definitions\RelationDefinition;
use Spiral\ORM\Schemas\SchemaBuilder;

/**
 * Helps to locate all outer records matching given interface.
 */
trait MorphedTrait
{
    /**
     * Resolving outer key.
     *
     * @param \Spiral\ORM\Schemas\SchemaBuilder $builder
     *
     * @return \Spiral\Database\Schemas\Prototypes\AbstractColumn|null When no outer records found
     *                                                                 NULL will be returned.
     */
    protected function findOuter(SchemaBuilder $builder)
    {
        foreach ($this->findTargets($builder) as $schema) {
            $outerTable = $builder->requestTable($schema->getTable(), $schema->getDatabase());
            $outerKey = $this->option(Record::OUTER_KEY);

            if ($outerKey == ORMInterface::R_PRIMARY_KEY) {
                $outerKey = current($outerTable->getPrimaryKeys());
            }

            if (!$outerTable->hasColumn($outerKey)) {
                throw new RelationSchemaException(sprintf("Outer key '%s'.'%s' (%s) does not exists",
                    $outerTable->getName(),
                    $outerKey,
                    $this->getDefinition()->getName()
                ));
            }

            return $outerTable->column($outerKey);
        }

        return null;
    }

    /**
     * Make sure all tables have same outer key.
     *
     * @param \Spiral\ORM\Schemas\SchemaBuilder                  $builder
     * @param \Spiral\Database\Schemas\Prototypes\AbstractColumn $column
     *
     * @throws RelationSchemaException
     */
    protected function verifyOuter(SchemaBuilder $builder, AbstractColumn $column)
    {
        foreach ($this->findTargets($builder) as $schema) {
            $outerTable = $builder->requestTable($schema->getTable(), $schema->getDatabase());

            if (!$outerTable->hasColumn($column->getName())) {
                //Column is missing
                throw new RelationSchemaException(
                    "Unable to build morphed relation, outer key '{$column->getName()}' "
                    . "is missing in '{$outerTable->getName()}'"
                );
            }

            if ($outerTable->column($column->getName())->phpType() != $column->phpType()) {
                //Inconsistent type
                throw new RelationSchemaException(
                    "Unable to build morphed relation, outer key '{$column->getName()}' "
                    . "has different type in '{$outerTable->getName()}'"
                );
            }
        }
    }

    /**
     * Find all matched schemas.
     *
     * @param \Spiral\ORM\Schemas\SchemaBuilder $builder
     *
     * @return \Generator|\Spiral\ORM\Schemas\SchemaInterface[]
     */
    protected function findTargets(SchemaBuilder $builder): \Generator
    {
        foreach ($builder->getSchemas() as $schema) {
            if (!is_a($schema->getClass(), $this->getDefinition()->getTarget(), true)) {
                //Not our targets
                continue;
            }
            yield $schema;
        }
    }

    /**
     * @return \Spiral\ORM\Schemas\Definitions\RelationDefinition
     */
    abstract public function getDefinition(): RelationDefinition;

    /**
     * @param string $key
     *
     * @return mixed
     */
    abstract protected function option(string $key);
}