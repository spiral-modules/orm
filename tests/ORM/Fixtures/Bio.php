<?php
/**
 * Created by PhpStorm.
 * User: Valentin
 * Date: 23.11.2017
 * Time: 12:30
 */

namespace Spiral\Tests\ORM\Fixtures;

class Bio extends BaseRecord
{
    const TABLE  = "bio";
    const SCHEMA = [
        'id'  => 'bigPrimary',
        'bio' => 'text'
    ];
}