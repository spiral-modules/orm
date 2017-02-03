<?php
/**
 * components
 *
 * @author    Wolfy-J
 */

namespace Spiral\Tests\ORM\Fixtures;

use Spiral\ORM\Record;

class LateOne extends Record
{
    const SCHEMA = [
        'id' => 'primary',

        'target' => [
            self::HAS_ONE      => TargetInterface::class,
            self::LATE_BINDING => true
        ]
    ];
}