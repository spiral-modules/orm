<?php
/**
 * Spiral, Core Components
 *
 * @author Wolfy-J
 */
namespace Spiral\Tests\ORM;

use Spiral\ORM\Events\RecordEvent;
use Spiral\ORM\Transaction;
use Spiral\Tests\ORM\Fixtures\Post;
use Spiral\Tests\ORM\Fixtures\User;

abstract class EventsTest extends BaseTest
{
    public function testOnCreate()
    {
        $post = new Post();
        $post->author = new User();
        $post->title = 'new post';

        $done = false;
        Post::events()->addListener('create', $listener = function (RecordEvent $e) use (&$done) {
            $this->assertTrue($e->isContextual());
            $done = true;
        });

        $this->assertFalse($done);
        $post->save();
        $this->assertTrue($done);

        Post::events()->removeListener('create', $listener);
    }

    public function testCreateInTransaction()
    {
        $post = new Post();
        $post->author = new User();
        $post->title = 'new post';

        $done = false;
        Post::events()->addListener('create', $listener = function (RecordEvent $e) use (&$done) {
            $this->assertTrue($e->isContextual());
            $done = true;
        });

        $doneB = false;
        Post::events()->addListener('created',
            $listenerB = function (RecordEvent $e) use (&$doneB) {
                $this->assertTrue($e->isContextual());
                $doneB = true;
            });

        $transaction = new Transaction();

        $this->assertFalse($done);
        $this->assertFalse($doneB);

        $transaction->store($post);

        $this->assertTrue($done);
        $this->assertFalse($doneB);

        $transaction->run();

        $this->assertTrue($done);
        $this->assertTrue($doneB);

        Post::events()->removeListener('create', $listener);
        Post::events()->removeListener('created', $listenerB);
    }

    public function testOnCreated()
    {
        $post = new Post();
        $post->author = new User();
        $post->title = 'new post';

        $done = false;
        Post::events()->addListener('created', $listener = function (RecordEvent $e) use (&$done) {
            $this->assertTrue($e->isContextual());
            $done = true;
        });

        $this->assertFalse($done);
        $post->save();
        $this->assertTrue($done);

        Post::events()->removeListener('created', $listener);
    }

    public function testOnUpdate()
    {
        $post = new Post();
        $post->author = new User();
        $post->title = 'new post';

        $done = false;
        Post::events()->addListener('update', $listener = function (RecordEvent $e) use (&$done) {
            $this->assertTrue($e->isContextual());
            $done = true;
        });
        $post->save();
        $this->assertFalse($done);

        $post->title = 'another post';
        $post->save();
        $this->assertTrue($done);

        Post::events()->removeListener('update', $listener);
    }

    public function testUpdateInTransaction()
    {
        $post = new Post();
        $post->author = new User();
        $post->title = 'new post';

        $post->save();

        $done = false;
        Post::events()->addListener('update', $listener = function (RecordEvent $e) use (&$done) {
            $this->assertTrue($e->isContextual());
            $done = true;
        });

        $doneB = false;
        Post::events()->addListener('updated',
            $listenerB = function (RecordEvent $e) use (&$doneB) {
                $this->assertTrue($e->isContextual());
                $doneB = true;
            });

        $transaction = new Transaction();

        $this->assertFalse($done);
        $this->assertFalse($doneB);

        $transaction->store($post);

        $this->assertTrue($done);
        $this->assertFalse($doneB);

        $transaction->run();

        $this->assertTrue($done);
        $this->assertTrue($doneB);

        Post::events()->removeListener('update', $listener);
        Post::events()->removeListener('updated', $listenerB);
    }

    public function testOnUpdated()
    {
        $post = new Post();
        $post->author = new User();
        $post->title = 'new post';

        $done = false;
        Post::events()->addListener('updated', $listener = function (RecordEvent $e) use (&$done) {
            $this->assertTrue($e->isContextual());
            $done = true;
        });
        $post->save();
        $this->assertFalse($done);

        $post->title = 'another post';
        $post->save();
        $this->assertTrue($done);

        Post::events()->removeListener('updated', $listener);
    }

    public function testOnDelete()
    {
        $post = new Post();
        $post->author = new User();
        $post->title = 'new post';
        $post->save();

        $done = false;
        Post::events()->addListener('delete', $listener = function (RecordEvent $e) use (&$done) {
            $this->assertFalse($e->isContextual());
            $done = true;
        });

        $this->assertFalse($done);
        $post->delete();
        $this->assertTrue($done);

        Post::events()->removeListener('deleted', $listener);
    }

    public function testDeleteInTransaction()
    {
        $post = new Post();
        $post->author = new User();
        $post->title = 'new post';
        $post->save();

        $done = false;
        Post::events()->addListener('delete', $listener = function (RecordEvent $e) use (&$done) {
            $this->assertFalse($e->isContextual());
            $done = true;
        });

        $doneB = false;
        Post::events()->addListener('deleted',
            $listenerB = function (RecordEvent $e) use (&$doneB) {
                $this->assertFalse($e->isContextual());
                $doneB = true;
            });

        $transaction = new Transaction();

        $this->assertFalse($done);
        $this->assertFalse($doneB);

        $transaction->delete($post);

        $this->assertTrue($done);
        $this->assertFalse($doneB);

        $transaction->run();

        $this->assertTrue($done);
        $this->assertTrue($doneB);

        Post::events()->removeListener('delete', $listener);
        Post::events()->removeListener('deleted', $listenerB);
    }

    public function testOnDeleted()
    {
        $post = new Post();
        $post->author = new User();
        $post->title = 'new post';
        $post->save();

        $done = false;
        Post::events()->addListener('deleted', $listener = function (RecordEvent $e) use (&$done) {
            $this->assertFalse($e->isContextual());
            $done = true;
        });

        $this->assertFalse($done);
        $post->delete();
        $this->assertTrue($done);

        Post::events()->removeListener('deleted', $listener);
    }
}