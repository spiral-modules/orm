<?php
/**
 * orm
 *
 * @author    Wolfy-J
 */

namespace Spiral\Tests\ORM;

use Spiral\ORM\Transaction;
use Spiral\Tests\ORM\Fixtures\Post;
use Spiral\Tests\ORM\Fixtures\User;

abstract class TreeSequenceTest extends BaseTest
{
    public function testSaveParentAndChild()
    {
        $user = new User();

        $post = new Post();
        $post->author = $user;

        $transaction = new Transaction();
        $transaction->store($user);
        $transaction->store($post);

        $transaction->run();

        $this->assertSameInDB($user);
        $this->assertSameInDB($post);
    }

    public function testSaveChildAndParent()
    {
        $user = new User();

        $post = new Post();
        $post->author = $user;

        $transaction = new Transaction();
        $transaction->store($post);
        $transaction->store($user);

        $transaction->run();

        $this->assertSameInDB($user);
        $this->assertSameInDB($post);
    }

    public function testSaveChildAndParentLOOP()
    {
        $user = new User();

        $post = new Post();
        $post->author = $user;
        $user->posts->add(clone $post);

        $transaction = new Transaction();
        $transaction->store($post);
        $transaction->store($user);

        $transaction->run();

        $this->assertSameInDB($user);
        $this->assertSameInDB($post);
    }

    public function testSaveChildAndParentLOOP_noParentSave()
    {
        $user = new User();

        $post = new Post();
        $post->author = $user;
        $user->posts->add(clone $post);

        $transaction = new Transaction();
        $transaction->store($post);

        $transaction->run();

        $this->assertSameInDB($user);
        $this->assertSameInDB($post);
    }

    public function testSavedParentAndChildLOOP()
    {
        $user = new User();

        $post = new Post();
        $post->author = $user;
        $user->posts->add(clone $post);

        $transaction = new Transaction();

        $transaction->store($user);
        $transaction->store($post);

        $transaction->run();

        $this->assertSameInDB($user);
        $this->assertSameInDB($post);
    }
}