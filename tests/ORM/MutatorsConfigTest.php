<?php
/**
 * Spiral, Core Components
 *
 * @author Wolfy-J
 */
namespace Spiral\Tests\ORM;

use Spiral\ORM\Configs\MutatorsConfig;

class MutatorsConfigTest extends \PHPUnit_Framework_TestCase
{
    public function testGetMutators()
    {
        $config = new MutatorsConfig([
            'mutators' => [
                'string' => ['setter' => 'strval']
            ]
        ]);

        $this->assertSame(['setter' => 'strval'], $config->getMutators('string'));
        $this->assertSame([], $config->getMutators('int'));
    }

    public function testAliases()
    {
        $config = new MutatorsConfig([
            'aliases'  => [
                'str' => 'string'
            ],
            'mutators' => [
                'string' => ['setter' => 'strval']
            ]
        ]);

        $this->assertSame($config->getMutators('str'), $config->getMutators('string'));
    }
}