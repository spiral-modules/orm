<?php
/**
 * Spiral, Core Components
 *
 * @author Wolfy-J
 */
namespace Spiral\Tests\ORM;

use Spiral\ORM\Schemas\NullLocator;

class NullLocatorTest extends \PHPUnit_Framework_TestCase
{
    public function testLocator()
    {
        $locator = new NullLocator();

        $this->assertSame([], $locator->locateSchemas());
        $this->assertSame([], $locator->locateSources());
    }
}