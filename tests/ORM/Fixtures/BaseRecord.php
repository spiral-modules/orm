<?php
/**
 * Spiral, Core Components
 *
 * @author Wolfy-J
 */
namespace Spiral\Tests\ORM\Fixtures;

use Spiral\ORM\Entities\RelationMap;
use Spiral\ORM\Record;

class BaseRecord extends Record
{
    public function getRelations(): RelationMap
    {
        return $this->relations;
    }
}