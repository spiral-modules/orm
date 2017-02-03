<?php
/**
 * Spiral, Core Components
 *
 * @author Wolfy-J
 */

namespace Spiral\ORM\Commands;

use Spiral\ORM\CommandInterface;
use Spiral\ORM\ContextualCommandInterface;
use Spiral\ORM\Exceptions\ORMException;

/**
 * Command to handle multiple inner commands.
 */
class TransactionalCommand implements \IteratorAggregate, ContextualCommandInterface
{
    /**
     * Nested commands.
     *
     * @var CommandInterface[]
     */
    private $commands = [];

    /**
     * @var ContextualCommandInterface
     */
    private $leadingCommand;

    /**
     * {@inheritdoc}
     */
    public function addCommand(CommandInterface $command, bool $leading = false)
    {
        if ($command instanceof NullCommand) {
            return;
        }

        $this->commands[] = $command;

        if ($leading) {
            if (!$command instanceof ContextualCommandInterface) {
                throw new ORMException("Only contextual commands can be used as leading");
            }

            $this->leadingCommand = $command;
        }
    }

    /**
     * {@inheritdoc}
     */
    public function getLeading(): ContextualCommandInterface
    {
        if (empty($this->leadingCommand)) {
            throw new ORMException("Leading command is not set");
        }

        return $this->leadingCommand;
    }

    /**
     * {@inheritdoc}
     */
    public function isEmpty(): bool
    {
        return $this->getLeading()->isEmpty();
    }

    /**
     * {@inheritdoc}
     */
    public function getContext(): array
    {
        return $this->getLeading()->getContext();
    }

    /**
     * {@inheritdoc}
     */
    public function addContext(string $name, $value)
    {
        $this->getLeading()->addContext($name, $value);
    }

    /**
     * @return mixed|null
     */
    public function primaryKey()
    {
        return $this->getLeading()->primaryKey();
    }

    /**
     * {@inheritdoc}
     */
    public function getIterator()
    {
        foreach ($this->commands as $command) {
            if ($command instanceof \Traversable) {
                yield from $command;
            }

            yield $command;
        }
    }

    /**
     * Closure to be called after command executing.
     *
     * @param \Closure $closure
     */
    final public function onExecute(\Closure $closure)
    {
        $this->getLeading()->onExecute($closure);
    }

    /**
     * To be called after parent transaction been commited.
     *
     * @param \Closure $closure
     */
    final public function onComplete(\Closure $closure)
    {
        $this->getLeading()->onComplete($closure);
    }

    /**
     * To be called after parent transaction been rolled back.
     *
     * @param \Closure $closure
     */
    final public function onRollBack(\Closure $closure)
    {
        $this->getLeading()->onRollBack($closure);
    }

    /**
     * {@inheritdoc}
     */
    public function execute()
    {
        //Nothing
    }

    /**
     * {@inheritdoc}
     */
    public function complete()
    {
        //Nothing
    }

    /**
     * {@inheritdoc}
     */
    public function rollBack()
    {
        //Nothing
    }
}
