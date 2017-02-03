<?php
/**
 * Spiral, Core Components
 *
 * @author Wolfy-J
 */

namespace Spiral\ORM\Schemas\Relations\Traits;

use Spiral\Database\Schemas\Prototypes\AbstractColumn;

trait TypecastTrait
{
    /**
     * Provides ability to resolve type for FK column to be matching external column type.
     *
     * @param AbstractColumn $column
     *
     * @return string
     */
    protected function resolveType(AbstractColumn $column): string
    {
        switch ($column->abstractType()) {
            case 'bigPrimary':
                return 'bigInteger';
            case 'primary':
                return 'integer';
            default:
                //Not primary key
                return $column->abstractType();
        }
    }
}