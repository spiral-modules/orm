<?php
/**
 * Spiral, Core Components
 *
 * @author Wolfy-J
 */

namespace Spiral\ORM\Commands;

use Spiral\Database\Entities\Table;
use Spiral\ORM\Commands\Traits\ContextTrait;
use Spiral\ORM\Commands\Traits\PrimaryTrait;
use Spiral\ORM\Commands\Traits\WhereTrait;
use Spiral\ORM\ContextualCommandInterface;

/**
 * Contextual delete is command which delete where statement directly linked to it's context
 * (mutable delete).
 *
 * This creates ability to create postponed delete command which where statement will be resolved
 * only later in transactions.
 */
class ContextualDeleteCommand extends TableCommand implements ContextualCommandInterface
{
    use ContextTrait, PrimaryTrait, WhereTrait;

    /**
     * Where conditions (short where format).
     *
     * @var array
     */
    private $where = [];

    /**
     * UpdateCommand constructor.
     *
     * @param Table $table
     * @param array $where
     * @param array $values
     */
    public function __construct(Table $table, array $where, array $values = [])
    {
        parent::__construct($table);
        $this->where = $where;
    }

    /**
     * {@inheritdoc}
     */
    public function getDriver()
    {
        if ($this->isEmpty()) {
            //Nothing to do
            return null;
        }

        return $this->table->getDatabase()->getDriver();
    }

    /**
     * {@inheritdoc}
     */
    public function isEmpty(): bool
    {
        return empty($this->where) && empty($this->context);
    }

    /**
     * Inserting data into associated table.
     */
    public function execute()
    {
        if (!$this->isEmpty()) {
            $this->table->delete($this->context + $this->where)->run();
        }

        parent::execute();
    }
}