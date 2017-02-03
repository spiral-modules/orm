<?php
/**
 * Spiral, Core Components
 *
 * @author Wolfy-J
 */

namespace Spiral\ORM\Entities;

use Spiral\Core\Component;
use Spiral\Core\Traits\SaturateTrait;
use Spiral\Models\EntityInterface;
use Spiral\ORM\Exceptions\SourceException;
use Spiral\ORM\ORMInterface;
use Spiral\ORM\Record;

/**
 * Source class associated to one ORM model. Source can be used to write your own custom find
 * method or change default selection.
 */
class RecordSource extends Component implements \Countable, \IteratorAggregate
{
    use SaturateTrait;

    /**
     * Linked record model. ORM can automatically index and link user sources to models based on
     * value of this constant.
     *
     * Use this constant in custom source implementation in order to automatically link it to
     * appropriate model AND be able to create source as constructor or method injection without
     * ORM dependency.
     */
    const RECORD = null;

    /**
     * @var RecordSelector
     */
    private $selector;

    /**
     * Associated document class.
     *
     * @var string
     */
    private $class = null;

    /**
     * @invisible
     *
     * @var ORMInterface
     */
    protected $orm = null;

    /**
     * @param string       $class
     * @param ORMInterface $orm
     *
     * @throws SourceException
     */
    public function __construct(string $class = null, ORMInterface $orm = null)
    {
        if (empty($class)) {
            if (empty(static::RECORD)) {
                throw new SourceException('Unable to create source without associated class');
            }

            $class = static::RECORD;
        }

        $this->class = $class;
        $this->orm = $this->saturate($orm, ORMInterface::class);
    }

    /**
     * Associated class.
     *
     * @return string
     */
    public function getClass(): string
    {
        return $this->class;
    }

    /**
     * Associated ORM manager.
     *
     * @return ORMInterface
     */
    public function getORM(): ORMInterface
    {
        return $this->orm;
    }

    /**
     * Create new DocumentEntity based on set of provided fields.
     *
     * @final Change static method of entity, not this one.
     *
     * @param array  $fields
     * @param string $class  Due ODM models can be inherited you can use this argument to specify
     *                       custom model class.
     *
     * @return EntityInterface|Record
     */
    public function create($fields = [], string $class = null): EntityInterface
    {
        //Create model with filtered set of fields
        return $this->orm->make($class ?? $this->class, $fields, ORMInterface::STATE_NEW);
    }

    /**
     * Find document by it's primary key.
     *
     * @see findOne()
     *
     * @param string|int $id   Primary key value.
     * @param array      $load Relations to pre-load.
     *
     * @return EntityInterface|Record|null
     */
    public function findByPK($id, array $load = [])
    {
        return $this->getSelector()->wherePK($id)->load($load)->findOne();
    }

    /**
     * Select one document from mongo collection.
     *
     * @param array $query  Fields and conditions to query by.
     * @param array $sortBy Always specify sort by to ensure that results are stable.
     * @param array $load   Relations to pre-load.
     *
     * @return EntityInterface|Record|null
     */
    public function findOne(array $query = [], array $sortBy = [], array $load = [])
    {
        return $this->getSelector()->orderBy($sortBy)->load($load)->findOne($query);
    }

    /**
     * Get associated document selection with pre-configured query (if any).
     *
     * @param array $query
     *
     * @return RecordSelector
     */
    public function find(array $query = []): RecordSelector
    {
        return $this->getSelector()->where($query);
    }

    /**
     * @param array  $query
     * @param string $column Column to count by, PK or * by default.
     *
     * @return int
     */
    public function count(array $query = [], string $column = null): int
    {
        return $this->getSelector()->where($query)->count();
    }

    /**
     * @return RecordSelector
     */
    public function getIterator(): RecordSelector
    {
        return $this->getSelector();
    }

    /**
     * Create source with new associated selector.
     *
     * @param RecordSelector $selector
     *
     * @return RecordSource
     */
    public function withSelector(RecordSelector $selector): RecordSource
    {
        $source = clone $this;
        $source->setSelector($selector);

        return $source;
    }

    /**
     * Set initial selector.
     *
     * @param RecordSelector $selector
     */
    protected function setSelector(RecordSelector $selector)
    {
        $this->selector = clone $selector;
    }

    /**
     * Get associated selector.
     *
     * @return RecordSelector
     */
    protected function getSelector(): RecordSelector
    {
        if (empty($this->selector)) {
            //Requesting selector on demand
            $this->selector = $this->orm->selector($this->class);
        }

        return clone $this->selector;
    }

    /**
     * {@inheritdoc}
     */
    protected function iocContainer()
    {
        if ($this->orm instanceof Component) {
            //Always work in ODM scope
            return $this->orm->iocContainer();
        }

        return parent::iocContainer();
    }
}