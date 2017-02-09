<?php
/**
 * spiral-empty.dev
 *
 * @author Wolfy-J
 */

namespace Spiral\Tests\ORM\Fixtures;

class Tag extends BaseRecord
{
    const DATABASE = 'other';

    const SCHEMA = [
        'id'   => 'primary',
        'name' => 'string(32)'
    ];
}