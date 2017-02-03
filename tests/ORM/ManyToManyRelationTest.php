<?php
/**
 * Spiral, Core Components
 *
 * @author Wolfy-J
 */
namespace Spiral\Tests\ORM;

use Spiral\ORM\Entities\Loaders\RelationLoader;
use Spiral\ORM\Entities\Relations\ManyToManyRelation;
use Spiral\ORM\Transaction;
use Spiral\Tests\ORM\Fixtures\Node;
use Spiral\Tests\ORM\Fixtures\Post;
use Spiral\Tests\ORM\Fixtures\Tag;
use Spiral\Tests\ORM\Fixtures\User;

abstract class ManyToManyRelationTest extends BaseTest
{
    public function testInstance()
    {
        $post = new Post();
        $this->assertFalse($post->getRelations()->get('tags')->isLoaded());
        $this->assertTrue(empty($post->tags));

        $this->assertInstanceOf(ManyToManyRelation::class, $post->tags);
        $this->assertFalse($post->tags->isLeading());

        //Force loading
        $this->assertCount(0, $post->tags);
        $this->assertTrue($post->tags->isLoaded());

        //But still empty
        $this->assertTrue(empty($post->tags));
    }

    public function testAddLinkedNoSave()
    {
        $post = new Post();
        $post->tags->link(new Tag(['name' => 'tag a']));
        $post->tags->link(new Tag(['name' => 'tag b']));

        $this->assertFalse(empty($post->tags));
        $this->assertCount(2, $post->tags);
    }

    public function testMatchOneNull()
    {
        $post = new Post();
        $post->tags->link($tag1 = new Tag(['name' => 'tag a']));
        $post->tags->link($tag2 = new Tag(['name' => 'tag b']));

        $this->assertSame(null, $post->tags->matchOne(null));
        $this->assertFalse($post->tags->has(null));
    }

    public function testMatchOneEntity()
    {
        $post = new Post();
        $post->tags->link($tag1 = new Tag(['name' => 'tag a']));
        $post->tags->link($tag2 = new Tag(['name' => 'tag b']));

        $this->assertSame($tag1, $post->tags->matchOne($tag1));
        $this->assertTrue($post->tags->has($tag1));

    }

    public function testMatchOneQuery()
    {
        $post = new Post();
        $post->tags->link($tag1 = new Tag(['name' => 'tag a']));
        $post->tags->link($tag2 = new Tag(['name' => 'tag b']));

        $this->assertSame($tag1, $post->tags->matchOne(['name' => 'tag a']));
        $this->assertTrue($post->tags->has(['name' => 'tag a']));

        $this->assertSame(
            [$tag1],
            iterator_to_array($post->tags->matchMultiple(['name' => 'tag a']))
        );
    }

    public function testUnlinkInSession()
    {
        $post = new Post();
        $post->tags->link($tag1 = new Tag(['name' => 'tag a']));
        $post->tags->link($tag2 = new Tag(['name' => 'tag b']));

        $this->assertFalse(empty($post->tags));
        $this->assertCount(2, $post->tags);

        $post->tags->unlink($tag1);

        $this->assertFalse(empty($post->tags));
        $this->assertCount(1, $post->tags);

        $post->tags->unlink($tag2);

        $this->assertTrue(empty($post->tags));
        $this->assertCount(0, $post->tags);
    }

    public function testCreateWithLinking()
    {
        $post = new Post();
        $post->author = new User();
        $post->tags->link($tag1 = new Tag(['name' => 'tag a']));
        $post->tags->link($tag2 = new Tag(['name' => 'tag b']));

        $post->save();
        $this->assertSameInDB($post);
        $this->assertSameInDB($post->author);
        $this->assertSameInDB($tag1);
        $this->assertSameInDB($tag2);

        $this->assertCount(2, $this->db->post_tag_map);
    }

    public function testCreateAndLinkWithExisted()
    {
        $tag2 = new Tag(['name' => 'tag b']);
        $tag2->save();

        $post = new Post();
        $post->author = new User();
        $post->tags->link($tag1 = new Tag(['name' => 'tag a']));
        $post->tags->link($tag2);

        $post->save();
        $this->assertSameInDB($post);
        $this->assertSameInDB($post->author);
        $this->assertSameInDB($tag1);
        $this->assertSameInDB($tag2);

        $this->assertCount(2, $this->db->post_tag_map);
    }

    public function testSaveTwice()
    {
        $tag2 = new Tag(['name' => 'tag b']);
        $tag2->save();

        $post = new Post();
        $post->author = new User();
        $post->tags->link($tag1 = new Tag(['name' => 'tag a']));
        $post->tags->link($tag2);

        $post->save();
        $post->save();

        $this->assertCount(2, $this->db->post_tag_map);

        $post->tags->link($tag3 = new Tag(['name' => 'tag c']));

        $post->save();
        $this->assertCount(3, $this->db->post_tag_map);
    }

    public function testPivotValues()
    {
        $tag1 = new Tag(['name' => 'tag a']);

        $post = new Post();
        $post->author = new User();

        $post->tags->link($tag1);
        $this->assertEmpty($pivot = $post->tags->getPivot($tag1));
        $post->save();

        $this->assertNotEmpty($pivot = $post->tags->getPivot($tag1));
        $this->assertEquals($post->primaryKey(), $pivot['post_id']);
        $this->assertEquals($tag1->primaryKey(), $pivot['tag_id']);
    }

    public function testUnlinkTransaction()
    {
        $transaction = new Transaction();

        $post = new Post();
        $post->author = new User();
        $post->tags->link($tag1 = new Tag(['name' => 'tag a']));
        $post->tags->link($tag2 = new Tag(['name' => 'tag b']));

        $post->save($transaction);

        $post->tags->unlink($tag2);

        $post->save($transaction);

        $transaction->run();

        $this->assertSameInDB($post);
        $this->assertSameInDB($post->author);
        $this->assertSameInDB($tag1);
        $this->assertSameInDB($tag2);

        $this->assertCount(2, $this->db->tags);
        $this->assertCount(1, $this->db->post_tag_map);
    }

    public function testLoadInload()
    {
        $post = new Post();
        $post->author = new User();
        $post->tags->link($tag1 = new Tag(['name' => 'tag a']));
        $post->tags->link($tag2 = new Tag(['name' => 'tag b']));

        $post->save();
        $this->assertCount(2, $this->db->post_tag_map);

        $post = $this->orm->source(Post::class)->find()
            ->load('tags', ['method' => RelationLoader::INLOAD])
            ->wherePK($post->primaryKey())
            ->findOne();

        $this->assertTrue($post->getRelations()->get('tags')->isLoaded());
        $this->assertCount(2, $post->tags);

        $this->assertTrue($post->tags->has($tag1));
        $this->assertTrue($post->tags->has($tag2));

        $this->assertNotEmpty($post->tags->getPivot($tag1));
        $this->assertNotEmpty($post->tags->getPivot($tag2));
    }

    public function testLoadInloadPartial()
    {
        $post = new Post();
        $post->author = new User();
        $post->tags->link($tag1 = new Tag(['name' => 'tag a']));
        $post->tags->link($tag2 = new Tag(['name' => 'tag b']));

        $post->save();
        $this->assertCount(2, $this->db->post_tag_map);

        $post = $this->orm->source(Post::class)->find()
            ->load('tags', [
                'method' => RelationLoader::INLOAD,
                'where'  => ['{@}.id' => $tag1->primaryKey()]
            ])
            ->load('author', [
                'method' => RelationLoader::INLOAD,
            ])
            ->wherePK($post->primaryKey())
            ->findOne();

        $this->assertTrue($post->getRelations()->get('tags')->isLoaded());
        $this->assertCount(1, $post->tags);

        $this->assertTrue($post->tags->has($tag1));
        $this->assertFalse($post->tags->has($tag2));

        $this->assertNotEmpty($post->tags->getPivot($tag1));
    }

    public function testLoadInloadPartialPivot()
    {
        $post = new Post();
        $post->author = new User();
        $post->tags->link($tag1 = new Tag(['name' => 'tag a']));
        $post->tags->link($tag2 = new Tag(['name' => 'tag b']));

        $post->save();
        $this->assertCount(2, $this->db->post_tag_map);

        $post = $this->orm->source(Post::class)->find()
            ->load('tags', [
                'method'     => RelationLoader::INLOAD,
                'wherePivot' => ['{@}.tag_id' => $tag2->primaryKey()]
            ])
            ->wherePK($post->primaryKey())
            ->findOne();

        $this->assertTrue($post->getRelations()->get('tags')->isLoaded());
        $this->assertCount(1, $post->tags);

        $this->assertTrue($post->tags->has($tag2));
        $this->assertFalse($post->tags->has($tag1));

        $this->assertNotEmpty($post->tags->getPivot($tag2));
    }

    public function testLoadPostload()
    {
        $post = new Post();
        $post->author = new User();
        $post->tags->link($tag1 = new Tag(['name' => 'tag a']));
        $post->tags->link($tag2 = new Tag(['name' => 'tag b']));

        $post->save();
        $this->assertCount(2, $this->db->post_tag_map);

        $post = $this->orm->source(Post::class)->find()
            ->load('tags', ['method' => RelationLoader::POSTLOAD])
            ->wherePK($post->primaryKey())
            ->findOne();

        $this->assertTrue($post->getRelations()->get('tags')->isLoaded());
        $this->assertCount(2, $post->tags);

        $this->assertTrue($post->tags->has($tag1));
        $this->assertTrue($post->tags->has($tag2));

        $this->assertNotEmpty($post->tags->getPivot($tag1));
        $this->assertNotEmpty($post->tags->getPivot($tag2));
    }

    public function testPostloadPartial()
    {
        $post = new Post();
        $post->author = new User();
        $post->tags->link($tag1 = new Tag(['name' => 'tag a']));
        $post->tags->link($tag2 = new Tag(['name' => 'tag b']));

        $post->save();
        $this->assertCount(2, $this->db->post_tag_map);

        $post = $this->orm->source(Post::class)->find()
            ->load('tags', [
                'method' => RelationLoader::POSTLOAD,
                'where'  => ['{@}.id' => $tag1->primaryKey()]
            ])
            ->wherePK($post->primaryKey())
            ->findOne();

        $this->assertTrue($post->getRelations()->get('tags')->isLoaded());
        $this->assertCount(1, $post->tags);

        $this->assertTrue($post->tags->has($tag1));
        $this->assertFalse($post->tags->has($tag2));

        $this->assertNotEmpty($post->tags->getPivot($tag1));
    }

    public function testLoadPostloadPartialPivot()
    {
        $post = new Post();
        $post->author = new User();
        $post->tags->link($tag1 = new Tag(['name' => 'tag a']));
        $post->tags->link($tag2 = new Tag(['name' => 'tag b']));

        $post->save();
        $this->assertCount(2, $this->db->post_tag_map);

        $post = $this->orm->source(Post::class)->find()
            ->load('tags', [
                'method'     => RelationLoader::POSTLOAD,
                'wherePivot' => ['{@}.tag_id' => $tag2->primaryKey()]
            ])
            ->wherePK($post->primaryKey())
            ->findOne();

        $this->assertTrue($post->getRelations()->get('tags')->isLoaded());
        $this->assertCount(1, $post->tags);

        $this->assertTrue($post->tags->has($tag2));
        $this->assertFalse($post->tags->has($tag1));

        $this->assertNotEmpty($post->tags->getPivot($tag2));
    }

    public function testLoadLazy()
    {
        $post = new Post();
        $post->author = new User();
        $post->tags->link($tag1 = new Tag(['name' => 'tag a']));
        $post->tags->link($tag2 = new Tag(['name' => 'tag b']));

        $post->save();
        $this->assertCount(2, $this->db->post_tag_map);

        $post = $this->orm->source(Post::class)->find()
            ->wherePK($post->primaryKey())
            ->findOne();

        $this->assertFalse($post->getRelations()->get('tags')->isLoaded());
        $this->assertCount(2, $post->tags);

        $this->assertTrue($post->tags->has($tag1));
        $this->assertTrue($post->tags->has($tag2));

        $this->assertNotEmpty($post->tags->getPivot($tag1));
        $this->assertNotEmpty($post->tags->getPivot($tag2));
    }

    public function testSetRelated()
    {
        $post = new Post();
        $post->author = new User();

        $post->tags = [
            $tag1 = new Tag(['name' => 'tag a']),
            $tag2 = new Tag(['name' => 'tag b']),
            null
        ];

        $post->save();
        $this->assertCount(2, $this->db->post_tag_map);

        $post = $this->orm->source(Post::class)->find()
            ->wherePK($post->primaryKey())
            ->findOne();

        $this->assertFalse($post->getRelations()->get('tags')->isLoaded());
        $this->assertCount(2, $post->tags);

        $this->assertTrue($post->tags->has($tag1));
        $this->assertTrue($post->tags->has($tag2));

        $this->assertNotEmpty($post->tags->getPivot($tag1));
        $this->assertNotEmpty($post->tags->getPivot($tag2));
    }

    public function testSetExisted()
    {
        $tag1 = new Tag(['name' => 'tag a']);
        $tag1->save();

        $post = new Post();
        $post->author = new User();

        $post->tags = [
            $tag1,
            $tag2 = new Tag(['name' => 'tag b']),
            null
        ];

        $post->save();
        $this->assertCount(2, $this->db->post_tag_map);

        $post = $this->orm->source(Post::class)->find()
            ->wherePK($post->primaryKey())
            ->findOne();

        $this->assertFalse($post->getRelations()->get('tags')->isLoaded());
        $this->assertCount(2, $post->tags);

        $this->assertTrue($post->tags->has($tag1));
        $this->assertTrue($post->tags->has($tag2));

        $this->assertNotEmpty($post->tags->getPivot($tag1));
        $this->assertNotEmpty($post->tags->getPivot($tag2));
    }

    public function testClean()
    {
        $tag1 = new Tag(['name' => 'tag a']);
        $tag1->save();

        $post = new Post();
        $post->author = new User();

        $post->tags = [
            $tag1,
            $tag2 = new Tag(['name' => 'tag b']),
            null
        ];

        $post->save();
        $this->assertCount(2, $this->db->post_tag_map);

        $post->tags = [];

        $post->save();
        $this->assertCount(0, $this->db->post_tag_map);
    }

    public function testCleanPartial()
    {
        $tag1 = new Tag(['name' => 'tag a']);
        $tag1->save();

        $post = new Post();
        $post->author = new User();

        $post->tags = [
            $tag1,
            $tag2 = new Tag(['name' => 'tag b']),
            null
        ];

        $post->save();
        $this->assertCount(2, $this->db->post_tag_map);

        $post->tags = [$tag1];

        $post->save();

        $this->assertCount(1, $post->tags);
        $this->assertTrue($post->tags->has($tag1));
        $this->assertFalse($post->tags->has($tag2));
        $this->assertCount(1, $this->db->post_tag_map);
    }

    public function testCleanInMemory()
    {
        $tag1 = new Tag(['name' => 'tag a']);
        $tag1->save();

        $transaction = new Transaction();

        $post = new Post();
        $post->author = new User();

        $post->tags = [
            $tag1,
            $tag2 = new Tag(['name' => 'tag b']),
            null
        ];

        $post->save($transaction);
        $post->tags = [];
        $post->save($transaction);
        $this->assertCount(0, $post->tags);

        $transaction->run();

        $this->assertCount(0, $this->db->post_tag_map);
        $this->assertCount(0, $post->tags);
    }

    public function testCustomPivot()
    {
        $tag1 = new Tag(['name' => 'tag a']);
        $tag1->save();

        $post = new Post();
        $post->author = new User();

        $post->tags->link($tag1, ['magic' => true]);
        $post->tags->link($tag2 = new Tag(['name' => 'tag b']));

        $post->save();
        $this->assertCount(2, $this->db->post_tag_map);

        $post = $this->orm->source(Post::class)->find()
            ->wherePK($post->primaryKey())
            ->findOne();

        $this->assertFalse($post->getRelations()->get('tags')->isLoaded());
        $this->assertCount(2, $post->tags);
        $this->assertCount(1, $post->magic_tags);

        $this->assertTrue($post->tags->has($tag1));
        $this->assertTrue($post->tags->has($tag2));

        $this->assertNotEmpty($post->tags->getPivot($tag1));
        $this->assertNotEmpty($post->tags->getPivot($tag2));

        $this->assertEquals(false, (bool)$post->tags->getPivot($tag2)['magic']);
        $this->assertEquals(true, (bool)$post->tags->getPivot($tag1)['magic']);
    }

    /**
     * @expectedException \Spiral\ORM\Exceptions\RelationException
     */
    public function testBadPivot()
    {
        $tag1 = new Tag(['name' => 'tag a']);
        $tag1->save();

        $post = new Post();
        $post->author = new User();

        $post->tags->link($tag1, ['bad' => true]);
        $post->tags->link($tag2 = new Tag(['name' => 'tag b']));
    }

    public function testInloadWithWhere()
    {
        $tag1 = new Tag(['name' => 'tag a']);
        $tag1->save();

        $post = new Post();
        $post->author = new User();

        $post->tags->link($tag1, ['magic' => true]);
        $post->tags->link($tag2 = new Tag(['name' => 'tag b']));

        $post->save();
        $this->assertCount(2, $this->db->post_tag_map);

        $post = $this->orm->source(Post::class)->find()
            ->load('tags', [
                'where'  => ['{@}.name' => 'tag a'],
                'method' => RelationLoader::INLOAD
            ])
            ->wherePK($post->primaryKey())
            ->findOne();

        $this->assertCount(1, $post->tags);

        $this->assertTrue($post->tags->has($tag1));
        $this->assertFalse($post->tags->has($tag2));
    }

    public function testInloadWithWherePivot()
    {
        $tag1 = new Tag(['name' => 'tag a']);
        $tag1->save();

        $post = new Post();
        $post->author = new User();

        $post->tags->link($tag1, ['magic' => true]);
        $post->tags->link($tag2 = new Tag(['name' => 'tag b']));

        $post->save();
        $this->assertCount(2, $this->db->post_tag_map);

        $post = $this->orm->source(Post::class)->find()
            ->load('tags', [
                'wherePivot' => ['{@}.magic' => true],
                'method'     => RelationLoader::INLOAD
            ])
            ->wherePK($post->primaryKey())
            ->findOne();

        $this->assertCount(1, $post->tags);

        $this->assertTrue($post->tags->has($tag1));
        $this->assertFalse($post->tags->has($tag2));
    }

    public function testPostloadWithWhere()
    {
        $tag1 = new Tag(['name' => 'tag a']);
        $tag1->save();

        $post = new Post();
        $post->author = new User();

        $post->tags->link($tag1, ['magic' => true]);
        $post->tags->link($tag2 = new Tag(['name' => 'tag b']));

        $post->save();
        $this->assertCount(2, $this->db->post_tag_map);

        $post = $this->orm->source(Post::class)->find()
            ->load('tags', [
                'where'  => ['{@}.name' => 'tag a'],
                'method' => RelationLoader::POSTLOAD
            ])
            ->wherePK($post->primaryKey())
            ->findOne();

        $this->assertCount(1, $post->tags);

        $this->assertTrue($post->tags->has($tag1));
        $this->assertFalse($post->tags->has($tag2));
    }

    public function testPostloadWithWherePivot()
    {
        $tag1 = new Tag(['name' => 'tag a']);
        $tag1->save();

        $post = new Post();
        $post->author = new User();

        $post->tags->link($tag1, ['magic' => true]);
        $post->tags->link($tag2 = new Tag(['name' => 'tag b']));

        $post->save();
        $this->assertCount(2, $this->db->post_tag_map);

        $post = $this->orm->source(Post::class)->find()
            ->load('tags', [
                'wherePivot' => ['{@}.magic' => true],
                'method'     => RelationLoader::POSTLOAD
            ])
            ->wherePK($post->primaryKey())
            ->findOne();

        $this->assertCount(1, $post->tags);

        $this->assertTrue($post->tags->has($tag1));
        $this->assertFalse($post->tags->has($tag2));
    }

    public function testLoadUsingAnotherRelation()
    {
        $tag1 = new Tag(['name' => 'tag a']);
        $tag1->save();

        $post = new Post();
        $post->author = new User();

        $post->tags->link($tag1, ['magic' => true]);
        $post->tags->link($tag2 = new Tag(['name' => 'tag b']));

        $post->save();
        $this->assertCount(2, $this->db->post_tag_map);

        $post = $this->orm->source(Post::class)->find()
            ->with('magic_tags', ['alias' => 'mt'])
            ->load('tags', [
                'using' => 'mt'
            ])
            ->wherePK($post->primaryKey())
            ->findOne();

        $this->assertCount(1, $post->tags);

        $this->assertTrue($post->tags->has($tag1));
        $this->assertFalse($post->tags->has($tag2));
    }

    public function testChangeInSession()
    {
        $tag1 = new Tag(['name' => 'tag a']);
        $tag1->save();

        $transaction = new Transaction();

        $post = new Post();
        $post->author = new User();

        $post->tags->link($tag1, ['magic' => true]);

        $this->assertCount(1, $post->tags);
        $this->assertTrue((bool)$post->tags->getPivot($tag1)['magic']);

        $post->tags->link($tag1, ['magic' => false]);
        $post->save($transaction);

        $transaction->run();

        $this->assertCount(1, $this->db->post_tag_map);

        $post = $this->orm->source(Post::class)->find()
            ->wherePK($post->primaryKey())
            ->findOne();

        $this->assertCount(1, $post->tags);
        $this->assertFalse((bool)$post->tags->getPivot($tag1)['magic']);
    }

    public function testChangePivot()
    {
        $tag1 = new Tag(['name' => 'tag a']);
        $tag1->save();

        $post = new Post();
        $post->author = new User();
        $post->tags->link($tag1, ['magic' => true]);
        $post->save();

        $this->assertCount(1, $this->db->post_tag_map);

        $post = $this->orm->source(Post::class)->find()
            ->wherePK($post->primaryKey())
            ->findOne();

        $this->assertCount(1, $post->tags);
        $this->assertTrue((bool)$post->tags->getPivot($tag1)['magic']);

        $post->tags->link($tag1, ['magic' => false]);

        //Ensure reference
        $tag = $post->tags->matchOne($tag1);
        $tag->name = 'tag x';

        $post->save();

        //Must be updated
        $this->assertSameInDB($tag);

        $this->assertFalse((bool)$post->tags->getPivot($tag1)['magic']);

        $post = $this->orm->source(Post::class)->find()
            ->wherePK($post->primaryKey())
            ->findOne();

        $this->assertCount(1, $post->tags);
        $this->assertFalse((bool)$post->tags->getPivot($tag1)['magic']);
    }

    public function testRecursive()
    {
        $node1 = new Node(['name' => 'node 1']);
        $node1->nodes->link($node2 = new Node(['name' => 'node 2']));
        $node2->nodes->link($node3 = new Node(['name' => 'node 3']));
        $node3->nodes->link($node4 = new Node(['name' => 'node 4']));

        $node1->save();

        $this->assertSameInDB($node1);
        $this->assertSameInDB($node2);
        $this->assertSameInDB($node3);
        $this->assertSameInDB($node4);
    }
}