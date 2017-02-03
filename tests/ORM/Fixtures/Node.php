<?php
/**
 * Spiral, Core Components
 *
 * @author Wolfy-J
 */
namespace Spiral\Tests\ORM\Fixtures;

class Node extends BaseRecord implements TargetInterface
{
    const SCHEMA = [
        'id'    => 'primary',
        'name'  => 'string',
        'nodes' => [
            self::MANY_TO_MANY      => Node::class,
            /*
             * BTW, the only database which told about it was SQLServer.
             */
            self::CREATE_CONSTRAINT => false,
            self::THOUGHT_OUTER_KEY => 'parent_id',
            self::THOUGHT_INNER_KEY => 'child_id'
        ]
    ];
}