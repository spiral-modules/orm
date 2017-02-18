<?php
/**
 * Spiral, Core Components
 *
 * @author Wolfy-J
 */

namespace Spiral\Tests\ORM;

use Spiral\ORM\Commands\CallbackCommand;
use Spiral\ORM\Exceptions\ORMException;
use Spiral\ORM\Transaction;
use Spiral\Tests\ORM\Fixtures\User;
use Spiral\Tests\ORM\Fixtures\UserStatus;

abstract class StoreInScopeTest extends BaseTest
{
    public function testNotLoaded()
    {
        $user = new User();

        $this->assertFalse($user->isLoaded());
    }

    public function testSaveActiveRecordAndCheckLoaded()
    {
        $user = new User();

        $this->assertFalse($user->isLoaded());

        $user->save();
        $this->assertTrue($user->isLoaded());
        $this->assertNotEmpty($user->primaryKey());
    }

    public function testSaveIntoTransaction()
    {
        $user = new User();
        $this->assertFalse($user->isLoaded());

        $transaction = new Transaction();
        $user->save($transaction);

        $this->assertTrue($user->isLoaded());
        $this->assertEmpty($user->primaryKey());

        $transaction->run();

        $this->assertTrue($user->isLoaded());
        $this->assertNotEmpty($user->primaryKey());
    }

    public function testStoreInTransactionAndCheckLoaded()
    {
        $user = new User();

        $this->assertFalse($user->isLoaded());

        $transaction = new Transaction();
        $transaction->store($user);

        $this->assertTrue($user->isLoaded());
        $this->assertEmpty($user->primaryKey());

        $transaction->run();

        $this->assertTrue($user->isLoaded());
        $this->assertNotEmpty($user->primaryKey());

        $this->assertSameInDB($user);
    }

    public function testStoreAndUpdate()
    {
        $user = new User();
        $user->name = 'Anton';
        $user->save();

        $this->assertSameInDB($user);

        $user->name = 'John';
        $user->save();

        $this->assertSameInDB($user);
    }

    public function testStoreAndUpdateDirty()
    {
        $user = new User();
        $user->name = 'Anton';
        $user->save();

        $this->assertSameInDB($user);

        $user->solidState(false);
        $user->name = 'John';
        $user->save();

        $this->assertSameInDB($user);
    }

    public function testStoreAndDelete()
    {
        $user = new User();
        $user->name = 'Anton';
        $user->save();

        $this->assertSameInDB($user);
        $this->assertSame(1, $this->dbal->database()->users->count());

        $user->delete();
        $this->assertFalse($user->isLoaded());

        $this->assertSame(0, $this->dbal->database()->users->count());
    }

    public function testStoreWithoutError()
    {
        $user = new User();
        $user->name = 'Anton';
        $this->assertFalse($user->isLoaded());

        $transaction = new Transaction();
        $transaction->store($user);
        $transaction->addCommand(new CallbackCommand(function () {
            //all good
        }));

        $transaction->run();

        $this->assertSame(1, $this->dbal->database()->users->count());

        $transaction->store($user);
        $transaction->run();

        $this->assertSame(1, $this->dbal->database()->users->count());
    }

    public function testStoreWithError()
    {
        $user = new User();
        $user->name = 'Anton';
        $this->assertFalse($user->isLoaded());

        $transaction = new Transaction();
        $transaction->store($user);
        $transaction->addCommand(new CallbackCommand(function () {
            throw new ORMException("some error");
        }));

        try {
            $transaction->run();
        } catch (ORMException $e) {
            $this->assertSame('some error', $e->getMessage());
        }

        $this->assertSame(0, $this->dbal->database()->users->count());

        $transaction->store($user);
        $transaction->run();

        $this->assertSame(1, $this->dbal->database()->users->count());
    }

    public function testMultipleSyncCommands()
    {
        $user = new User();
        $user->name = 'Anton';
        $this->assertFalse($user->isLoaded());

        $transaction = new Transaction();
        $transaction->store($user);

        $user->name = 'John';
        $transaction->store($user);

        $user->name = 'Bobby';
        $transaction->store($user);

        //Nothing changed
        $transaction->store($user);

        $transaction->run();

        $this->assertSame(1, $this->dbal->database()->users->count());
    }

    public function testIterate()
    {
        $user = new User();
        $user->name = 'Anton';
        $user->save();

        $count = 0;
        foreach ($this->orm->source(User::class) as $item) {
            $count++;
            $this->assertSameRecord($user, $item);
        }

        $this->assertSame(1, $count);
    }

    public function testMapIterate()
    {
        $user = new User();
        $user->name = 'Anton';
        $user->save();

        $this->orm->getMap()->remember($user);

        $count = 0;
        foreach ($this->orm->source(User::class) as $item) {
            $count++;
            //Same instance
            $this->assertSame($user, $item);
        }

        $this->assertSame(1, $count);
    }

    public function testMapIterateTwice()
    {
        $user = new User();
        $user->name = 'Anton';
        $user->save();

        $this->orm->getMap()->remember($user);

        $count = 0;
        foreach ($this->orm->source(User::class) as $item) {
            $count++;
            //Same instance
            $this->assertSame($user, $item);
        }

        $this->assertSame(1, $count);

        $this->orm->getMap()->forget($user);

        $count = 0;
        foreach ($this->orm->source(User::class) as $item) {
            $count++;
            $this->assertSameRecord($user, $item);
            $this->assertNotSame($user, $item);
        }

        $this->assertSame(1, $count);
    }


    public function testSum()
    {
        $user = new User();
        $user->name = 'Anton';
        $user->balance = 10;
        $user->save();

        $user = new User();
        $user->name = 'Anton';
        $user->balance = 20;
        $user->save();

        $this->assertSame(30, $this->orm->selector(User::class)->sum('balance'));
    }

    public function testStoreAndDeleteOneInTransaction()
    {
        $transaction = new Transaction();

        $user = new User();
        $user->name = 'Anton';
        $user->balance = 10;
        $user->save($transaction);

        $user = new User();
        $user->name = 'Anton';
        $user->balance = 20;
        $user->save($transaction);

        $user->delete($transaction);

        $transaction->run();
        $this->assertSame(1, $this->orm->selector(User::class)->count());

        $this->assertInstanceOf(User::class, $this->orm->selector(User::class)->fetchAll()[0]);
    }

    /**
     * @expectedException \Spiral\ORM\Exceptions\MapException
     */
    public function testStoreInMapNew()
    {
        $user = new User();
        $user->name = 'Anton';

        $this->orm->getMap()->remember($user);
    }

    public function testEnumColumn()
    {
        $user = new User();
        $this->assertInstanceOf(UserStatus::class, $user->status);
        $this->assertSame('active', (string)$user->status);

        $user->status = UserStatus::DISABLED;
        $this->assertSame('disabled', (string)$user->status);
    }

    /**
     * @expectedException \Spiral\ORM\Exceptions\AccessException
     */
    public function testEnumColumnError()
    {
        $user = new User();
        $this->assertInstanceOf(UserStatus::class, $user->status);
        $this->assertSame('active', (string)$user->status);

        $user->status = 'magic';
        $this->assertSame('disabled', (string)$user->status);
    }
}