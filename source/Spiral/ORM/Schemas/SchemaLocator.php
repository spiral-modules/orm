<?php
/**
 * Spiral, Core Components
 *
 * @author Wolfy-J
 */

namespace Spiral\ORM\Schemas;

use Interop\Container\ContainerInterface;
use Spiral\Core\FactoryInterface;
use Spiral\Models\Reflections\ReflectionEntity;
use Spiral\ORM\Entities\RecordSource;
use Spiral\ORM\RecordEntity;
use Spiral\Tokenizer\ClassesInterface;

/**
 * Provides ability to automatically locate schemas in a project. Can be user redefined in order to
 * automatically include custom classes.
 *
 * This is lazy implementation.
 */
class SchemaLocator implements LocatorInterface
{
    /**
     * Container is used for lazy resolution for ClassesInterface.
     *
     * @var ContainerInterface
     */
    protected $container;

    /**
     * @param ContainerInterface $container
     */
    public function __construct(ContainerInterface $container)
    {
        $this->container = $container;
    }

    /**
     * Locate all available document schemas in a project.
     *
     * @return SchemaInterface[]
     */
    public function locateSchemas(): array
    {
        if (!$this->container->has(ClassesInterface::class)) {
            return [];
        }

        /**
         * @var ClassesInterface $classes
         */
        $classes = $this->container->get(ClassesInterface::class);

        $schemas = [];
        foreach ($classes->getClasses(RecordEntity::class) as $class) {
            if ($class['abstract']) {
                continue;
            }

            $schemas[] = $this->container->get(FactoryInterface::class)->make(
                RecordSchema::class,
                ['reflection' => new ReflectionEntity($class['name']),]
            );
        }

        return $schemas;
    }

    /**
     * Locate all DocumentSources defined by user. Must return values in a form of
     * Document::class => Source::class.
     *
     * @return array
     */
    public function locateSources(): array
    {
        if (!$this->container->has(ClassesInterface::class)) {
            return [];
        }

        /**
         * @var ClassesInterface $classes
         */
        $classes = $this->container->get(ClassesInterface::class);

        $result = [];
        foreach ($classes->getClasses(RecordSource::class) as $class) {
            $source = $class['name'];
            if ($class['abstract'] || empty($source::RECORD)) {
                continue;
            }

            $result[$source::RECORD] = $source;
        }

        return $result;
    }
}