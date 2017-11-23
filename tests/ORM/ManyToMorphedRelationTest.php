<?php
/**
 * Spiral, Core Components
 *
 * @author Wolfy-J
 */

namespace Spiral\Tests\ORM;

use Spiral\ORM\Entities\Loaders\RelationLoader;
use Spiral\ORM\Entities\Relations\ManyToManyRelation;
use Spiral\ORM\Entities\Relations\ManyToMorphedRelation;
use Spiral\Tests\ORM\Fixtures\Bio;
use Spiral\Tests\ORM\Fixtures\Comment;
use Spiral\Tests\ORM\Fixtures\Post;
use Spiral\Tests\ORM\Fixtures\Profile;
use Spiral\Tests\ORM\Fixtures\Supertag;
use Spiral\Tests\ORM\Fixtures\Tag;
use Spiral\Tests\ORM\Fixtures\User;

abstract class ManyToMorphedRelationTest extends BaseTest
{
    const MODELS = [
        User::class,
        Post::class,
        Comment::class,
        Tag::class,
        Profile::class,
        Bio::class,
        Supertag::class
    ];

    public function testSchemaBuilding()
    {
        $schema = $this->db->table('tagged_map')->getSchema();
        $this->assertTrue($schema->hasColumn('supertag_id'));
        $this->assertTrue($schema->hasColumn('tagged_id'));
        $this->assertTrue($schema->hasColumn('tagged_type'));
    }

    public function testInstance()
    {
        $tag = new Supertag(['name' => 'A']);
        $this->assertInstanceOf(ManyToMorphedRelation::class, $tag->tagged);

        $this->assertSame([
            'users' => User::class,
            'posts' => Post::class
        ], $tag->tagged->getVariations());

        $this->assertInstanceOf(ManyToManyRelation::class, $tag->tagged->users);
        $this->assertInstanceOf(ManyToManyRelation::class, $tag->tagged->posts);
    }

    /**
     * @expectedException \Spiral\ORM\Exceptions\RelationException
     */
    public function testBadVariation()
    {
        $tag = new Supertag(['name' => 'A']);
        $tag->tagged->magic;
    }

    public function testCount()
    {
        $tag = new Supertag(['name' => 'A']);
        $tag->save();

        $this->assertCount(0, $tag->tagged->users);
        $this->assertCount(0, $tag->tagged->posts);
    }

    public function testMultilinking()
    {
        $tag = new Supertag(['name' => 'A']);
        $tag->tagged->users->link($user = new User(['name' => 'Anton']));
        $tag->tagged->users->link($user1 = new User(['name' => 'John']));

        $post = new Post();
        $post->title = 'new title';
        $post->author = $user;

        $tag->tagged->posts->link($post);

        $post1 = new Post();
        $post1->title = 'new title 2';
        $post1->author = $user1;

        $tag->tagged->posts->link($post1);

        $tag->save();

        $this->assertCount(2, $tag->tagged->users);
        $this->assertCount(2, $tag->tagged->posts);

        $this->assertSameInDB($tag);
        $this->assertSameInDB($user);
        $this->assertSameInDB($user1);
        $this->assertSameInDB($post);
        $this->assertSameInDB($post1);

        $user = $this->orm->source(User::class)->findByPK($user->primaryKey());
        $this->assertTrue($user->supertags->has($tag));

        $post = $this->orm->source(Post::class)->findByPK($post->primaryKey());
        $this->assertTrue($post->supertags->has($tag));
    }

    public function testLinkInversedLazyLoad()
    {
        $user = new User();
        $user->supertags->link($tag1 = new Supertag(['name' => 'A']));
        $user->supertags->link($tag2 = new Supertag(['name' => 'B']));
        $user->save();

        $post = new Post();
        $post->author = $user;
        $post->supertags->link($tag2);
        $post->save();

        $user = $this->orm->source(User::class)->findByPK($user->primaryKey());
        $this->assertCount(2, $user->supertags);

        $this->assertTrue($user->supertags->has($tag1));
        $this->assertTrue($user->supertags->has($tag2));

        $post = $this->orm->source(Post::class)->findByPK($post->primaryKey());
        $this->assertCount(1, $post->supertags);

        $this->assertTrue($post->supertags->has($tag2));
    }

    public function testLinkInversedPostload()
    {
        $user = new User();
        $user->supertags->link($tag1 = new Supertag(['name' => 'A']));
        $user->supertags->link($tag2 = new Supertag(['name' => 'B']));
        $user->save();

        $post = new Post();
        $post->author = $user;
        $post->supertags->link($tag2);
        $post->save();

        $user = $this->orm->selector(User::class)->wherePK($user->primaryKey())
            ->with('supertags')
            ->load('supertags', ['method' => RelationLoader::POSTLOAD])
            ->findOne();

        $this->assertTrue($user->getRelations()->get('supertags')->isLoaded());
        $this->assertCount(2, $user->supertags);

        $this->assertTrue($user->supertags->has($tag1));
        $this->assertTrue($user->supertags->has($tag2));

        $post = $this->orm->selector(Post::class)->wherePK($post->primaryKey())
            ->with('supertags')
            ->load('supertags', ['method' => RelationLoader::POSTLOAD])
            ->findOne();

        $this->assertTrue($post->getRelations()->get('supertags')->isLoaded());
        $this->assertCount(1, $post->supertags);

        $this->assertTrue($post->supertags->has($tag2));
    }

    public function testLinkInversedInload()
    {
        $user = new User();
        $user->supertags->link($tag1 = new Supertag(['name' => 'A']));
        $user->supertags->link($tag2 = new Supertag(['name' => 'B']));
        $user->save();

        $post = new Post();
        $post->author = $user;
        $post->supertags->link($tag2);
        $post->save();

        $user = $this->orm->selector(User::class)->wherePK($user->primaryKey())
            ->with('supertags')
            ->load('supertags', ['method' => RelationLoader::INLOAD])
            ->findOne();

        $this->assertTrue($user->getRelations()->get('supertags')->isLoaded());
        $this->assertCount(2, $user->supertags);

        $this->assertTrue($user->supertags->has($tag1));
        $this->assertTrue($user->supertags->has($tag2));

        $post = $this->orm->selector(Post::class)->wherePK($post->primaryKey())
            ->with('supertags')
            ->load('supertags', ['method' => RelationLoader::INLOAD])
            ->findOne();

        $this->assertTrue($post->getRelations()->get('supertags')->isLoaded());
        $this->assertCount(1, $post->supertags);

        $this->assertTrue($post->supertags->has($tag2));
    }
}