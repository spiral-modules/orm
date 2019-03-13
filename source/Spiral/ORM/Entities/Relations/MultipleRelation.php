<?php
/**
 * Spiral, Core Components
 *
 * @author Wolfy-J
 */

namespace Spiral\ORM\Entities\Relations;

use Spiral\ORM\Entities\RecordIterator;
use Spiral\ORM\Entities\Relations\Traits\MatchTrait;
use Spiral\ORM\Entities\Relations\Traits\PartialTrait;
use Spiral\ORM\RecordInterface;
use Spiral\ORM\RelationInterface;

/**
 * Relation with multiple related instances.
 */
abstract class MultipleRelation extends AbstractRelation
{
    use MatchTrait, PartialTrait;

    /**
     * Loaded list of records. SplObjectStorage?
     *
     * @var RecordInterface[]
     */
    protected $instances = [];

    /**
     * {@inheritdoc}
     *
     * We have to init relations right after initialization, this might be optimized in a future.
     */
    public function withContext(
        RecordInterface $parent,
        bool $loaded = false,
        array $data = null
    ): RelationInterface {
        $relation = parent::withContext($parent, $loaded, $data);

        /** @var self $relation */
        return $relation->initInstances();
    }

    /**
     * {@inheritdoc}
     */
    public function hasRelated(): bool
    {
        if (!$this->isLoaded()) {
            //Lazy loading our relation data
            $this->loadData(true);
        }

        return !empty($this->instances);
    }

    /**
     * Such relations will represent themselves.
     *
     * @return $this
     */
    public function getRelated()
    {
        return $this;
    }

    /**
     * Iterate over instance set.
     *
     * @return \ArrayIterator
     */
    public function getIterator()
    {
        return new \ArrayIterator($this->loadData(true)->instances);
    }

    /**
     * @return int
     */
    public function count()
    {
        return count($this->loadData(true)->instances);
    }

    /**
     * Method will autoload data.
     *
     * @param array|RecordInterface|mixed $query Fields, entity or PK.
     *
     * @return bool
     */
    public function has($query): bool
    {
        return !empty($this->matchOne($query));
    }

    /**
     * Fine one entity for a given query or return null. Method will autoload data.
     *
     * Example: ->matchOne(['value' => 'something', ...]);
     *
     * @param array|RecordInterface|mixed $query Fields, entity or PK.
     *
     * @return RecordInterface|null
     */
    public function matchOne($query)
    {
        foreach ($this->loadData(true)->instances as $instance) {
            if ($this->match($instance, $query)) {
                return $instance;
            }
        }

        return null;
    }

    /**
     * Return only instances matched given query, performed in memory! Only simple conditions are
     * allowed. Not "find" due trademark violation. Method will autoload data.
     *
     * Example: ->matchMultiple(['value' => 'something', ...]);
     *
     * @param array|RecordInterface|mixed $query Fields, entity or PK.
     *
     * @return \ArrayIterator
     */
    public function matchMultiple($query)
    {
        $result = [];
        foreach ($this->loadData(true)->instances as $instance) {
            if ($this->match($instance, $query)) {
                $result[] = $instance;
            }
        }

        return new \ArrayIterator($result);
    }

    /**
     * {@inheritdoc}
     *
     * @return self
     *
     * @throws \Spiral\ORM\Exceptions\SelectorException
     * @throws \Spiral\Database\Exceptions\QueryException (needs wrapping)
     */
    protected function loadData(bool $autoload = true): self
    {
        if ($this->loaded) {
            return $this;
        }

        $this->loaded = true;

        if (empty($this->data) || !is_array($this->data)) {
            if ($this->autoload && $autoload) {
                //Only for non partial selections (excluded already selected)
                $this->data = $this->loadRelated();
            } else {
                $this->data = [];
            }
        }

        return $this->initInstances();
    }

    /**
     * Init pre-loaded data.
     *
     * @return HasManyRelation|self
     */
    protected function initInstances(): self
    {
        if (is_array($this->data) && !empty($this->data)) {
            //Iterates and instantiate records
            $iterator = new RecordIterator($this->data, $this->class, $this->orm);

            foreach ($iterator as $item) {
                if (in_array($item, $this->instances, true)) {
                    //Skip duplicates
                    continue;
                }

                $this->instances[] = $item;
            }
        }

        //Memory free
        $this->data = null;

        return $this;
    }

    /**
     * Fetch relation data from database.
     *
     * @return array
     */
    abstract protected function loadRelated(): array;
}
