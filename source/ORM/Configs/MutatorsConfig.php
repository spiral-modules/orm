<?php
/**
 * Spiral Framework.
 *
 * @license MIT
 * @author  Anton Titov (Wolfy-J)
 */

namespace Spiral\ORM\Configs;

use Spiral\Core\InjectableConfig;
use Spiral\Core\Traits\Config\AliasTrait;

/**
 *  Provides set of rules and aliases for automatic mutations for Record fields.
 */
class MutatorsConfig extends InjectableConfig
{
    use AliasTrait;

    /**
     * Configuration section.
     */
    const CONFIG = 'schemas/records';

    /**
     * @var array
     */
    protected $config = [
        'aliases'  => [],
        'mutators' => []
    ];

    /**
     * Get list of mutators associated with given field type.
     *
     * @param string $type
     *
     * @return array
     */
    public function getMutators(string $type): array
    {
        $type = $this->resolveAlias($type);
        $mutators = $this->config['mutators'];

        return isset($mutators[$type]) ? $mutators[$type] : [];
    }
}
