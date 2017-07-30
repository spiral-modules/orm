Spiral ORM
========
[![Latest Stable Version](https://poser.pugx.org/spiral/orm/v/stable)](https://packagist.org/packages/spiral/orm) 
[![License](https://poser.pugx.org/spiral/orm/license)](https://packagist.org/packages/spiral/orm)
[![Build Status](https://travis-ci.org/spiral/orm.svg?branch=master)](https://travis-ci.org/spiral/orm)
[![Scrutinizer Code Quality](https://scrutinizer-ci.com/g/spiral/orm/badges/quality-score.png?b=master)](https://scrutinizer-ci.com/g/spiral/orm/?branch=master)
[![Coverage Status](https://coveralls.io/repos/github/spiral/orm/badge.svg?branch=master)](https://coveralls.io/github/spiral/orm?branch=master)

<b>[Full Documentation](http://spiral-framework.com/guide)</b> | [CHANGELOG](/CHANGELOG.md)

# Documentation
 * [Overview](https://spiral-framework.com/guide/orm/overview.md)
 * [Record and RecordEntity](https://spiral-framework.com/guide/orm/entities.md)
 * [Repositories and Selectors](https://spiral-framework.com/guide/orm/repositories.md)
 * [Accessors and Filters](https://spiral-framework.com/guide/orm/accessors.md)
 * [Column Objects](https://spiral-framework.com/guide/orm/columns.md)
 * [Scaffolding and Migrations](https://spiral-framework.com/guide/orm/scaffolding.md)
 * [Transactions](https://spiral-framework.com/guide/orm/transactions.md)
 * [Relations](https://spiral-framework.com/guide/orm/relations.md)
 * [Morphed Relations](https://spiral-framework.com/guide/orm/morphed-relations.md)
 * [Pre-compiled Relations](https://spiral-framework.com/guide/orm/late-binding.md)
 * [Query Models](https://spiral-framework.com/guide/orm/query.md)
 * [Eager loading](https://spiral-framework.com/guide/orm/loading.md)
 * [Recursive Relations](https://spiral-framework.com/guide/orm/recursive-relations.md)
 * [Hybrid Databases](https://spiral-framework.com/guide/orm/odm-bridge.md)
 * [Custom Relations](https://spiral-framework.com/guide/orm/custom-relations.md)

# Examples

```php
class Post extends RecordEntity
{
    use TimestampsTrait;

    //Database partitions, isolation and aliasing
    const DATABASE = 'blog';

    const SCHEMA = [
        'id'     => 'bigPrimary',
        'title'  => 'string(64)',
        'status' => 'enum(published,draft)',
        'body'   => 'text',
        
        //Simple relation definitions
        'comments' => [self::HAS_MANY => Comment::class],
        
        //Not very simple relation definitions
        'collaborators' => [
            self::MANY_TO_MANY  => User::class,
            self::PIVOT_TABLE   => 'post_collaborators_map',
            self::PIVOT_COLUMNS => [
                'time_assigned' => 'datetime',
                'type'          => 'string, nullable',
            ],
            User::INVERSE       => 'collaborated_posts'
        ],
        
        //Pre-compiled relations
        'author'   => [
            self::BELONGS_TO   => AuthorInterface::class,
            self::LATE_BINDING => true
        ],
               
        //Hybrid databases
        'metadata' => [
            Document::ONE => Mongo\Metadata::class
        ]
    ];
}
```

```php
$posts = $postSource->find()->distinct()
    ->with('comments', ['where' => ['{@}.approved' => true]]) //Automatic joins
    ->with('author')->where('author_name', 'LIKE', $authorName) //Fluent
    ->load('comments.author') //Cascade eager-loading (joins or external query)
    ->paginate(10) //Quick pagination using active request
    ->getIterator();

foreach ($posts as $post) {
    echo $post->author->getName();
}
```

```php
$post = new Post();
$post->publish_at = 'tomorrow 8am';
$post->author = new User(['name' => 'Antony']);

$post->tags->link(new Tag(['name' => 'tag A']));
$post->tags->link($tags->findOne(['name' => 'tag B']));

$transaction = new Transaction();
$transaction->store($post);
$transaction->run();

//--or--: Active record (optional)
$post->save();

//--or--: request specific transaction
$this->transaction->store($post);
```
