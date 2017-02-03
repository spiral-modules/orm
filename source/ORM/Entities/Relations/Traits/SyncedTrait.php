<?php
/**
 * components
 *
 * @author    Wolfy-J
 */

namespace Spiral\ORM\Entities\Relations\Traits;

use Spiral\ORM\Record;
use Spiral\ORM\RecordInterface;

/**
 * Checks if two records are synced (only for simple relations)
 */
trait SyncedTrait
{
    /**
     * If record not synced or can't be synced. Only work for PK based relations.
     *
     * @param RecordInterface $inner
     * @param RecordInterface $outer
     *
     * @return bool
     */
    protected function isSynced(RecordInterface $inner, RecordInterface $outer): bool
    {
        if (empty($outer->primaryKey())) {
            //Parent not saved
            return false;
        }

        //Comparing FK values
        return $outer->getField(
                $this->key(Record::OUTER_KEY)
            ) == $inner->getField(
                $this->key(Record::INNER_KEY)
            );
    }

    /**
     * Key name.
     *
     * @param int $key
     *
     * @return string|null
     */
    abstract protected function key(int $key);
}