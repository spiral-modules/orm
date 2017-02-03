<?php
/**
 * Spiral, Core Components
 *
 * @author Wolfy-J
 */

namespace Spiral\ORM\Commands\Traits;

trait PrimaryTrait
{
    /**
     * Primary key value (from previous command), promised on execution!.
     *
     * @var mixed
     */
    private $primaryKey;

    /**
     * Promised on execute.
     *
     * @return mixed|null
     */
    public function primaryKey()
    {
        return $this->primaryKey;
    }

    /**
     * @param mixed $primaryKey
     */
    public function setPrimaryKey($primaryKey)
    {
        $this->primaryKey = $primaryKey;
    }
}