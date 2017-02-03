<?php
/**
 * Spiral, Core Components
 *
 * @author Wolfy-J
 */

namespace Spiral\ORM;

use Spiral\Database\Entities\Driver;

/**
 * Declares that command must be executed inside SQL transaction scope.
 */
interface SQLCommandInterface extends CommandInterface
{
    /**
     * Must return associated command driver. If any.
     *
     * @return Driver|null
     */
    public function getDriver();
}