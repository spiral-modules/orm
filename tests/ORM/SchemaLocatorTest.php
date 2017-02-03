<?php
/**
 * Spiral, Core Components
 *
 * @author Wolfy-J
 */
namespace Spiral\Tests\ORM;

use Mockery as m;
use Spiral\Core\Container;
use Spiral\ORM\Configs\MutatorsConfig;
use Spiral\ORM\RecordEntity;
use Spiral\ORM\Schemas\SchemaLocator;
use Spiral\ORM\Schemas\RecordSchema;
use Spiral\Tests\ORM\Fixtures\Post;
use Spiral\Tests\ORM\Fixtures\User;
use Spiral\Tests\ORM\Fixtures\UserSource;
use Spiral\Tests\ORM\Traits\ORMTrait;
use Spiral\Tokenizer\ClassesInterface;

class SchemaLocatorTest extends \PHPUnit_Framework_TestCase
{
    use ORMTrait;

    public function testLocateDocuments()
    {
        $classes = m::mock(ClassesInterface::class);
        $config = new MutatorsConfig([]);

        $container = new Container();
        $container->bind(ClassesInterface::class, $classes);
        $container->bind(MutatorsConfig::class, $config);

        $locator = new SchemaLocator($container);

        $classes->shouldReceive('getClasses', [RecordEntity::class])->andReturn([
            User::class => ['name' => User::class, 'filename' => '~', 'abstract' => false],
            Post::class => ['name' => Post::class, 'filename' => '~', 'abstract' => true]
        ]);

        $result = $locator->locateSchemas();
        $this->assertCount(1, $result);

        /**
         * @var RecordSchema $schema
         */
        $schema = $result[0];
        $this->assertInstanceOf(RecordSchema::class, $schema);
        $this->assertSame(User::class, $schema->getClass());
    }

    public function testLocateSources()
    {
        $classes = m::mock(ClassesInterface::class);
        $config = new MutatorsConfig([]);

        $container = new Container();
        $container->bind(ClassesInterface::class, $classes);
        $container->bind(MutatorsConfig::class, $config);

        $locator = new SchemaLocator($container);

        $classes->shouldReceive('getClasses', [RecordEntity::class])->andReturn([
            UserSource::class => [
                'name'     => UserSource::class,
                'filename' => '~',
                'abstract' => false
            ],
            Post::class       => ['name' => Post::class, 'filename' => '~', 'abstract' => true]
        ]);

        $result = $locator->locateSources();

        $this->assertSame([
            User::class => UserSource::class
        ], $result);
    }
}