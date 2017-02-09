<?php
/**
 * Spiral, Core Components
 *
 * @author Wolfy-J
 */

namespace Spiral\Tests\ORM;

use Interop\Container\ContainerInterface;
use Spiral\Core\Container;
use Spiral\Database\Configs\DatabasesConfig;
use Spiral\Database\DatabaseManager;
use Spiral\Database\Entities\Database;
use Spiral\Database\Entities\Driver;
use Spiral\Database\Helpers\SynchronizationPool;
use Spiral\ORM\Configs\MutatorsConfig;
use Spiral\ORM\Configs\RelationsConfig;
use Spiral\ORM\EntityMap;
use Spiral\ORM\ORM;
use Spiral\ORM\ORMInterface;
use Spiral\ORM\RecordInterface;
use Spiral\ORM\Schemas\SchemaBuilder;
use Spiral\Tests\Core\Fixtures\SharedComponent;
use Spiral\Tests\ORM\Fixtures\BaseRecord;
use Spiral\Tests\ORM\Fixtures\Comment;
use Spiral\Tests\ORM\Fixtures\Node;
use Spiral\Tests\ORM\Fixtures\Post;
use Spiral\Tests\ORM\Fixtures\Profile;
use Spiral\Tests\ORM\Fixtures\Recursive;
use Spiral\Tests\ORM\Fixtures\Tag;
use Spiral\Tests\ORM\Fixtures\User;
use Spiral\Tests\ORM\Fixtures\UserSource;
use Spiral\Tests\ORM\Traits\ORMTrait;

abstract class BaseTest extends \PHPUnit_Framework_TestCase
{
    use ORMTrait;

    const PROFILING = ENABLE_PROFILING;

    const MODELS = [
        User::class,
        Post::class,
        Comment::class,
        Tag::class,
        Profile::class,
        Recursive::class,
        Node::class,
    ];

    const SOURCES = [User::class => UserSource::class];

    /**
     * @var DatabaseManager
     */
    protected $dbal;

    /**
     * @var SchemaBuilder
     */
    protected $builder;

    /**
     * @var ORM
     */
    protected $orm;

    /**
     * @var Database
     */
    protected $db;

    /**
     * @var ContainerInterface
     */
    protected $container;

    public function setUp()
    {
        $this->container = $container = new Container();
        $this->dbal = $this->databaseManager($this->container);
        $this->builder = $this->makeBuilder($this->dbal);

        $this->orm = new ORM(
            $this->dbal,
            $this->relationsConfig(),
            null,
            null,
            null,
            $container
        );

        //lazy loading
        $container->bind(RelationsConfig::class, $this->relationsConfig());
        $container->bind(MutatorsConfig::class, $this->mutatorsConfig());

        foreach (static::MODELS as $model) {
            $this->builder->addSchema($this->makeSchema($model));
        }

        foreach (static::SOURCES as $model => $source) {
            $this->builder->addSource($model, $source);
        }

        $this->db->getDriver()->setProfiling(false);
        $this->builder->renderSchema();
        $this->builder->pushSchema();
        $this->db->getDriver()->setProfiling(true);

        $this->orm->setSchema($this->builder);

        SharedComponent::shareContainer($container);

        //Make all tests with map
        $this->orm = $this->orm->withMap(new EntityMap());
        $container->bind(ORMInterface::class, $this->orm);
    }

    public function tearDown()
    {
        $this->orm->getMap()->flush();

        SharedComponent::shareContainer(null);

        $schemas = [];
        //Clean up
        $this->db->getDriver()->setProfiling(false);
        foreach ($this->dbal->database()->getTables() as $table) {
            $schema = $table->getSchema();
            $schema->declareDropped();
            $schemas[] = $schema;
        }

        //Clear all tables
        $syncBus = new SynchronizationPool($schemas);
        $syncBus->run();

        $this->db->getDriver()->setProfiling(true);
    }

    /**
     * Default SQLite database.
     *
     * @param ContainerInterface $container
     *
     * @return DatabaseManager
     */
    protected function databaseManager(ContainerInterface $container): DatabaseManager
    {
        $dbal = new DatabaseManager(
            $this->dbConfig = new DatabasesConfig([
                'default'     => 'default',
                'aliases'     => [
                    'other' => 'default'
                ],
                'databases'   => [],
                'connections' => []
            ]),
            $container
        );

        $dbal->addDatabase(
            $this->db = new Database($this->getDriver($container), 'default', 'tests_')
        );

        $dbal->addDatabase(new Database($this->getDriver($container), 'slave', 'slave_'));

        return $dbal;
    }

    protected function assertSameInDB(BaseRecord $record)
    {
        $this->assertTrue($record->isLoaded());
        $this->assertNotEmpty($record->primaryKey());

        $fromDB = $this->orm->source(get_class($record))->findByPK($record->primaryKey());
        $this->assertInstanceOf(get_class($record), $fromDB);

        $this->assertEquals(
            $record->getFields(),
            $fromDB->getFields()
        );
    }

    protected function assertSameRecord(RecordInterface $entity, RecordInterface $entityB)
    {
        $this->assertTrue(empty(array_diff($entity->getFields(), $entityB->getFields())));
    }

    /**
     * Database driver.
     *
     * @return Driver
     */
    abstract function getDriver(ContainerInterface $container = null): Driver;
}