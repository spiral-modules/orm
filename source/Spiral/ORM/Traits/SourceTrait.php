<?php
/**
 * Spiral Framework.
 *
 * @license   MIT
 * @author    Anton Titov (Wolfy-J)
 */

namespace Spiral\ORM\Traits;

use Interop\Container\ContainerInterface;
use Spiral\Core\Component;
use Spiral\Core\Exceptions\ScopeException;
use Spiral\ORM\Entities\RecordSelector;
use Spiral\ORM\Entities\RecordSource;
use Spiral\ORM\ORMInterface;

/**
 * Static record functionality including create and find methods.
 */
trait SourceTrait
{
    /**
     * Get record selector for a given query.
     *
     * Example:
     * User::find(['status' => 'active'];
     *
     * @param array $query Selection WHERE statement.
     *
     * @return RecordSelector
     *
     * @throws ScopeException
     */
    public static function find(array $query = []): RecordSelector
    {
        return static::source()->find($query);
    }

    /**
     * Fetch one record based on provided query or return null. Make sure to specify sort by in
     * order to stabilize selection
     *
     * Example:
     * User::findOne(['name' => 'Wolfy-J'], ['id' => 'DESC'], ['profile']);
     *
     *
     * @param array $where  Selection WHERE statement.
     * @param array $sortBy Sort by.
     * @param array $load   Relations to pre-load.
     *
     * @return \Spiral\ORM\RecordEntity|null     *
     * @throws ScopeException
     */
    public static function findOne($where = [], array $sortBy = [], array $load = [])
    {
        return static::source()->findOne($where, $sortBy, $load);
    }

    /**
     * Find record using it's primary key and load children.
     *
     * Example:
     * User::findByPK(1, ['profile']);
     *
     * @param mixed $primaryKey Primary key.
     * @param array $load       Relations to pre-load.
     *
     * @return \Spiral\ORM\RecordEntity|null
     *
     * @throws ScopeException
     */
    public static function findByPK($primaryKey, array $load = [])
    {
        return static::source()->findByPK($primaryKey, $load);
    }

    /**
     * Instance of RecordSource associated with specific model.
     *
     * @see   Component::staticContainer()
     **
     * @return RecordSource
     *
     * @throws ScopeException
     */
    public static function source(): RecordSource
    {
        /**
         * Container to be received via global scope.
         *
         * @var ContainerInterface $container
         */
        //Via global scope
        $container = self::staticContainer();

        if (empty($container)) {
            //Via global scope
            throw new ScopeException(sprintf(
                "Unable to get '%s' source, no container scope is available",
                static::class
            ));
        }

        return $container->get(ORMInterface::class)->source(static::class);
    }

    /**
     * Trait can ONLY be added to components.
     *
     * @see Component
     *
     * @param ContainerInterface|null $container
     *
     * @return ContainerInterface|null
     */
    abstract protected static function staticContainer(ContainerInterface $container = null);
}