<?php
/**
 * Spiral, Core Components
 *
 * @author Wolfy-J
 */

namespace Spiral\ORM\Entities;

use Spiral\ORM\ORMInterface;

/**
 * Instantiates array of entities. At this moment implementation is rather simple.
 */
class RecordIterator implements \IteratorAggregate
{
    /**
     * Array of entity data to be fed into instantiators.
     *
     * @var array
     */
    private $data = [];

    /**
     * Class to be instantiated.
     *
     * @var string
     */
    private $class;

    /**
     * Responsible for entity construction.
     *
     * @invisible
     * @var ORMInterface
     */
    private $orm;

    /**
     * @param array        $data
     * @param string       $class
     * @param ORMInterface $orm
     */
    public function __construct(array $data, string $class, ORMInterface $orm)
    {
        $this->data = $data;
        $this->class = $class;
        $this->orm = $orm;
    }

    /**
     * Generate over data.
     * Method will use pibot
     *
     * @return \Generator
     */
    public function getIterator(): \Generator
    {
        foreach ($this->data as $index => $data) {
            if (isset($data[ORMInterface::PIVOT_DATA])) {
                /*
                 * When pivot data is provided we are able to use it as array key.
                 */
                $index = $data[ORMInterface::PIVOT_DATA];
                unset($data[ORMInterface::PIVOT_DATA]);
            }

            yield $index => $this->orm->make(
                $this->class,
                $data,
                ORMInterface::STATE_LOADED,
                true
            );
        }
    }
}