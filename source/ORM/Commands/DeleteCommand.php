<?php
/**
 * Spiral, Core Components
 *
 * @author Wolfy-J
 */

namespace Spiral\ORM\Commands;

use Spiral\Database\Entities\Table;
use Spiral\ORM\Commands\Traits\WhereTrait;

class DeleteCommand extends TableCommand
{
    use WhereTrait;

    /**
     * @param Table $table
     * @param mixed $where
     */
    public function __construct(Table $table, array $where)
    {
        parent::__construct($table);
        $this->where = $where;
    }

    /**
     * Inserting data into associated table.
     */
    public function execute()
    {
        $this->table->delete($this->where)->run();
        parent::execute();
    }
}