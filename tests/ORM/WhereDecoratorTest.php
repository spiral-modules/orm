<?php
/**
 * Spiral, Core Components
 *
 * @author Wolfy-J
 */
namespace Spiral\Tests\ORM;

use Mockery as m;
use Spiral\Database\Builders\SelectQuery;
use Spiral\Database\Injections\Fragment;
use Spiral\ORM\Helpers\WhereDecorator;

class WhereDecoratorTest extends \PHPUnit_Framework_TestCase
{
    public function testDecorator()
    {
        $select = m::mock(SelectQuery::class);
        $decorator = new WhereDecorator($select, 'where', 'alias');

        $this->assertSame('where', $decorator->getTarget());
        $decorator->setTarget('onWhere');
        $this->assertSame('onWhere', $decorator->getTarget());
    }

    public function testWhere()
    {
        $select = m::mock(SelectQuery::class);
        $decorator = new WhereDecorator($select, 'where', 'alias');

        $select->shouldReceive('where')->with('name', 'value');

        $decorator->where('name', 'value');
    }

    public function testAndWhere()
    {
        $select = m::mock(SelectQuery::class);
        $decorator = new WhereDecorator($select, 'where', 'alias');

        $select->shouldReceive('andWhere')->with('name', 'value');

        $decorator->andWhere('name', 'value');
    }

    public function testOrWhere()
    {
        $select = m::mock(SelectQuery::class);
        $decorator = new WhereDecorator($select, 'where', 'alias');

        $select->shouldReceive('orWhere')->with('name', 'value');

        $decorator->orWhere('name', 'value');
    }

    public function testWhereJoin()
    {
        $select = m::mock(SelectQuery::class);
        $decorator = new WhereDecorator($select, 'onWhere', 'alias');

        $select->shouldReceive('onWhere')->with('name', 'value');

        $decorator->where('name', 'value');
    }

    public function testWhereJoinClosure()
    {
        $select = m::mock(SelectQuery::class);
        $decorator = new WhereDecorator($select, 'onWhere', 'alias');

        $select->shouldReceive('onWhere')->with('alias.name', 'value');

        $decorator->where(function (WhereDecorator $query) {
            $query->where('{@}.name', 'value');
        });
    }

    public function testWhereOrJoin()
    {
        $select = m::mock(SelectQuery::class);
        $decorator = new WhereDecorator($select, 'onWhere', 'alias');

        $select->shouldReceive('orOnWhere')->with('name', 'value');

        $decorator->orWhere('name', 'value');
    }

    public function testWhereOrJoinClosure()
    {
        $select = m::mock(SelectQuery::class);
        $decorator = new WhereDecorator($select, 'onWhere', 'alias');

        $select->shouldReceive('orOnWhere')->with('alias.name', 'value');

        $decorator->orWhere(function (WhereDecorator $query) {
            $query->orWhere('{@}.name', 'value');
        });
    }


    public function testAndWhereOrJoinClosure()
    {
        $select = m::mock(SelectQuery::class);
        $decorator = new WhereDecorator($select, 'onWhere', 'alias');

        $select->shouldReceive('andOnWhere')->with('alias.name', 'value');

        $decorator->andWhere(function (WhereDecorator $query) {
            $query->andWhere('{@}.name', 'value');
        });
    }

    public function testAlias()
    {
        $select = m::mock(SelectQuery::class);
        $decorator = new WhereDecorator($select, 'onWhere', 'alias');

        $select->shouldReceive('orOnWhere')->with('alias.name', 'value');

        $decorator->orWhere('{@}.name', 'value');
    }

    public function testFragment()
    {
        $select = m::mock(SelectQuery::class);
        $decorator = new WhereDecorator($select, 'onWhere', 'alias');

        $f = new Fragment('hi');

        $select->shouldReceive('orOnWhere')->with($f);

        $decorator->orWhere($f);
    }
}