<?php
/**
 * Spiral, Core Components
 *
 * @author Wolfy-J
 */

namespace Spiral\ORM;

use Spiral\ORM\Exceptions\InstantionException;

/**
 * Instantiates ORM entities.
 */
interface InstantiatorInterface
{
    /**
     * Method must detect and construct appropriate class instance based on a given fields. When
     * state set to NEW values MUST be filtered/typecasted before appearing in entity!
     *
     * @param array $fields
     * @param int   $state
     *
     * @return RecordInterface
     *
     * @throws InstantionException
     */
    public function make(array $fields, int $state): RecordInterface;
}