<?php
/**
 * Spiral, Core Components
 *
 * @author Wolfy-J
 */
namespace Spiral\Tests\ORM;

use Mockery as m;
use Spiral\ORM\EntityMap;
use Spiral\Tests\ORM\Fixtures\User;

class EntityMapTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @expectedException \Spiral\ORM\Exceptions\MapException
     */
    public function testIdentityError()
    {
        $map = new EntityMap(2);
        $m = m::mock(User::class);
        $m->shouldReceive('primaryKey')->andReturnNull();

        $map->remember($m);
    }

    public function testIdentityOK()
    {
        $map = new EntityMap(2);
        $m = m::mock(User::class);
        $m->shouldReceive('primaryKey')->andReturn(1);

        $map->remember($m);

        $this->assertTrue($map->has(get_class($m), $m->primaryKeY()));

        $map->forget($m);
        $this->assertFalse($map->has(get_class($m), $m->primaryKeY()));
    }

    /**
     * @expectedException \Spiral\ORM\Exceptions\MapException
     */
    public function testCacheSize()
    {
        $map = new EntityMap(2);
        $m = m::mock(User::class);
        $m->shouldReceive('primaryKey')->andReturn(1);

        $map->remember($m);

        $m = m::mock(User::class);
        $m->shouldReceive('primaryKey')->andReturn(2);

        $map->remember($m);

        $m = m::mock(User::class);
        $m->shouldReceive('primaryKey')->andReturn(3);

        $map->remember($m, false);
    }

    public function testCacheSizeForce()
    {
        $map = new EntityMap(2);
        $m = m::mock(User::class);
        $m->shouldReceive('primaryKey')->andReturn(1);

        $map->remember($m);

        $m = m::mock(User::class);
        $m->shouldReceive('primaryKey')->andReturn(2);

        $map->remember($m);

        $m = m::mock(User::class);
        $m->shouldReceive('primaryKey')->andReturn(3);

        $map->remember($m, true);
    }

}