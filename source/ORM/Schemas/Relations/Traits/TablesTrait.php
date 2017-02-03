<?php
/**
 * Spiral, Core Components
 *
 * @author Wolfy-J
 */

namespace Spiral\ORM\Schemas\Relations\Traits;

use Spiral\Database\Schemas\Prototypes\AbstractTable;
use Spiral\ORM\Exceptions\RelationSchemaException;
use Spiral\ORM\Schemas\Definitions\RelationDefinition;
use Spiral\ORM\Schemas\SchemaBuilder;

trait TablesTrait
{
    /**
     * Get table linked with source relation model.
     *
     * @param SchemaBuilder $builder
     *
     * @return AbstractTable
     *
     * @throws RelationSchemaException
     */
    protected function sourceTable(SchemaBuilder $builder): AbstractTable
    {
        $source = $this->getDefinition()->sourceContext();

        return $builder->requestTable($source->getTable(), $source->getDatabase());
    }

    /**
     * Get table linked with target relation model.
     *
     * @param SchemaBuilder $builder
     *
     * @return AbstractTable
     *
     * @throws RelationSchemaException
     */
    protected function targetTable(SchemaBuilder $builder): AbstractTable
    {
        $target = $this->getDefinition()->targetContext();
        if (empty($target)) {
            throw new RelationSchemaException(sprintf(
                "Unable to get target context '%s' in '%s'.'%s'",
                $this->getDefinition()->getTarget(),
                $this->getDefinition()->sourceContext()->getClass(),
                $this->getDefinition()->getName()
            ));
        }

        return $builder->requestTable($target->getTable(), $target->getDatabase());
    }

    /**
     * @return RelationDefinition
     */
    abstract protected function getDefinition(): RelationDefinition;
}