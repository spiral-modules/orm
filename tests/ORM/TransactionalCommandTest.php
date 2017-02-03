<?php
/**
 * Spiral, Core Components
 *
 * @author Wolfy-J
 */
namespace Spiral\Tests\ORM;

use Mockery as m;
use Spiral\ORM\Commands\DeleteCommand;
use Spiral\ORM\Commands\InsertCommand;
use Spiral\ORM\Commands\NullCommand;
use Spiral\ORM\Commands\TransactionalCommand;

class TransactionalCommandTest extends \PHPUnit_Framework_TestCase
{
    public function testNestedCommands()
    {
        $command = new TransactionalCommand();

        $command->addCommand(new NullCommand());
        $command->addCommand(new NullCommand());
        $command->addCommand(m::mock(InsertCommand::class));
        $command->addCommand(m::mock(InsertCommand::class));

        $count = 0;
        foreach ($command as $sub) {
            $this->assertInstanceOf(InsertCommand::class, $sub);
            $count++;
        }

        $this->assertSame(2, $count);

        //Nothing
        $command->execute();
        $command->complete();
        $command->rollBack();
    }

    public function testByPassGood()
    {
        $command = new TransactionalCommand();
        $command->addCommand(m::mock(InsertCommand::class), true);
    }

    /**
     * @expectedException \Spiral\ORM\Exceptions\ORMException
     * @expectedExceptionMessage  Only contextual commands can be used as leading
     */
    public function testByPassBad()
    {
        $command = new TransactionalCommand();
        $command->addCommand(m::mock(DeleteCommand::class), true);
    }

    public function testGetLeadingGood()
    {
        $command = new TransactionalCommand();
        $command->addCommand($lead = m::mock(InsertCommand::class), true);

        $this->assertSame($lead, $command->getLeading());
    }

    /**
     * @expectedException \Spiral\ORM\Exceptions\ORMException
     */
    public function testGetLeadingBad()
    {
        $command = new TransactionalCommand();
        $command->getLeading();
    }

    public function testIsEmpty()
    {
        $command = new TransactionalCommand();
        $command->addCommand($lead = m::mock(InsertCommand::class), true);

        $lead->shouldReceive('isEmpty')->andReturn(true);

        $this->assertSame(true, $command->isEmpty());
    }

    public function testGetContext()
    {
        $command = new TransactionalCommand();
        $command->addCommand($lead = m::mock(InsertCommand::class), true);

        $lead->shouldReceive('getContext')->andReturn(['hi']);

        $this->assertSame(['hi'], $command->getContext());
    }

    public function testPrimaryKey()
    {
        $command = new TransactionalCommand();
        $command->addCommand($lead = m::mock(InsertCommand::class), true);

        $lead->shouldReceive('primaryKey')->andReturn(900);

        $this->assertSame(900, $command->primaryKey());
    }

    public function testAddContext()
    {
        $command = new TransactionalCommand();
        $command->addCommand($lead = m::mock(InsertCommand::class), true);

        $lead->shouldReceive('addContext')->with('name', 'value');

        $command->addContext('name', 'value');
    }

    public function testPassCallbackExecute()
    {
        $command = new TransactionalCommand();
        $command->addCommand($lead = m::mock(InsertCommand::class), true);

        $f = function () {
        };

        $lead->shouldReceive('onExecute')->with($f);
        $command->onExecute($f);
    }

    public function testPassCallbackComplete()
    {
        $command = new TransactionalCommand();
        $command->addCommand($lead = m::mock(InsertCommand::class), true);

        $f = function () {
        };

        $lead->shouldReceive('onComplete')->with($f);
        $command->onComplete($f);
    }

    public function testPassCallbackRollback()
    {
        $command = new TransactionalCommand();
        $command->addCommand($lead = m::mock(InsertCommand::class), true);

        $f = function () {
        };

        $lead->shouldReceive('onRollback')->with($f);
        $command->onRollBack($f);
    }
}