<?php
/**
 * Spiral, Core Components
 *
 * @author Wolfy-J
 */

namespace Spiral\ORM;

use Spiral\Models\ActiveEntityInterface;

/**
 * Adds ActiveRecord abilities to RecordEntity.
 */
abstract class Record extends RecordEntity implements ActiveEntityInterface
{
    /**
     * Sync entity with database, when no transaction is given ActiveRecord will create and run it
     * automatically.
     *
     * @param TransactionInterface|null $transaction
     * @param bool                      $queueRelations
     *
     * @return int
     */
    public function save(TransactionInterface $transaction = null, bool $queueRelations = true): int
    {
        /*
         * First, per interface agreement calculate entity state after save command being called.
         */
        if (!$this->isLoaded()) {
            $state = self::CREATED;
        } elseif (!$this->hasChanges()) {
            $state = self::UPDATED;
        } else {
            $state = self::UNCHANGED;
        }

        if (empty($transaction)) {
            /*
             * When no transaction is given we will create our own and run it immediately.
             */
            $transaction = $transaction ?? new Transaction();
            $transaction->store($this, $queueRelations);
            $transaction->run();
        } else {
            $transaction->addCommand($this->queueStore($queueRelations));
        }

        return $state;
    }

    /**
     * Delete entity in database, when no transaction is given ActiveRecord will create and run it
     * automatically.
     *
     * @param TransactionInterface|null $transaction
     */
    public function delete(TransactionInterface $transaction = null)
    {
        if (empty($transaction)) {
            /*
             * When no transaction is given we will create our own and run it immediately.
             */
            $transaction = $transaction ?? new Transaction();
            $transaction->delete($this);
            $transaction->run();
        } else {
            $transaction->addCommand($this->queueDelete());
        }
    }
}