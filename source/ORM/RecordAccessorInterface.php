<?php
/**
 * Spiral Framework.
 *
 * @license   MIT
 * @author    Anton Titov (Wolfy-J)
 */

namespace Spiral\ORM;

use Spiral\Database\Injections\FragmentInterface;
use Spiral\Models\AccessorInterface;

/**
 * Declares requirement for every ORM field accessor to control it's updates and state.
 */
interface RecordAccessorInterface extends AccessorInterface
{
    /**
     * Check if object has any update.
     *
     * @return bool
     */
    public function hasChanges(): bool;

    /**
     * Indicate that all updates done, reset dirty state.
     */
    public function flushChanges();

    /**
     * Create update value or statement to be used in DBAL update builder. May return SQLFragments
     * and expressions.
     *
     * @param string $field Name of field where accessor associated to.
     *
     * @return mixed|FragmentInterface
     */
    public function compileUpdates(string $field = '');
}