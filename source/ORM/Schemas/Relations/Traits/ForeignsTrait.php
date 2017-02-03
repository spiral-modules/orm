<?php
/**
 * Spiral, Core Components
 *
 * @author Wolfy-J
 */

namespace Spiral\ORM\Schemas\Relations\Traits;

use Spiral\Database\Schemas\Prototypes\AbstractColumn;
use Spiral\Database\Schemas\Prototypes\AbstractTable;

/**
 * Simplified functionality to create foreign for a given schema.
 */
trait ForeignsTrait
{
    /**
     * @param AbstractTable  $table
     * @param AbstractColumn $source
     * @param AbstractColumn $target
     * @param string         $onDelete
     * @param string         $onUpdate
     */
    protected function createForeign(
        AbstractTable $table,
        AbstractColumn $source,
        AbstractColumn $target,
        string $onDelete,
        string $onUpdate
    ) {
        $foreignKey = $table->foreign($source->getName())->references(
            $target->getTable(),
            $target->getName(),
            false
        );

        $foreignKey->onDelete($onDelete);
        $foreignKey->onUpdate($onUpdate);
    }
}