<?php
/**
 * components
 *
 * @author    Wolfy-J
 */

namespace Spiral\Tests\ORM\Fixtures;

use Spiral\ORM\Record;

class LateTwo extends Record
{
    const SCHEMA = [
        'id' => 'primary',

        'target' => [
            self::HAS_ONE      => 'user',
            self::LATE_BINDING => true
        ]
    ];
}