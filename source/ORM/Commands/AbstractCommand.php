<?php
/**
 * Spiral, Core Components
 *
 * @author Wolfy-J
 */

namespace Spiral\ORM\Commands;

use Spiral\ORM\CommandInterface;

/**
 * Provides support for command events.
 */
abstract class AbstractCommand implements CommandInterface
{
    /**
     * @var \Closure[]
     */
    private $onExecute = [];

    /**
     * @var \Closure[]
     */
    private $onComplete = [];

    /**
     * @var \Closure[]
     */
    private $onRollBack = [];

    /**
     * Closure to be called after command executing.
     *
     * @param \Closure $closure
     */
    final public function onExecute(\Closure $closure)
    {
        $this->onExecute[] = $closure;
    }

    /**
     * To be called after parent transaction been commited.
     *
     * @param \Closure $closure
     */
    final public function onComplete(\Closure $closure)
    {
        $this->onComplete[] = $closure;
    }

    /**
     * To be called after parent transaction been rolled back.
     *
     * @param \Closure $closure
     */
    final public function onRollBack(\Closure $closure)
    {
        $this->onRollBack[] = $closure;
    }

    /**
     * {@inheritdoc}
     */
    public function execute()
    {
        foreach ($this->onExecute as $closure) {
            call_user_func($closure, $this);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function complete()
    {
        foreach ($this->onComplete as $closure) {
            call_user_func($closure, $this);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function rollBack()
    {
        foreach ($this->onRollBack as $closure) {
            call_user_func($closure, $this);
        }
    }
}