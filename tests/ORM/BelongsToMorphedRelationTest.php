<?php
/**
 * Spiral, Core Components
 *
 * @author Wolfy-J
 */

namespace Spiral\Tests\ORM;

use Spiral\ORM\Entities\Loaders\RelationLoader;
use Spiral\Tests\ORM\Fixtures\Bio;
use Spiral\Tests\ORM\Fixtures\Comment;
use Spiral\Tests\ORM\Fixtures\Label;
use Spiral\Tests\ORM\Fixtures\Picture;
use Spiral\Tests\ORM\Fixtures\Post;
use Spiral\Tests\ORM\Fixtures\Profile;
use Spiral\Tests\ORM\Fixtures\Tag;
use Spiral\Tests\ORM\Fixtures\User;

abstract class BelongsToMorphedRelationTest extends BaseTest
{
    const MODELS = [
        User::class,
        Post::class,
        Comment::class,
        Tag::class,
        Profile::class,
        Bio::class,
        Picture::class,
        Label::class
    ];

    public function testSchemaBuilding()
    {
        $picture = new Picture();
        $this->assertTrue($picture->hasField('parent_id'));
        $this->assertTrue($picture->hasField('parent_type'));
    }

    public function testSetParent()
    {
        $picture = new Picture();
        $picture->parent = $user = new User();
        $picture->save();

        $this->assertSameInDB($picture);

        $this->assertEquals('user', $picture->parent_type);
        $this->assertEquals($user->primaryKey(), $picture->parent_id);
    }

    public function testChangeParent()
    {
        $picture = new Picture();
        $picture->parent = $user = new User();
        $picture->save();

        $this->assertSameInDB($picture);

        $this->assertEquals('user', $picture->parent_type);
        $this->assertEquals($user->primaryKey(), $picture->parent_id);

        $picture->parent = $post = new Post();
        $picture->parent->author = $user;
        $picture->save();

        $this->assertSameInDB($picture);

        $this->assertEquals('post', $picture->parent_type);
        $this->assertEquals($post->primaryKey(), $picture->parent_id);
    }

    public function testLazyLoad()
    {
        $picture = new Picture();
        $picture->parent = $user = new User();
        $picture->save();

        $picture = $this->orm->source(Picture::class)->findByPK($picture->primaryKey());
        $this->assertSameRecord($user, $picture->parent);

        $picture->parent = $post = new Post();
        $picture->parent->author = $user;
        $picture->save();

        $picture = $this->orm->source(Picture::class)->findByPK($picture->primaryKey());
        $this->assertSameRecord($post, $picture->parent);
    }

    public function testSetNull()
    {
        $picture = new Picture();
        $picture->parent = $user = new User();
        $picture->save();

        $picture = $this->orm->source(Picture::class)->findByPK($picture->primaryKey());
        $this->assertSameRecord($user, $picture->parent);

        $picture->parent = null;
        $picture->save();

        $picture = $this->orm->source(Picture::class)->findByPK($picture->primaryKey());
        $this->assertSame(null, $picture->parent);
    }

    public function testInversedLazy()
    {
        $picture = new Picture();
        $picture->parent = $user = new User();
        $picture->save();

        $user = $this->orm->source(User::class)->findByPK($user->primaryKey());

        $this->assertSameRecord($picture, $user->picture);

        $picture->parent = $post = new Post();
        $picture->parent->author = $user;
        $picture->save();

        $post = $this->orm->source(Post::class)->findByPK($user->primaryKey());
        $this->assertSameRecord($picture, $post->picture);
    }

    public function testInversedPostload()
    {
        $picture = new Picture();
        $picture->parent = $user = new User();
        $picture->save();

        $user = $this->orm->selector(User::class)->wherePK($user->primaryKey())
            ->load('picture', ['method' => RelationLoader::POSTLOAD])
            ->findOne();

        $this->assertTrue($user->getRelations()->get('picture')->isLoaded());

        $this->assertSameRecord($picture, $user->picture);

        $picture = new Picture();
        $picture->parent = $post = new Post();
        $picture->parent->author = $user;
        $picture->save();

        $post = $this->orm->selector(Post::class)->wherePK($post->primaryKey())
            ->load('picture', ['method' => RelationLoader::POSTLOAD])
            ->findOne();

        $this->assertTrue($post->getRelations()->get('picture')->isLoaded());
        $this->assertSameRecord($picture, $post->picture);
    }

    public function testInversedInload()
    {
        $picture = new Picture();
        $picture->parent = $user = new User();
        $picture->save();

        $user = $this->orm->selector(User::class)->wherePK($user->primaryKey())
            ->with('picture')
            ->load('picture', ['method' => RelationLoader::INLOAD])
            ->findOne();

        $this->assertTrue($user->getRelations()->get('picture')->isLoaded());

        $this->assertSameRecord($picture, $user->picture);

        $picture = new Picture();
        $picture->parent = $post = new Post();
        $picture->parent->author = $user;
        $picture->save();

        $post = $this->orm->selector(Post::class)->wherePK($post->primaryKey())
            ->with('picture')
            ->load('picture', ['method' => RelationLoader::INLOAD])
            ->findOne();

        $this->assertTrue($post->getRelations()->get('picture')->isLoaded());
        $this->assertSameRecord($picture, $post->picture);
    }

    public function testMorphedMany()
    {
        $user = new User();
        $user->labels->add($l1 = new Label(['name' => 'A']));
        $user->labels->add($l2 = new Label(['name' => 'B']));
        $user->save();

        $post = new Post();
        $post->author = $user;
        $post->labels->add($l3 = new Label(['name' => 'C']));
        $post->save();

        $this->assertSameInDB($user);
        $this->assertSameInDB($post);
        $this->assertSameInDB($l1);
        $this->assertSameInDB($l2);
        $this->assertSameInDB($l3);
    }

    public function testMorphedManyLazy()
    {
        $user = new User();
        $user->labels->add($l1 = new Label(['name' => 'A']));
        $user->labels->add($l2 = new Label(['name' => 'B']));
        $user->save();

        $post = new Post();
        $post->author = $user;
        $post->labels->add($l3 = new Label(['name' => 'C']));
        $post->save();

        $user = $this->orm->source(User::class)->findByPK($user->primaryKey());
        $this->assertCount(2, $user->labels);
        $this->assertTrue($user->labels->has($l1));
        $this->assertTrue($user->labels->has($l2));

        $post = $this->orm->source(Post::class)->findByPK($post->primaryKey());
        $this->assertCount(1, $post->labels);
        $this->assertTrue($post->labels->has($l3));
    }

    public function testMorphedManyInload()
    {
        $user = new User();
        $user->labels->add($l1 = new Label(['name' => 'A']));
        $user->labels->add($l2 = new Label(['name' => 'B']));
        $user->save();

        $post = new Post();
        $post->author = $user;
        $post->labels->add($l3 = new Label(['name' => 'C']));
        $post->save();

        $user = $this->orm->selector(User::class)->wherePK($user->primaryKey())
            ->with('labels')
            ->load('labels', ['method' => RelationLoader::INLOAD])
            ->findOne();

        $this->assertTrue($user->getRelations()->get('labels')->isLoaded());
        $this->assertCount(2, $user->labels);
        $this->assertTrue($user->labels->has($l1));
        $this->assertTrue($user->labels->has($l2));

        $post = $this->orm->selector(Post::class)->wherePK($post->primaryKey())
            ->with('labels')
            ->load('labels', ['method' => RelationLoader::INLOAD])
            ->findOne();

        $this->assertTrue($post->getRelations()->get('labels')->isLoaded());
        $this->assertCount(1, $post->labels);
        $this->assertTrue($post->labels->has($l3));
    }

    public function testMorphedManyPostload()
    {
        $user = new User();
        $user->labels->add($l1 = new Label(['name' => 'A']));
        $user->labels->add($l2 = new Label(['name' => 'B']));
        $user->save();

        $post = new Post();
        $post->author = $user;
        $post->labels->add($l3 = new Label(['name' => 'C']));
        $post->save();

        $user = $this->orm->selector(User::class)->wherePK($user->primaryKey())
            ->with('labels')
            ->load('labels', ['method' => RelationLoader::POSTLOAD])
            ->findOne();

        $this->assertTrue($user->getRelations()->get('labels')->isLoaded());
        $this->assertCount(2, $user->labels);
        $this->assertTrue($user->labels->has($l1));
        $this->assertTrue($user->labels->has($l2));

        $post = $this->orm->selector(Post::class)->wherePK($post->primaryKey())
            ->with('labels')
            ->load('labels', ['method' => RelationLoader::POSTLOAD])
            ->findOne();

        $this->assertTrue($post->getRelations()->get('labels')->isLoaded());
        $this->assertCount(1, $post->labels);
        $this->assertTrue($post->labels->has($l3));
    }
}