<?php
/**
 * Spiral, Core Components
 *
 * @author Wolfy-J
 */

namespace Spiral\Tests\ORM\Fixtures;

class Supertag extends BaseRecord
{
    const SCHEMA = [
        'id'     => 'primary',
        'name'   => 'string(32)',
        'tagged' => [
            self::MANY_TO_MORPHED => TaggableInterface::class,
            self::INVERSE         => 'supertags'
        ]
    ];
}