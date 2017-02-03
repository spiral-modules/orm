<?php
/**
 * Spiral, Core Components
 *
 * @author Wolfy-J
 */
namespace Spiral\Tests\ORM;

use Spiral\Tests\ORM\Fixtures\Post;
use Spiral\Tests\ORM\Fixtures\Profile;
use Spiral\Tests\ORM\Fixtures\User;

abstract class StoreWithRelationsTest extends BaseTest
{
    /**
     * @expectedException \Spiral\Database\Exceptions\QueryException
     */
    public function testSaveWithBelongsToWithoutParent()
    {
        $post = new Post();
        $post->save();
    }

    public function testSaveWithParent()
    {
        $post = new Post();
        $post->author = new User();
        $post->save();

        $this->assertSameInDB($post);
        $this->assertSameInDB($post->author);
    }

    public function testSaveWithChild()
    {
        $user = new User();
        $user->name = 'Some name';
        $this->assertInstanceOf(Profile::class, $user->profile);
        $user->profile->bio = 'Some bio';

        $user->save();

        $this->assertSameInDB($user);
        $this->assertSameInDB($user->profile);
    }

    public function testSave3levelTree()
    {
        $post = new Post();

        $user = new User();
        $user->name = 'Some name';
        $user->profile->bio = 'Some bio';

        $post->author = $user;
        $post->save();

        $this->assertSameInDB($post);
        $this->assertSameInDB($post->author);
        $this->assertSameInDB($post->author->profile);
    }

    public function testSave3levelTreeDirectInit()
    {
        $post = new Post();
        $post->author = new User();
        $post->author->name = 'Some name';
        $post->author->profile->bio = 'Some bio';

        $post->save();

        $this->assertSameInDB($post);
        $this->assertSameInDB($post->author);
        $this->assertSameInDB($post->author->profile);
    }

    public function testSave3levelTreeDirectInitChild()
    {
        $post = new Post();
        $post->author = new User();
        $post->author->name = 'Some name';
        $post->author->profile = new Profile(['bio' => 'new bio']);

        $post->save();

        $this->assertSameInDB($post);
        $this->assertSameInDB($post->author);
        $this->assertSameInDB($post->author->profile);
    }
}