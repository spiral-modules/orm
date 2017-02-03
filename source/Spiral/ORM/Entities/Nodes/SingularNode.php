<?php
/**
 * Spiral, Core Components
 *
 * @author Wolfy-J
 */

namespace Spiral\ORM\Entities\Nodes;

use Spiral\ORM\Entities\Nodes\Traits\DuplicateTrait;
use Spiral\ORM\Exceptions\NodeException;

/**
 * Node with ability to push it's data into referenced tree location.
 */
class SingularNode extends AbstractNode
{
    use DuplicateTrait;

    /**
     * @var string
     */
    protected $innerKey;

    /**
     * @param array       $columns
     * @param string      $innerKey Inner relation key (for example user_id)
     * @param string|null $outerKey Outer (parent) relation key (for example id = parent.id)
     * @param   string    $primaryKey
     */
    public function __construct(
        array $columns,
        string $innerKey,
        string $outerKey,
        string $primaryKey
    ) {
        parent::__construct($columns, $outerKey);
        $this->innerKey = $innerKey;

        //Using primary keys (if any) to de-duplicate results
        $this->primaryKey = $primaryKey;
    }

    /**
     * {@inheritdoc}
     */
    protected function pushData(array &$data)
    {
        if (empty($this->parent)) {
            throw new NodeException("Unable to register data tree, parent is missing");
        }

        if (is_null($data[$this->innerKey])) {
            //No data was loaded
            return;
        }

        //Mounting parsed data into parent under defined container
        $this->parent->mount(
            $this->container,
            $this->outerKey,
            $data[$this->innerKey],
            $data
        );
    }
}