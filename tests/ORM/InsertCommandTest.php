<?php
/**
 * Spiral, Core Components
 *
 * @author Wolfy-J
 */
namespace Spiral\Tests\ORM;

use Mockery as m;
use Spiral\Database\Entities\Table;
use Spiral\ORM\Commands\InsertCommand;

class InsertCommandTest extends \PHPUnit_Framework_TestCase
{
    public function testIsEmpty()
    {
        $insert = new InsertCommand(m::mock(Table::class));
        $this->assertTrue($insert->isEmpty());
    }

    public function testIsEmptyData()
    {
        $insert = new InsertCommand(m::mock(Table::class), ['name' => 'value']);
        $this->assertFalse($insert->isEmpty());
    }

    public function testIsEmptyContext()
    {
        $insert = new InsertCommand(m::mock(Table::class));
        $this->assertTrue($insert->isEmpty());

        $insert->addContext('name', 'value');
        $this->assertFalse($insert->isEmpty());
    }
}