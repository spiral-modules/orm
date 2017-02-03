<?php
/**
 * Spiral, Core Components
 *
 * @author Wolfy-J
 */

namespace Spiral\ORM;

/**
 * Transaction aggregates set of commands declared by entities and executes them all together.
 */
interface TransactionInterface
{
    /**
     * @param CommandInterface $command
     *
     * @return mixed
     */
    public function addCommand(CommandInterface $command);

    /**
     * Execute all nested commands in transaction, if failed - transaction MUST automatically
     * rollback and exception instance MUST be throwed.
     *
     * Transaction will be emptied after execution.
     *
     * @throws \Throwable
     */
    public function run();
}