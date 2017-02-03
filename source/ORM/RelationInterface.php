<?php
/**
 * Spiral, Core Components
 *
 * @author Wolfy-J
 */

namespace Spiral\ORM;

use Spiral\ORM\Exceptions\RelationException;

/**
 * Base definition for cross RecordInterface relations.
 */
interface RelationInterface
{
    /**
     * Indicates that relation commands must be executed prior to parent command.
     *
     * @return bool
     */
    public function isLeading(): bool;

    /**
     * Create version of relation with given parent and loaded data (if any).
     *
     * @param RecordInterface $parent
     * @param bool            $loaded
     * @param array|null      $data
     *
     * @return RelationInterface
     */
    public function withContext(
        RecordInterface $parent,
        bool $loaded = false,
        array $data = null
    ): RelationInterface;

    /**
     * Get class relation points to. Usually for debug purposes.
     *
     * @return string
     */
    public function getClass(): string;

    /**
     * Return true if relation has any loaded data (method withContext) must be called prior to
     * that.
     *
     * @return bool
     */
    public function isLoaded(): bool;

    /**
     * Indication that relation has any relation data, WILL force autoloading.
     *
     * @return bool
     */
    public function hasRelated(): bool;

    /**
     * Assign new value to relation. Make sure type is compatible.
     *
     * @param mixed $value
     *
     * @throws RelationException
     */
    public function setRelated($value);

    /**
     * Get related data representation.
     *
     * @return mixed|self
     *
     * @throws RelationException
     */
    public function getRelated();

    /**
     * Create relation specific command or multiple commands in relation to parent object command.
     * Parent command must be contextual in order to provide ability to exchange FK keys.
     *
     * @param ContextualCommandInterface $parentCommand
     *
     * @return CommandInterface
     */
    public function queueCommands(ContextualCommandInterface $parentCommand): CommandInterface;
}