<?php
/**
 * Spiral, Core Components
 *
 * @author Wolfy-J
 */

namespace Spiral\ORM;

use Spiral\Database\Entities\Driver;
use Spiral\ORM\Exceptions\RecordException;

/**
 * Singular ORM transaction with ability to automatically open transaction for all involved
 * drivers.
 *
 * Drivers will be automatically fetched from commands. Potentially Transaction can be improved
 * to optimize commands inside it (batch insert, batch delete and etc).
 *
 * Technically Transaction can work as classic UnitOfWork with ability to watch entities, but it's
 * recommended to create additional abstraction at top with proper business rules.
 */
final class Transaction implements TransactionInterface
{
    /**
     * @var CommandInterface[]
     */
    private $commands = [];

    /**
     * Store entity information (update or insert).
     *
     * @param RecordInterface $record
     * @param bool            $queueRelations
     *
     * @throws RecordException
     */
    public function store(RecordInterface $record, bool $queueRelations = true)
    {
        $this->addCommand($record->queueStore($queueRelations));
    }

    /**
     * Delete entity from database.
     *
     * @param RecordInterface $record
     *
     * @throws RecordException
     */
    public function delete(RecordInterface $record)
    {
        $this->addCommand($record->queueDelete());
    }

    /**
     * {@inheritdoc}
     */
    public function addCommand(CommandInterface $command)
    {
        $this->commands[] = $command;
    }

    /**
     * Will return flattened list of commands.
     *
     * @return \Generator
     */
    public function getCommands()
    {
        foreach ($this->commands as $command) {
            if ($command instanceof \Traversable) {
                //Nested commands
                yield from $command;
            }

            yield $command;
        }
    }

    /**
     * {@inheritdoc}
     *
     * @param bool $forceTransaction When set to false transaction would not be started when only
     *                               single command is presented inside (all commands are flatten
     *                               before appearing inside this method).
     * @param bool $clean            When set to true - transaction will be cleaned after
     *                               execution, default behaviour.
     */
    public function run(bool $forceTransaction = false, bool $clean = true)
    {
        /**
         * @var Driver[]           $drivers
         * @var CommandInterface[] $commands
         */
        $drivers = $commands = [];

        foreach ($this->getCommands() as $command) {
            if ($command instanceof SQLCommandInterface) {
                $driver = $command->getDriver();
                if (!empty($driver) && !in_array($driver, $drivers)) {
                    $drivers[] = $driver;
                }
            }

            $commands[] = $command;
        }

        if (empty($commands)) {
            return;
        }

        //Commands we executed and drivers with started transactions
        $executedCommands = $wrappedDrivers = [];

        try {
            if ($forceTransaction || count($commands) > 1) {
                //Starting transactions
                foreach ($drivers as $driver) {
                    $driver->beginTransaction();
                    $wrappedDrivers[] = $driver;
                }
            }

            //Run commands
            foreach ($commands as $command) {
                $command->execute();
                $executedCommands[] = $command;
            }
        } catch (\Throwable $e) {
            try {
                foreach (array_reverse($wrappedDrivers) as $driver) {
                    /** @var Driver $driver */
                    $driver->rollbackTransaction();
                }
            } catch (\Throwable $et) {
                throw $e;
            }

            foreach (array_reverse($executedCommands) as $command) {
                /** @var CommandInterface $command */
                $command->rollBack();
            }

            $this->commands = [];
            throw $e;
        }

        foreach (array_reverse($wrappedDrivers) as $driver) {
            /** @var Driver $driver */
            $driver->commitTransaction();
        }

        foreach ($executedCommands as $command) {
            //This is the point when record will get related PK and FKs filled
            $command->complete();
        }

        //Clean transaction
        if ($clean) {
            $this->commands = [];
        }
    }
}
