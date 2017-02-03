<?php
/**
 * Spiral, Core Components
 *
 * @author Wolfy-J
 */

namespace Spiral\ORM\Events;

use Spiral\Models\EntityInterface;
use Spiral\Models\Events\EntityEvent;
use Spiral\ORM\CommandInterface;
use Spiral\ORM\ContextualCommandInterface;

class RecordEvent extends EntityEvent
{
    /**
     * @var CommandInterface
     */
    private $command;

    /**
     * @param EntityInterface  $entity
     * @param CommandInterface $command
     */
    public function __construct(EntityInterface $entity, CommandInterface $command)
    {
        parent::__construct($entity);
        $this->command = $command;
    }

    /**
     * Indication that command is contextual (i.e. have mutable data).
     *
     * @return bool
     */
    public function isContextual(): bool
    {
        return $this->command instanceof ContextualCommandInterface;
    }

    /**
     * @return CommandInterface
     */
    public function getCommand(): CommandInterface
    {
        return $this->command;
    }
}
