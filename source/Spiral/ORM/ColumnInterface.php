<?php
/**
 * Spiral, Core Components
 *
 * @author Wolfy-J
 */

namespace Spiral\ORM;

use Spiral\Database\Schemas\Prototypes\AbstractColumn;

/**
 * Interface declares ability for RecordAccessor to describe database columns.
 */
interface ColumnInterface
{
    /**
     * Configure column.
     *
     * @param AbstractColumn $column
     */
    public static function describeColumn(AbstractColumn $column);
}