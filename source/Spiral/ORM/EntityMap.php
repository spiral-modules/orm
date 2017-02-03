<?php
/**
 * Spiral, Core Components
 *
 * @author Wolfy-J
 */

namespace Spiral\ORM;

use Spiral\Models\EntityInterface;
use Spiral\ORM\Exceptions\MapException;

/**
 * Entity cache provides ability to access already retrieved entities from memory instead of
 * calling database. Attention, cache WILL BE isolated in a selection scope in order to prevent
 * data collision, ie:
 *
 * $user1 => $users->findOne();
 * $user2 => $users->findOne();
 *
 * //Will work ONLY when both selections in a same scope, or it's same selector
 * assert($user1 !== $user2);
 */
final class EntityMap
{
    /**
     * @var EntityInterface[]
     */
    private $entities = [];

    /**
     * Maximum entity cache size. Null is unlimited.
     *
     * @var int|null
     */
    private $maxSize = null;

    /**
     * @param int|null $maxSize Set to null to make cache size unlimited.
     */
    public function __construct(int $maxSize = null)
    {
        $this->maxSize = $maxSize;
    }

    /**
     * Add Record to entity cache. Primary key value will be used as
     * identifier.
     *
     * Attention, existed entity will be replaced!
     *
     * @param RecordInterface $entity
     * @param bool            $ignoreLimit Cache overflow will be ignored.
     *
     * @return RecordInterface Returns given entity.
     *
     * @throws MapException When cache size exceeded.
     */
    public function remember(RecordInterface $entity, bool $ignoreLimit = false): RecordInterface
    {
        if (!$ignoreLimit && !is_null($this->maxSize) && count($this->entities) > $this->maxSize - 1) {
            throw new MapException('Entity cache size exceeded');
        }

        if (empty($entity->primaryKey())) {
            throw new MapException("Unable to store non identified entity " . get_class($entity));
        }

        $cacheID = get_class($entity) . ':' . $entity->primaryKey();

        return $this->entities[$cacheID] = $entity;
    }

    /**
     * Remove entity record from entity cache. Primary key value will be used as identifier.
     *
     * @param RecordInterface $entity
     */
    public function forget(RecordInterface $entity)
    {
        $cacheID = get_class($entity) . ':' . $entity->primaryKey();
        unset($this->entities[$cacheID]);
    }

    /**
     * Check if desired entity was already cached.
     *
     * @param string $class
     * @param string $identity
     *
     * @return bool
     */
    public function has(string $class, string $identity): bool
    {
        return isset($this->entities["{$class}:{$identity}"]);
    }

    /**
     * Fetch entity from cache.
     *
     * @param string $class
     * @param string $identity
     *
     * @return null|mixed
     */
    public function get(string $class, string $identity)
    {
        if (!$this->has($class, $identity)) {
            return null;
        }

        return $this->entities["{$class}:{$identity}"];
    }

    /**
     * Flush content of entity cache.
     */
    public function flush()
    {
        $this->entities = [];
    }

    /**
     * Destructing.
     */
    public function __destruct()
    {
        $this->flush();
    }
}
