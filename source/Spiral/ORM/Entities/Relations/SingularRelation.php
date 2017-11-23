<?php
/**
 * Spiral, Core Components
 *
 * @author Wolfy-J
 */

namespace Spiral\ORM\Entities\Relations;

use Spiral\Database\Exceptions\QueryException;
use Spiral\ORM\Entities\Relations\Traits\SyncedTrait;
use Spiral\ORM\Exceptions\SelectorException;
use Spiral\ORM\Helpers\AliasDecorator;
use Spiral\ORM\ORMInterface;
use Spiral\ORM\Record;
use Spiral\ORM\RecordInterface;

/**
 * Provides ability to create cached instances of related data.
 */
abstract class SingularRelation extends AbstractRelation
{
    use SyncedTrait;

    /**
     * Create placeholder model when relation is empty.
     */
    const CREATE_PLACEHOLDER = false;

    /**
     * @var RecordInterface
     */
    protected $instance;

    /**
     * {@inheritdoc}
     */
    public function hasRelated(): bool
    {
        if (!$this->isLoaded()) {
            //Lazy loading our relation data
            $this->loadData();
        }

        return !empty($this->instance);
    }

    /**
     * {@inheritdoc}
     *
     * Returns associated parent or NULL if none associated.
     */
    public function getRelated()
    {
        if ($this->instance instanceof RecordInterface) {
            return $this->instance;
        }

        if (!$this->isLoaded()) {
            //Lazy loading our relation data
            $this->loadData();
        }

        if (!empty($this->instance)) {
            return $this->instance;
        }

        if (!empty($this->data)) {
            $this->instance = $this->orm->make(
                $this->getClass(),
                $this->data,
                ORMInterface::STATE_LOADED,
                true
            );
        } elseif ($this->isPlaceholderNeeded()) {
            //Stub instance
            $this->instance = $this->orm->make(
                $this->getClass(),
                [],
                ORMInterface::STATE_NEW
            );
        }

        return $this->instance;
    }

    /**
     * {@inheritdoc}
     *
     * @throws SelectorException
     * @throws QueryException
     */
    protected function loadData()
    {
        $this->loaded = true;

        $innerKey = $this->key(Record::INNER_KEY);
        if (empty($this->parent->getField($innerKey))) {
            //Unable to load
            return;
        }

        $selector = $this->orm->selector($this->getClass());
        $decorator = new AliasDecorator($selector, 'where', $selector->getAlias());
        $decorator->where($this->whereStatement());

        $this->data = $selector->fetchData();

        if (!empty($this->data[0])) {
            //Use first result
            $this->data = $this->data[0];
        }
    }

    /**
     * Where statement to load outer record.
     *
     * @return array
     */
    protected function whereStatement(): array
    {
        return [
            $this->key(Record::OUTER_KEY) => $this->parent->getField($this->key(Record::INNER_KEY))
        ];
    }

    /**
     * @return bool
     */
    protected function isPlaceholderNeeded(): bool
    {
        return static::CREATE_PLACEHOLDER;
    }
}