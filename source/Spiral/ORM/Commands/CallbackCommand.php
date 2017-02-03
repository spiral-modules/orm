<?php
/**
 * Spiral, Core Components
 *
 * @author Wolfy-J
 */

namespace Spiral\ORM\Commands;

class CallbackCommand extends AbstractCommand
{
    /**
     * @var \Closure
     */
    private $callback;

    /**
     * @param \Closure $callback
     */
    public function __construct(\Closure $callback)
    {
        $this->callback = $callback;
    }

    /**
     * {@inheritdoc}
     */
    public function execute()
    {
        call_user_func($this->callback);
        parent::execute();
    }
}