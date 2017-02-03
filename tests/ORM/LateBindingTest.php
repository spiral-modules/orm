<?php
/**
 * components
 *
 * @author    Wolfy-J
 */

namespace Spiral\Tests\ORM;

use Spiral\Tests\ORM\Fixtures\Comment;
use Spiral\Tests\ORM\Fixtures\LateOne;
use Spiral\Tests\ORM\Fixtures\LateTwo;
use Spiral\Tests\ORM\Fixtures\Node;
use Spiral\Tests\ORM\Fixtures\Post;
use Spiral\Tests\ORM\Fixtures\Profile;
use Spiral\Tests\ORM\Fixtures\Tag;
use Spiral\Tests\ORM\Fixtures\User;

abstract class LateBindingTest extends BaseTest
{
    const MODELS  = [];
    const SOURCES = [];

    public function testBindByRole()
    {
        $builder = $this->orm->schemaBuilder(false);

        $builder->addSchema($this->makeSchema(User::class));
        $builder->addSchema($this->makeSchema(Profile::class));
        $builder->addSchema($this->makeSchema(Post::class));
        $builder->addSchema($this->makeSchema(Comment::class));
        $builder->addSchema($this->makeSchema(Tag::class));
        $builder->addSchema($this->makeSchema(LateTwo::class));

        $builder->renderSchema();
        $builder->pushSchema();

        $this->orm->setSchema($builder);

        $lateTwo = new LateTwo();
        $this->assertInstanceOf(User::class, $lateTwo->target);
    }

    /**
     * @expectedException \Spiral\ORM\Exceptions\DefinitionException
     * @expectedExceptionMessage Unable to locate outer record of
     *                           'user' for late binded relation
     *                           Spiral\Tests\ORM\Fixtures\LateOne.target
     */
    public function testLocateNone()
    {
        $builder = $this->orm->schemaBuilder(false);

        $builder->addSchema($this->makeSchema(LateTwo::class));

        $builder->renderSchema();
        $builder->pushSchema();

        $this->orm->setSchema($builder);
    }

    public function testBindByInterface()
    {
        $builder = $this->orm->schemaBuilder(false);

        $builder->addSchema($this->makeSchema(User::class));
        $builder->addSchema($this->makeSchema(Profile::class));
        $builder->addSchema($this->makeSchema(Post::class));
        $builder->addSchema($this->makeSchema(Comment::class));
        $builder->addSchema($this->makeSchema(Tag::class));
        $builder->addSchema($this->makeSchema(LateOne::class));

        $builder->renderSchema();
        $builder->pushSchema();

        $this->orm->setSchema($builder);

        $lateTwo = new LateOne();
        $this->assertInstanceOf(User::class, $lateTwo->target);
    }

    /**
     * @expectedException \Spiral\ORM\Exceptions\DefinitionException
     * @expectedExceptionMessage Unable to locate outer record of
     *                           'Spiral\Tests\ORM\Fixtures\TargetInterface' for late binded
     *                           relation Spiral\Tests\ORM\Fixtures\LateOne.target
     */
    public function testLocaleNoneByInterface()
    {
        $builder = $this->orm->schemaBuilder(false);

        $builder->addSchema($this->makeSchema(LateOne::class));

        $builder->renderSchema();
        $builder->pushSchema();

        $this->orm->setSchema($builder);
    }

    /**
     * @expectedException \Spiral\ORM\Exceptions\DefinitionException
     * @expectedExceptionMessage Ambiguous target of 'Spiral\Tests\ORM\Fixtures\TargetInterface'
     *                           for late binded relation Spiral\Tests\ORM\Fixtures\LateOne.target
     */
    public function testLocateMultiple()
    {
        $builder = $this->orm->schemaBuilder(false);

        $builder->addSchema($this->makeSchema(User::class));
        $builder->addSchema($this->makeSchema(Profile::class));
        $builder->addSchema($this->makeSchema(Post::class));
        $builder->addSchema($this->makeSchema(Comment::class));
        $builder->addSchema($this->makeSchema(Tag::class));
        $builder->addSchema($this->makeSchema(Node::class));
        $builder->addSchema($this->makeSchema(LateOne::class));

        $builder->renderSchema();
        $builder->pushSchema();

        $this->orm->setSchema($builder);

        $lateTwo = new LateOne();
        $this->assertInstanceOf(User::class, $lateTwo->target);
    }
}