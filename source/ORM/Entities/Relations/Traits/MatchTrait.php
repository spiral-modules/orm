<?php
/**
 * Spiral, Core Components
 *
 * @author Wolfy-J
 */

namespace Spiral\ORM\Entities\Relations\Traits;

use Spiral\ORM\RecordInterface;

/**
 * Provides ability to compare entity and query.
 */
trait MatchTrait
{
    /**
     * Match entity by field intersection, instance values or primary key.
     *
     * @param RecordInterface             $record
     * @param RecordInterface|array|mixed $query
     *
     * @return bool
     */
    protected function match(RecordInterface $record, $query): bool
    {
        if ($record === $query) {
            //Strict search
            return true;
        }

        //Primary key comparision
        if (is_scalar($query) && $record->primaryKey() == $query) {
            return true;
        }

        //Record based comparision
        if ($query instanceof RecordInterface) {
            //Matched by PK (assuming both same class)
            if (!empty($query->primaryKey()) && $query->primaryKey() == $record->primaryKey()) {
                return true;
            }

            //Matched by content (this is a bit tricky!)
            if ($record->packValue() == $query->packValue()) {
                return true;
            }

            return false;
        }

        //Field intersection
        if (is_array($query) && array_intersect_assoc($record->packValue(), $query) == $query) {
            return true;
        }

        return false;
    }
}