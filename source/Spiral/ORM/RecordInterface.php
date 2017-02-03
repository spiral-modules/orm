<?php
/**
 * Spiral, Core Components
 *
 * @author Wolfy-J
 */

namespace Spiral\ORM;

use Spiral\Models\EntityInterface;
use Spiral\ORM\Exceptions\RecordException;
use Spiral\ORM\Exceptions\RelationException;

interface RecordInterface extends EntityInterface
{
    /**
     * Can be null.
     *
     * @return mixed
     */
    public function primaryKey();

    /**
     * Pack entity data into array form, no accessors is allowed. Not typed strictly to be
     * compatible with AccessorInterface.
     *
     * @return array
     */
    public function packValue();

    /**
     * {@inheritdoc}
     *
     * @param bool $queueRelations
     *
     * @throws RecordException
     * @throws RelationException
     */
    public function queueStore(bool $queueRelations = true): ContextualCommandInterface;

    /**
     * {@inheritdoc}
     *
     * @throws RecordException
     * @throws RelationException
     */
    public function queueDelete(): CommandInterface;
}