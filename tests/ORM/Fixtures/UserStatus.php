<?php
/**
 * Spiral, Core Components
 *
 * @author Wolfy-J
 */

namespace Spiral\Tests\ORM\Fixtures;

use Spiral\ORM\Columns\EnumColumn;

class UserStatus extends EnumColumn
{
    //Values
    const ACTIVE   = 'active';
    const DISABLED = 'disabled';

    //Definition
    const VALUES  = [self::ACTIVE, self::DISABLED];
    const DEFAULT = self::ACTIVE;
}