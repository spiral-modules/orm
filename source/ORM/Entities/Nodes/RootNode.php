<?php
/**
 * Spiral, Core Components
 *
 * @author Wolfy-J
 */

namespace Spiral\ORM\Entities\Nodes;

use Spiral\ORM\Entities\Nodes\Traits\DuplicateTrait;

class RootNode extends OutputNode
{
    use DuplicateTrait;

    /**
     * @param array  $columns
     * @param string $primaryKey
     */
    public function __construct(array $columns = [], string $primaryKey)
    {
        parent::__construct($columns, null);
        $this->primaryKey = $primaryKey;
    }

    /**
     * {@inheritdoc}
     */
    protected function pushData(array &$data)
    {
        $this->result[] = &$data;
    }
}