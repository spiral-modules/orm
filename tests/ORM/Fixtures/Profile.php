<?php
/**
 * Spiral, Core Components
 *
 * @author Wolfy-J
 */
namespace Spiral\Tests\ORM\Fixtures;

class Profile extends BaseRecord
{
    const SCHEMA = [
        'id'  => 'bigPrimary',
        'bio' => 'text'
    ];
}