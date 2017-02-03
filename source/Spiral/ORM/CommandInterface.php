<?php
/**
 * Spiral, Core Components
 *
 * @author Wolfy-J
 */

namespace Spiral\ORM;

use Spiral\ORM\Commands\TransactionalCommand;

/**
 * Represent one or multiple operations in transaction.
 *
 * Attention, ALL commands are flatten before execution to extract sub commands, implement
 * Traversable interface to let transaction to flatten command.
 *
 * @see TransactionalCommand
 */
interface CommandInterface
{
    /**
     * Executes command.
     */
    public function execute();

    /**
     * Complete command, method to be called when all other commands are already executed and
     * transaction is closed.
     */
    public function complete();

    /**
     * Rollback command or declare that command been rolled back.
     */
    public function rollBack();

    /**
     * Closure to be called after command executing.
     *
     * @param \Closure $closure
     */
    public function onExecute(\Closure $closure);

    /**
     * To be called after parent transaction been committed.
     *
     * @param \Closure $closure
     */
    public function onComplete(\Closure $closure);

    /**
     * To be called after parent transaction been rolled back.
     *
     * @param \Closure $closure
     */
    public function onRollBack(\Closure $closure);
}