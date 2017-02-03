<?php
/**
 * Spiral, Core Components
 *
 * @author Wolfy-J
 */

namespace Spiral\ORM\Entities;

use Spiral\ORM\Exceptions\InstantionException;
use Spiral\ORM\InstantiatorInterface;
use Spiral\ORM\ORMInterface;
use Spiral\ORM\RecordInterface;

/**
 * Default instantiator for records.
 */
class RecordInstantiator implements InstantiatorInterface
{
    /**
     * @invisible
     * @var ORMInterface
     */
    private $orm;

    /**
     * Record class.
     *
     * @var string
     */
    private $class = '';

    /**
     * @param ORMInterface $orm
     * @param string       $class
     */
    public function __construct(ORMInterface $orm, string $class)
    {
        $this->orm = $orm;
        $this->class = $class;
    }

    /**
     * {@inheritdoc}
     *
     * @return RecordInterface
     *
     * @throws InstantionException
     */
    public function make(array $fields, int $state): RecordInterface
    {
        $class = $this->class;

        //Now we can construct needed class, in this case we are following DocumentEntity declaration
        if ($state == ORMInterface::STATE_LOADED) {
            //No need to filter values, passing directly in constructor
            return new $class($fields, $state, $this->orm);
        }

        if ($state != ORMInterface::STATE_NEW) {
            throw new InstantionException(
                "Undefined state {$state}, only NEW and LOADED are supported"
            );
        }

        /*
         * Filtering entity
         */

        $entity = new $class([], $state, $this->orm);
        if (!$entity instanceof RecordInterface) {
            throw new InstantionException(
                "Unable to set filtered values for '{$class}', must be instance of RecordInterface"
            );
        }

        //Must pass value thought all needed filters
        $entity->setFields($fields);

        return $entity;
    }
}
