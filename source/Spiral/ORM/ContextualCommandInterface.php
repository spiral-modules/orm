<?php
/**
 * Spiral, Core Components
 *
 * @author Wolfy-J
 */

namespace Spiral\ORM;

/**
 * Contextual commands used to carry FK and PK values across commands pipeline, other commands are
 * able to mount it's values into parent context or read from it.
 */
interface ContextualCommandInterface extends CommandInterface
{
    /**
     * Must be true when command does not carry any data.
     *
     * @return bool
     */
    public function isEmpty(): bool;

    /**
     * Get current command context.
     *
     * @return array
     */
    public function getContext(): array;

    /**
     * Add context value, usually FK. Must be set before command being executed, usually in leading
     * command "execute" event.
     *
     * @param string $name
     * @param mixed  $value
     */
    public function addContext(string $name, $value);

    /**
     * Returns associated primary key, can be NULL. Promised on execution!
     *
     * @return mixed|null
     */
    public function primaryKey();
}