<?php
/**
 * Spiral, Core Components
 *
 * @author Wolfy-J
 */
namespace Spiral\Tests\ORM;

use Mockery as m;
use Spiral\Database\Entities\Table;
use Spiral\ORM\Commands\UpdateCommand;

class UpdateCommandTest extends \PHPUnit_Framework_TestCase
{
    public function testIsEmpty()
    {
        $update = new UpdateCommand(m::mock(Table::class), ['where' => 'value'], []);
        $this->assertTrue($update->isEmpty());

        $this->assertSame(['where' => 'value'], $update->getWhere());
    }

    public function testIsEmptyData()
    {
        $update = new UpdateCommand(m::mock(Table::class), ['where' => 'value'], [
            'name' => 'value'
        ]);
        $this->assertFalse($update->isEmpty());
    }

    public function testIsEmptyContext()
    {
        $update = new UpdateCommand(m::mock(Table::class), ['where' => 'value'], []);
        $this->assertTrue($update->isEmpty());

        $update->addContext('name', 'value');
        $this->assertFalse($update->isEmpty());
    }
}