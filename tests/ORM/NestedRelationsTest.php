<?php
/**
 * Spiral, Core Components
 *
 * @author Wolfy-J
 */
namespace Spiral\Tests\ORM;

use Spiral\ORM\Entities\Loaders\RelationLoader;
use Spiral\Tests\ORM\Fixtures\Comment;
use Spiral\Tests\ORM\Fixtures\Post;
use Spiral\Tests\ORM\Fixtures\Tag;
use Spiral\Tests\ORM\Fixtures\User;

abstract class NestedRelationsTest extends BaseTest
{
    public function testParentWithChild()
    {
        $post = new Post();
        $post->author = new User();
        $post->author->profile->bio = 'hello world';
        $post->comments->add($comment = new Comment(['message' => 'hi']));

        $post->save();

        /**
         * @var Post $dbPost
         */
        $dbPost = $this->orm->selector(Post::class)
            ->load('author.profile')
            ->load('comments')
            ->findOne();

        $this->assertTrue($dbPost->getRelations()->get('author')->isLoaded());
        $this->assertTrue($dbPost->author->getRelations()->get('profile')->isLoaded());
        $this->assertTrue($dbPost->getRelations()->get('comments')->isLoaded());

        $this->assertSame($post->primaryKey(), $dbPost->primaryKey());

        $this->assertSameRecord($post->author, $dbPost->author);
        $this->assertSameRecord($post->author->profile, $dbPost->author->profile);
        $this->assertCount(1, $dbPost->comments);
    }

    public function testAlternativeLoadWithChild()
    {
        $post = new Post();
        $post->author = new User();
        $post->author->profile->bio = 'hello world';
        $post->comments->add($comment = new Comment(['message' => 'hi']));

        $post->save();

        /**
         * @var Post $dbPost
         */
        $dbPost = $this->orm->selector(Post::class)
            ->load(['author.profile', 'comments'])
            ->findOne();

        $this->assertTrue($dbPost->getRelations()->get('author')->isLoaded());
        $this->assertTrue($dbPost->author->getRelations()->get('profile')->isLoaded());
        $this->assertTrue($dbPost->getRelations()->get('comments')->isLoaded());

        $this->assertSame($post->primaryKey(), $dbPost->primaryKey());

        $this->assertSameRecord($post->author, $dbPost->author);
        $this->assertSameRecord($post->author->profile, $dbPost->author->profile);
        $this->assertCount(1, $dbPost->comments);
    }

    public function testAlternative2LoadWithChild()
    {
        $post = new Post();
        $post->author = new User();
        $post->author->profile->bio = 'hello world';
        $post->comments->add($comment = new Comment(['message' => 'hi']));

        $post->save();

        /**
         * @var Post $dbPost
         */
        $dbPost = $this->orm->selector(Post::class)
            ->load([
                'author.profile' => ['method' => RelationLoader::INLOAD],
                'comments'       => ['method' => RelationLoader::INLOAD]
            ])
            ->findOne();

        $this->assertTrue($dbPost->getRelations()->get('author')->isLoaded());
        $this->assertTrue($dbPost->author->getRelations()->get('profile')->isLoaded());
        $this->assertTrue($dbPost->getRelations()->get('comments')->isLoaded());

        $this->assertSame($post->primaryKey(), $dbPost->primaryKey());

        $this->assertSameRecord($post->author, $dbPost->author);
        $this->assertSameRecord($post->author->profile, $dbPost->author->profile);
        $this->assertCount(1, $dbPost->comments);
    }

    public function testAlternativeWithWithChild()
    {
        $post = new Post();
        $post->author = new User();
        $post->author->profile->bio = 'hello world';
        $post->comments->add($comment = new Comment(['message' => 'hi']));

        $post->save();

        /**
         * @var Post $dbPost
         */
        $dbPost = $this->orm->selector(Post::class)
            ->with(['author.profile', 'comments'])
            ->findOne();

        $this->assertFalse($dbPost->getRelations()->get('author')->isLoaded());
        $this->assertFalse($dbPost->author->getRelations()->get('profile')->isLoaded());
        $this->assertFalse($dbPost->getRelations()->get('comments')->isLoaded());

        $this->assertSame($post->primaryKey(), $dbPost->primaryKey());

        $this->assertSameRecord($post->author, $dbPost->author);
        $this->assertSameRecord($post->author->profile, $dbPost->author->profile);
        $this->assertCount(1, $dbPost->comments);
    }


    public function testAlternative2WithWithChild()
    {
        $post = new Post();
        $post->author = new User();
        $post->author->profile->bio = 'hello world';
        $post->comments->add($comment = new Comment(['message' => 'hi']));

        $post->save();

        /**
         * @var Post $dbPost
         */
        $dbPost = $this->orm->selector(Post::class)
            ->with([
                'author.profile' => ['alias' => 'user_profile'],
                'comments'       => ['alias' => 'some_comments']
            ])
            ->findOne();

        $this->assertFalse($dbPost->getRelations()->get('author')->isLoaded());
        $this->assertFalse($dbPost->author->getRelations()->get('profile')->isLoaded());
        $this->assertFalse($dbPost->getRelations()->get('comments')->isLoaded());

        $this->assertSame($post->primaryKey(), $dbPost->primaryKey());

        $this->assertSameRecord($post->author, $dbPost->author);
        $this->assertSameRecord($post->author->profile, $dbPost->author->profile);
        $this->assertCount(1, $dbPost->comments);
    }

    public function testParentWithChildNoProfile()
    {
        $post = new Post();
        $post->author = new User();
        $post->comments->add($comment = new Comment(['message' => 'hi']));

        $post->save();

        /**
         * @var Post $dbPost
         */
        $dbPost = $this->orm->selector(Post::class)
            ->load('author.profile')
            ->load('comments')
            ->findOne();

        $this->assertTrue($dbPost->getRelations()->get('author')->isLoaded());
        $this->assertTrue($dbPost->author->getRelations()->get('profile')->isLoaded());
        $this->assertTrue($dbPost->getRelations()->get('comments')->isLoaded());

        $this->assertSame($post->primaryKey(), $dbPost->primaryKey());

        $this->assertSameRecord($post->author, $dbPost->author);
        $this->assertFalse($post->author->profile->isLoaded());
        $this->assertCount(1, $dbPost->comments);
    }

    public function testParentWithChildNoComments()
    {
        $post = new Post();
        $post->author = new User();
        $post->author->profile->bio = 'hello world';

        $post->save();

        /**
         * @var Post $dbPost
         */
        $dbPost = $this->orm->selector(Post::class)
            ->load('author.profile')
            ->load('comments')
            ->findOne();

        $this->assertTrue($dbPost->getRelations()->get('author')->isLoaded());
        $this->assertTrue($dbPost->author->getRelations()->get('profile')->isLoaded());
        $this->assertTrue($dbPost->getRelations()->get('comments')->isLoaded());

        $this->assertSame($post->primaryKey(), $dbPost->primaryKey());

        $this->assertSameRecord($post->author, $dbPost->author);
        $this->assertSameRecord($post->author->profile, $dbPost->author->profile);
        $this->assertCount(0, $dbPost->comments);
    }

    public function testComplexInverse()
    {
        $post = new Post();
        $post->title = 'title';
        $post->author = new User();
        $post->author->profile->bio = 'hello world';

        $post->tags->link(new Tag(['name' => 'tag 1']));
        $post->tags->link(new Tag(['name' => 'tag b']));

        $post->comments->add($comment = new Comment(['message' => 'hi']));

        $post->save();

        /**
         * @var Post $dbPost
         */
        $dbPost = $this->orm->withMap(null)->selector(Post::class)
            ->load('tags', ['method' => RelationLoader::INLOAD])
            ->load('tags.posts', ['method' => RelationLoader::INLOAD])
            ->load('tags.posts.author', ['method' => RelationLoader::INLOAD])
            ->load('tags.posts.author.profile', ['method' => RelationLoader::INLOAD])
            ->load('tags.posts.comments', ['method' => RelationLoader::INLOAD])
            ->findOne();

        foreach ($dbPost->tags as $tag) {
            $this->assertTrue($tag->getRelations()->get('posts')->isLoaded());
            $this->assertCount(1, $tag->posts);

            foreach ($tag->posts as $tagPost) {
                $this->assertSameRecord($post, $tagPost);
                $this->assertTrue($tagPost->getRelations()->get('comments')->isLoaded());
                $this->assertTrue($tagPost->getRelations()->get('author')->isLoaded());
                $this->assertTrue($tagPost->author->getRelations()->get('profile')->isLoaded());
                $this->assertSameRecord($post->author->profile, $tagPost->author->profile);
            }
        }
    }

    public function testComplexInverseButNoTags()
    {
        $post = new Post();
        $post->title = 'title';
        $post->author = new User();
        $post->author->profile->bio = 'hello world';
        $post->comments->add($comment = new Comment(['message' => 'hi']));

        $post->save();

        /**
         * @var Post $dbPost
         */
        $dbPost = $this->orm->withMap(null)->selector(Post::class)
            ->load('tags', ['method' => RelationLoader::INLOAD])
            ->load('tags.posts', ['method' => RelationLoader::INLOAD])
            ->load('tags.posts.author', ['method' => RelationLoader::INLOAD])
            ->load('tags.posts.author.profile', ['method' => RelationLoader::INLOAD])
            ->load('tags.posts.comments', ['method' => RelationLoader::INLOAD])
            ->findOne();

        $this->assertNotEmpty($dbPost);
    }
}