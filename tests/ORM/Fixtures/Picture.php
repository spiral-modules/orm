<?php
/**
 * Spiral, Core Components
 *
 * @author Wolfy-J
 */

namespace Spiral\Tests\ORM\Fixtures;

use Spiral\ORM\Record;

class Picture extends BaseRecord
{
    const SCHEMA = [
        'id'     => 'primary',
        'url'    => 'string',
        'parent' => [
            self::BELONGS_TO_MORPHED => PicturedInterface::class,
            self::INVERSE            => [Record::HAS_ONE, 'picture']
        ]
    ];
}