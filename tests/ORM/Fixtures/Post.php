<?php
/**
 * spiral-empty.dev
 *
 * @author Wolfy-J
 */

namespace Spiral\Tests\ORM\Fixtures;

class Post extends BaseRecord implements PicturedInterface, LabelledInterface, TaggableInterface
{
    const SCHEMA = [
        'id'      => 'bigPrimary',
        'title'   => 'string(64)',
        'content' => 'text',
        'public'  => 'bool',

        'comments'          => [
            self::HAS_MANY    => Comment::class,
            Comment::INVERSE  => 'post',
            Comment::NULLABLE => false
        ],

        //Only approved comments
        'approved_comments' => [
            self::HAS_MANY    => Comment::class,
            Comment::INVERSE  => 'post',
            Comment::NULLABLE => false,
            Comment::WHERE    => ['{@}.approved' => true]
        ],

        'tags' => [
            self::MANY_TO_MANY  => Tag::class,
            self::PIVOT_COLUMNS => [
                'time_linked' => 'datetime',
                'magic'       => 'bool'
            ],
            Tag::INVERSE        => 'posts'
        ],

        'magic_tags' => [
            self::MANY_TO_MANY  => Tag::class,
            self::PIVOT_COLUMNS => [
                'time_linked' => 'datetime',
                'magic'       => 'bool'
            ],
            self::WHERE_PIVOT   => ['{@}.magic' => true]
        ]
    ];
}