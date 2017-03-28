<?php
/**
 * Spiral, Core Components
 *
 * @author Wolfy-J
 */

namespace Spiral\Tests\ORM;

use Spiral\ORM\Entities\Loaders\RelationLoader;
use Spiral\ORM\Entities\Relations\HasManyRelation;
use Spiral\Tests\ORM\Fixtures\Comment;
use Spiral\Tests\ORM\Fixtures\Post;
use Spiral\Tests\ORM\Fixtures\User;

abstract class HasManyRelationTest extends BaseTest
{
    public function testInstance()
    {
        $post = new Post();
        $this->assertFalse($post->getRelations()->get('comments')->isLoaded());
        $this->assertTrue(empty($post->comments));

        $this->assertInstanceOf(HasManyRelation::class, $post->comments);
        $this->assertFalse($post->comments->isLeading());
        $this->assertCount(0, $post->comments);
        $this->assertTrue($post->comments->isLoaded());
        $this->assertTrue(empty($post->comments));
    }

    public function testAddInstanceAndSave()
    {
        $post = new Post();
        $post->author = new User();
        $post->comments->add($comment = new Comment(['message' => 'hi']));
        $this->assertCount(1, $post->comments);

        $post->save();

        $this->assertCount(1, $this->db->posts);
        $this->assertCount(1, $this->db->comments);

        $this->assertSameInDB($post);
        $this->assertSameInDB($comment);
    }

    public function testAddMultipleInstancesAndSave()
    {
        $post = new Post();
        $post->author = new User();
        $post->comments->add($comment = new Comment(['message' => 'hi']));
        $post->comments->add($comment2 = new Comment(['message' => 'hi2']));

        $this->assertCount(2, $post->comments);

        $post->save();

        $this->assertCount(2, $this->db->comments);

        $this->assertSameInDB($post);
        $this->assertSameInDB($comment);
        $this->assertSameInDB($comment2);
    }

    /**
     * @expectedException \Spiral\ORM\Exceptions\RelationException
     * @expectedExceptionMessage Must be an instance of 'Spiral\Tests\ORM\Fixtures\Comment',
     *                           'Spiral\Tests\ORM\Fixtures\User' given
     */
    public function testSetWrongInstance()
    {
        $post = new Post();
        $post->comments->add(new User());
    }

    /**
     * @expectedException \Spiral\ORM\Exceptions\RelationException
     * @expectedExceptionMessage Must be an instance of 'Spiral\Tests\ORM\Fixtures\Comment',
     *                           'Spiral\Tests\ORM\Fixtures\User' given
     */
    public function testSetWrongInstance2()
    {
        $post = new Post();
        $post->comments = [new User()];
    }

    /**
     * @expectedException \Spiral\ORM\Exceptions\RelationException
     * @expectedExceptionMessage HasMany relation can only be set with array of entities
     */
    public function testSetWrongInstance3()
    {
        $post = new Post();
        $post->comments = new User();
    }

    public function testSaveAndHasAndPostload()
    {
        $post = new Post();
        $post->author = new User();
        $post->comments->add($comment = new Comment(['message' => 'hi']));
        $post->comments->add($comment2 = new Comment(['message' => 'hi2']));
        $post->comments->add($comment3 = new Comment(['message' => 'hi3']));

        $this->assertTrue($post->comments->has($comment));
        $this->assertTrue($post->comments->has($comment2->getFields()));
        $this->assertTrue($post->comments->has(['message' => 'hi3']));
        $this->assertCount(3, $post->comments);

        $post->save();

        $this->assertSameInDB($post);
        $this->assertSameInDB($comment);
        $this->assertSameInDB($comment2);
        $this->assertSameInDB($comment3);

        /** @var Post $dbPost */
        $dbPost = $this->orm->selector(Post::class)
            ->wherePK($post->primaryKey())
            ->load('comments', ['method' => RelationLoader::POSTLOAD])
            ->findOne();

        $this->assertTrue($dbPost->getRelations()->get('comments')->isLoaded());
        $this->assertCount(3, $dbPost->comments);

        $this->assertTrue($dbPost->comments->has($comment));
        $this->assertTrue($dbPost->comments->has($comment2));
        $this->assertTrue($dbPost->comments->has(['message' => 'hi3']));
    }

    public function testSaveAndHasAndInload()
    {
        $post = new Post();
        $post->author = new User();
        $post->comments->add($comment = new Comment(['message' => 'hi']));
        $post->comments->add($comment2 = new Comment(['message' => 'hi2']));
        $post->comments->add($comment3 = new Comment(['message' => 'hi3']));

        $this->assertTrue($post->comments->has($comment));
        $this->assertTrue($post->comments->has($comment2));
        $this->assertTrue($post->comments->has(['message' => 'hi3']));
        $this->assertCount(3, $post->comments);

        $post->save();

        $this->assertSameInDB($post);
        $this->assertSameInDB($comment);
        $this->assertSameInDB($comment2);
        $this->assertSameInDB($comment3);

        /** @var Post $dbPost */
        $dbPost = $this->orm->selector(Post::class)
            ->wherePK($post->primaryKey())
            ->load('comments', ['method' => RelationLoader::INLOAD])
            ->findOne();

        $this->assertSame(1, $this->orm->selector(Post::class)
            ->wherePK($post->primaryKey())
            ->load('comments', ['method' => RelationLoader::INLOAD])
            ->count()
        );

        $this->assertTrue($dbPost->getRelations()->get('comments')->isLoaded());
        $this->assertCount(3, $dbPost->comments);

        $this->assertTrue($dbPost->comments->has($comment));
        $this->assertTrue($dbPost->comments->has($comment2));
        $this->assertTrue($dbPost->comments->has(['message' => 'hi3']));
    }

    public function testSaveAndHasAndLazyLoad()
    {
        $post = new Post();
        $post->author = new User();
        $post->comments->add($comment = new Comment(['message' => 'hi']));
        $post->comments->add($comment2 = new Comment(['message' => 'hi2']));
        $post->comments->add($comment3 = new Comment(['message' => 'hi3']));

        $this->assertTrue($post->comments->has($comment));
        $this->assertTrue($post->comments->has($comment2->getFields()));
        $this->assertTrue($post->comments->has(['message' => 'hi3']));
        $this->assertCount(3, $post->comments);

        $post->save();

        $this->assertSameInDB($post);
        $this->assertSameInDB($comment);
        $this->assertSameInDB($comment2);
        $this->assertSameInDB($comment3);

        /** @var Post $dbPost */
        $dbPost = $this->orm->selector(Post::class)
            ->wherePK($post->primaryKey())
            ->findOne();

        $this->assertSame(1, $this->orm->selector(Post::class)
            ->wherePK($post->primaryKey())
            ->count()
        );

        $this->assertFalse($dbPost->getRelations()->get('comments')->isLoaded());
        $this->assertCount(3, $dbPost->comments);

        $this->assertTrue($dbPost->comments->has($comment));
        $this->assertTrue($dbPost->comments->has($comment2));
        $this->assertTrue($dbPost->comments->has(['message' => 'hi3']));
    }

    public function testSaveAndHasAndPartial()
    {
        $post = new Post();
        $post->author = new User();
        $post->comments->add($comment = new Comment(['message' => 'hi']));
        $post->comments->add($comment2 = new Comment(['message' => 'hi2']));
        $post->comments->add($comment3 = new Comment(['message' => 'hi3']));

        $this->assertTrue($post->comments->has($comment));
        $this->assertTrue($post->comments->has($comment2->getFields()));
        $this->assertTrue($post->comments->has(['message' => 'hi3']));
        $this->assertCount(3, $post->comments);

        $post->save();

        $this->assertSameInDB($post);
        $this->assertSameInDB($comment);
        $this->assertSameInDB($comment2);
        $this->assertSameInDB($comment3);

        /** @var Post $dbPost */
        $dbPost = $this->orm->selector(Post::class)
            ->wherePK($post->primaryKey())
            ->findOne();

        $this->assertSame(1, $this->orm->selector(Post::class)
            ->wherePK($post->primaryKey())
            ->count()
        );


        $this->assertFalse($dbPost->getRelations()->get('comments')->isLoaded());
        $dbPost->comments->partial(true);
        $this->assertTrue($dbPost->comments->isPartial());

        //do not load
        $this->assertCount(0, $dbPost->comments);
        $this->assertTrue($dbPost->getRelations()->get('comments')->isLoaded());

        $this->assertFalse($dbPost->comments->has($comment));
        $this->assertFalse($dbPost->comments->has($comment2->getFields()));
        $this->assertFalse($dbPost->comments->has(['message' => 'hi3']));
    }

    public function testDeleteInSession()
    {
        $post = new Post();
        $post->author = new User();
        $post->comments->add($comment = new Comment(['message' => 'hi']));
        $post->comments->add($comment2 = new Comment(['message' => 'hi2']));
        $post->comments->add($comment3 = new Comment(['message' => 'hi3']));

        $post->comments->delete($comment3);

        $this->assertTrue($post->comments->has($comment));
        $this->assertTrue($post->comments->has($comment2->getFields()));
        $this->assertFalse($post->comments->has(['message' => 'hi3']));
        $this->assertCount(2, $post->comments);

        $post->save();

        $this->assertSameInDB($post);
        $this->assertSameInDB($comment);
        $this->assertSameInDB($comment2);

        $this->assertCount(2, $this->db->comments);
    }

    public function testTransferDetach()
    {
        $post = new Post();
        $post->author = new User();
        $post->comments->add($comment = new Comment(['message' => 'hi']));
        $post->comments->add($comment2 = new Comment(['message' => 'hi2']));
        $post->comments->add($comment3 = new Comment(['message' => 'hi3']));

        $post1 = new Post();
        $post1->author = new User();
        $post1->comments->add($comment4 = new Comment(['message' => 'hi4']));
        $post1->comments->add($comment5 = new Comment(['message' => 'hi5']));
        $post1->comments->add($comment6 = new Comment(['message' => 'hi6']));

        $post->save();
        $post1->save();

        $this->assertEquals($comment4->post_id, $post1->id);

        $post->comments->add(
            $post1->comments->detach($comment4)
        );

        $post->save();
        $post1->save();

        $this->assertSameInDB($comment4);
        $this->assertEquals($comment4->post_id, $post->id);
    }

    public function testDeleteAfterSave()
    {
        $post = new Post();
        $post->author = new User();
        $post->comments->add($comment = new Comment(['message' => 'hi']));
        $post->comments->add($comment2 = new Comment(['message' => 'hi2']));
        $post->comments->add($comment3 = new Comment(['message' => 'hi3']));

        $this->assertTrue($post->comments->has($comment));
        $this->assertTrue($post->comments->has($comment2->getFields()));
        $this->assertTrue($post->comments->has(['message' => 'hi3']));
        $this->assertCount(3, $post->comments);

        $post->save();

        $this->assertCount(3, $this->db->comments);

        $post->comments->delete($comment3);
        $this->assertCount(2, $post->comments);

        $post->save();

        $this->assertTrue($post->comments->has($comment));
        $this->assertTrue($post->comments->has($comment2->getFields()));
        $this->assertFalse($post->comments->has(['message' => 'hi3']));

        $this->assertSameInDB($post);
        $this->assertSameInDB($comment);
        $this->assertSameInDB($comment2);

        $this->assertCount(2, $this->db->comments);
    }

    public function testDeleteAfterSaveReload()
    {
        $post = new Post();
        $post->author = new User();
        $post->comments->add($comment = new Comment(['message' => 'hi']));
        $post->comments->add($comment2 = new Comment(['message' => 'hi2']));
        $post->comments->add($comment3 = new Comment(['message' => 'hi3']));

        $this->assertTrue($post->comments->has($comment));
        $this->assertTrue($post->comments->has($comment2->getFields()));
        $this->assertTrue($post->comments->has(['message' => 'hi3']));
        $this->assertCount(3, $post->comments);

        $post->save();

        $this->assertSame(1, $comment->primaryKey());
        $this->assertSame(2, $comment2->primaryKey());
        $this->assertSame(3, $comment3->primaryKey());

        $this->assertCount(3, $this->db->comments);

        $post->comments->delete($comment3);
        $this->assertCount(2, $post->comments);

        $post->save();

        $post = $this->orm->source(Post::class)->findByPK($post->primaryKey());

        $this->assertTrue($post->comments->has($comment));
        $this->assertTrue($post->comments->has($comment2));
        $this->assertFalse($post->comments->has(['message' => 'hi3']));

        $this->assertSameInDB($post);
        $this->assertSameInDB($comment);
        $this->assertSameInDB($comment2);

        $this->assertCount(2, $this->db->comments);
    }

    public function testMatchOneSession()
    {
        $post = new Post();
        $post->author = new User();
        $post->comments->add($comment = new Comment(['message' => 'hi']));
        $post->comments->add($comment2 = new Comment(['message' => 'hi2']));
        $post->comments->add($comment3 = new Comment(['message' => 'hi3']));

        $this->assertTrue($post->comments->has($comment));
        $this->assertTrue($post->comments->has($comment2->getFields()));
        $this->assertTrue($post->comments->has(['message' => 'hi3']));
        $this->assertCount(3, $post->comments);

        $post->save();

        $this->assertSame($comment, $post->comments->matchOne($comment));
        $this->assertSame($comment, $post->comments->matchOne($comment->primaryKey()));
        $this->assertSame($comment, $post->comments->matchOne(['message' => 'hi']));
    }

    public function testReloadPreloaded()
    {
        $post = new Post();
        $post->author = new User();
        $post->comments->add($comment = new Comment(['message' => 'hi']));
        $post->comments->add($comment2 = new Comment(['message' => 'hi2']));
        $post->comments->add($comment3 = new Comment(['message' => 'hi3']));

        $this->assertTrue($post->comments->has($comment));
        $this->assertTrue($post->comments->has($comment2->getFields()));
        $this->assertTrue($post->comments->has(['message' => 'hi3']));
        $this->assertCount(3, $post->comments);

        $post->save();

        $post = $this->orm->selector(Post::class)
            ->wherePK($post->primaryKey())
            ->load('comments')
            ->findOne();

        $this->assertNotNull($post->comments->matchOne($comment));

        $this->assertSameRecord($comment, $post->comments->matchOne($comment));
        $this->assertSameRecord($comment, $post->comments->matchOne($comment->primaryKey()));
        $this->assertSameRecord($comment, $post->comments->matchOne(['message' => 'hi']));
    }

    public function testReloadButLazy()
    {
        $post = new Post();
        $post->author = new User();
        $post->comments->add($comment = new Comment(['message' => 'hi']));
        $post->comments->add($comment2 = new Comment(['message' => 'hi2']));
        $post->comments->add($comment3 = new Comment(['message' => 'hi3']));

        $this->assertTrue($post->comments->has($comment));
        $this->assertTrue($post->comments->has($comment2->getFields()));
        $this->assertTrue($post->comments->has(['message' => 'hi3']));
        $this->assertCount(3, $post->comments);

        $post->save();

        $post = $this->orm->selector(Post::class)
            ->wherePK($post->primaryKey())
            ->findOne();

        $this->assertSameRecord($comment, $post->comments->matchOne($comment));
        $this->assertSameRecord($comment, $post->comments->matchOne($comment->primaryKey()));
        $this->assertSameRecord($comment, $post->comments->matchOne(['message' => 'hi']));
    }

    public function testReloadButLazyButPartial()
    {
        $post = new Post();
        $post->author = new User();
        $post->comments->add($comment = new Comment(['message' => 'hi']));
        $post->comments->add($comment2 = new Comment(['message' => 'hi2']));
        $post->comments->add($comment3 = new Comment(['message' => 'hi3']));

        $this->assertTrue($post->comments->has($comment));
        $this->assertTrue($post->comments->has($comment2->getFields()));
        $this->assertTrue($post->comments->has(['message' => 'hi3']));
        $this->assertCount(3, $post->comments);

        $post->save();

        $post = $this->orm->selector(Post::class)
            ->wherePK($post->primaryKey())
            ->findOne();

        $post->comments->partial(true);

        $this->assertNull($post->comments->matchOne($comment));
        $this->assertNull($post->comments->matchOne($comment->primaryKey()));
        $this->assertNull($post->comments->matchOne(['message' => 'hi']));
    }

    public function testAddAsPartial()
    {
        $post = new Post();
        $post->author = new User();
        $post->comments->add($comment = new Comment(['message' => 'hi']));
        $post->comments->add($comment2 = new Comment(['message' => 'hi2']));
        $post->comments->add($comment3 = new Comment(['message' => 'hi3']));

        $this->assertCount(3, $post->comments);

        $post->save();

        $post = $this->orm->selector(Post::class)
            ->wherePK($post->primaryKey())
            ->findOne();

        $post->comments->partial(true);
        $post->comments->add($comment4 = new Comment(['message' => 'hi4']));

        $post->save();

        $this->assertSameInDB($comment4);

        $this->assertCount(4, $this->db->comments);
    }

    public function testMatchMultiple()
    {
        $post = new Post();
        $post->author = new User();
        $post->comments->add($comment = new Comment(['message' => 'hi']));
        $post->comments->add($comment2 = new Comment(['message' => 'hi']));
        $post->comments->add($comment3 = new Comment(['message' => 'hi3']));

        $this->assertCount(3, $post->comments);

        $this->assertCount(2, $post->comments->matchMultiple(['message' => 'hi']));
        $this->assertCount(1, $post->comments->matchMultiple(['message' => 'hi3']));
    }

    public function testClean2()
    {
        $post = new Post();
        $post->author = new User();
        $post->comments->add($comment = new Comment(['message' => 'hi']));
        $post->comments->add($comment2 = new Comment(['message' => 'hi']));
        $post->comments->add($comment3 = new Comment(['message' => 'hi3']));

        $post->save();

        $this->assertCount(3, $this->db->comments);

        $post->comments->setRelated(null);
        $this->assertCount(3, $post->comments->getDeleted());
        $post->save();

        $this->assertCount(0, $this->db->comments);
    }

    public function testCleanX()
    {
        $post = new Post();
        $post->author = new User();
        $post->comments->add($comment = new Comment(['message' => 'hi']));
        $post->comments->add($comment2 = new Comment(['message' => 'hi']));
        $post->comments->add($comment3 = new Comment(['message' => 'hi3']));

        $post->save();

        $this->assertCount(3, $this->db->comments);
        $post = $this->orm->source(Post::class)->findByPK($post->primaryKey());

        $post->comments->setRelated(null);
        $this->assertCount(3, $post->comments->getDeleted());
        $post->save();

        $this->assertCount(0, $this->db->comments);
    }

    public function testCleanY()
    {
        $post = new Post();
        $post->author = new User();
        $post->comments->add($comment = new Comment(['message' => 'hi']));
        $post->comments->add($comment2 = new Comment(['message' => 'hi']));
        $post->comments->add($comment3 = new Comment(['message' => 'hi3']));

        $post->save();

        $this->assertCount(3, $this->db->comments);
        $post = $this->orm->source(Post::class)->findByPK($post->primaryKey());

        $post->comments = [];
        $this->assertCount(3, $post->comments->getDeleted());
        $post->save();

        $this->assertCount(0, $this->db->comments);
    }

    public function testReassign()
    {
        $post = new Post();
        $post->author = new User();
        $post->comments->add($comment = new Comment(['message' => 'hi']));
        $post->comments->add($comment2 = new Comment(['message' => 'hi']));
        $post->comments->add($comment3 = new Comment(['message' => 'hi3']));

        $post->save();
        $this->assertCount(3, $this->db->comments);

        $post->comments->setRelated([$comment3, null], true);
        $post->save();

        $this->assertCount(0, $post->comments->getDeleted());
        $this->assertCount(1, $this->db->comments);
    }

    public function testCleanInSession()
    {
        $post = new Post();
        $post->author = new User();
        $post->comments->add($comment = new Comment(['message' => 'hi']));
        $post->comments->add($comment2 = new Comment(['message' => 'hi']));
        $post->comments->add($comment3 = new Comment(['message' => 'hi3']));
        $post->save();

        $this->assertCount(3, $this->db->comments);
        $this->assertCount(3, $post->comments);
        $post->comments = [];
        $this->assertCount(0, $post->comments);
        $post->save();
        $this->assertCount(0, $this->db->comments);
    }

    public function testCleanReloaded()
    {
        $post = new Post();
        $post->author = new User();
        $post->comments->add($comment = new Comment(['message' => 'hi']));
        $post->comments->add($comment2 = new Comment(['message' => 'hi']));
        $post->comments->add($comment3 = new Comment(['message' => 'hi3']));
        $post->save();

        $this->assertCount(3, $this->db->comments);

        $post = $this->orm->selector(Post::class)
            ->wherePK($post->primaryKey())
            ->findOne();

        $this->assertCount(3, $post->comments);
        $post->comments = [];
        $this->assertCount(0, $post->comments);
        $post->save();
        $this->assertCount(0, $this->db->comments);
    }

    public function testLoadPartialPostload()
    {
        $post = new Post();
        $post->author = new User();
        $post->comments->add($comment = new Comment(['message' => 'hi']));
        $post->comments->add($comment2 = new Comment(['message' => 'hi']));
        $post->comments->add($comment3 = new Comment(['message' => 'hi3']));
        $post->save();

        $this->assertCount(3, $this->db->comments);

        $post = $this->orm->selector(Post::class)
            ->wherePK($post->primaryKey())
            ->load('comments', [
                'method' => RelationLoader::POSTLOAD,
                'where'  => ['{@}.message' => 'hi']
            ])
            ->findOne();

        $this->assertCount(2, $post->comments);

        $this->assertTrue($post->comments->has($comment));
        $this->assertTrue($post->comments->has($comment2));
    }

    public function testLoadPartialInload()
    {
        $post = new Post();
        $post->author = new User();
        $post->comments->add($comment = new Comment(['message' => 'hi']));
        $post->comments->add($comment2 = new Comment(['message' => 'hi']));
        $post->comments->add($comment3 = new Comment(['message' => 'hi3']));
        $post->save();

        $this->assertCount(3, $this->db->comments);

        $post = $this->orm->selector(Post::class)
            ->wherePK($post->primaryKey())
            ->load('comments', [
                'method' => RelationLoader::INLOAD,
                'where'  => [
                    '{@}.message' => 'hi'
                ]
            ])
            ->findOne();

        $this->assertCount(2, $post->comments);

        $this->assertTrue($post->comments->has($comment));
        $this->assertTrue($post->comments->has($comment2));
    }

    public function testWith()
    {
        $post = new Post();
        $post->author = new User();
        $post->comments->add($comment = new Comment(['message' => 'hi']));
        $post->comments->add($comment2 = new Comment(['message' => 'hi2']));
        $post->save();

        $post2 = new Post();
        $post2->author = new User();
        $post2->comments->add($comment = new Comment(['message' => 'hi']));
        $post2->comments->add($comment3 = new Comment(['message' => 'hi3']));
        $post2->save();

        $this->assertCount(2, $this->db->posts);
        $this->assertCount(4, $this->db->comments);

        $this->assertSame(2, $this->orm->selector(Post::class)
            ->with('comments')
            ->count());

        $this->assertSame(2, $this->orm->selector(Post::class)
            ->with('comments', ['where' => ['{@}.message' => 'hi']])
            ->count());

        $this->assertSame(1, $this->orm->selector(Post::class)
            ->with('comments', ['where' => ['{@}.message' => 'hi3']])
            ->count());

        $this->assertSameRecord($post, $this->orm->selector(Post::class)
            ->with('comments', ['where' => ['{@}.message' => 'hi2']])
            ->findOne());

        $this->assertSameRecord($post2, $this->orm->selector(Post::class)
            ->with('comments', ['where' => ['{@}.message' => 'hi3']])
            ->findOne());
    }

    public function testWithPartialUsing()
    {
        $post = new Post();
        $post->author = new User();
        $post->comments->add($comment = new Comment(['message' => 'hi']));
        $post->comments->add($comment2 = new Comment(['message' => 'hi', 'approved' => true]));
        $post->comments->add($comment3 = new Comment(['message' => 'hi3']));
        $post->save();

        $this->assertCount(3, $this->db->comments);

        $this->assertSame(1, $this->orm->selector(Post::class)
            ->with('approved_comments')
            ->count());

        $post = $this->orm->selector(Post::class)
            ->wherePK($post->primaryKey())
            ->with('approved_comments', ['alias' => 'ac'])
            ->load('comments', ['using' => 'ac'])
            ->findOne();

        $this->assertCount(1, $post->comments);
        $this->assertCount(1, $post->approved_comments);

        $this->assertFalse($post->comments->has($comment));
        $this->assertTrue($post->comments->has($comment2));
    }

    public function testClearPartial()
    {
        $post = new Post();
        $post->author = new User();
        $post->comments->add($comment = new Comment(['message' => 'hi']));
        $post->comments->add($comment2 = new Comment(['message' => 'hi']));
        $post->comments->add($comment3 = new Comment(['message' => 'hi3']));
        $post->save();

        $this->assertCount(3, $this->db->comments);

        $post = $this->orm->selector(Post::class)
            ->wherePK($post->primaryKey())
            ->load('comments', [
                'method' => RelationLoader::POSTLOAD,
                'where'  => ['{@}.message' => 'hi']
            ])
            ->findOne();

        $this->assertCount(2, $post->comments);
        $post->comments = [];
        $this->assertCount(0, $post->comments);
        $post->save();

        $this->assertCount(1, $this->db->comments);
    }

    public function testTransfer()
    {
        $post = new Post();
        $post->author = new User();
        $post->comments->add($comment = new Comment(['message' => 'hi']));
        $post->save();

        $post2 = new Post();
        $post2->author = new User();
        $post2->save();

        $this->assertCount(2, $this->db->posts);
        $this->assertCount(1, $this->db->comments);

        $post2->comments->add($comment);
        $post2->save();

        $this->assertCount(2, $this->db->posts);
        $this->assertCount(1, $this->db->comments);

        $this->assertSameInDB($comment);

        $post2 = $this->orm->selector(Post::class)
            ->wherePK($post2->primaryKey())
            ->findOne();

        $this->assertTrue($post2->comments->has($comment));

        $post = $this->orm->selector(Post::class)
            ->wherePK($post->primaryKey())
            ->findOne();

        $this->assertFalse($post->comments->has($comment));
    }

    public function testLimit()
    {
        $post = new Post();
        $post->author = new User();
        $post->comments->add($comment = new Comment(['message' => 'hi1']));
        $post->comments->add($comment2 = new Comment(['message' => 'hi2', 'approved' => true]));
        $post->comments->add($comment3 = new Comment(['message' => 'hi3']));
        $post->save();

        $this->assertCount(3, $this->db->comments);

        $post = $this->orm->selector(Post::class)
            ->wherePK($post->primaryKey())
            ->load('comments', ['limit' => 2, 'orderBy' => ['{@}.id' => 'ASC']])
            ->findOne();

        $this->assertCount(2, $post->comments);

        $this->assertTrue($post->comments->has($comment));
        $this->assertTrue($post->comments->has($comment2));
        $this->assertFalse($post->comments->has($comment3));
    }

    public function testLimitReversed()
    {
        $post = new Post();
        $post->author = new User();
        $post->comments->add($comment = new Comment(['message' => 'hi']));
        $post->comments->add($comment2 = new Comment(['message' => 'hi', 'approved' => true]));
        $post->comments->add($comment3 = new Comment(['message' => 'hi3']));
        $post->save();

        $this->assertCount(3, $this->db->comments);

        $post = $this->orm->selector(Post::class)
            ->wherePK($post->primaryKey())
            ->load('comments', ['limit' => 2, 'orderBy' => ['{@}.id' => 'DESC']])
            ->findOne();

        $this->assertCount(2, $post->comments);

        $this->assertFalse($post->comments->has($comment));
        $this->assertTrue($post->comments->has($comment2));
        $this->assertTrue($post->comments->has($comment3));
    }
}