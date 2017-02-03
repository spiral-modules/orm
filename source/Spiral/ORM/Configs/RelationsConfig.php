<?php
/**
 * Spiral, Core Components
 *
 * @author Wolfy-J
 */

namespace Spiral\ORM\Configs;

use Spiral\Core\InjectableConfig;

/**
 * Defined classes and behaviours for ORM relations.
 */
class RelationsConfig extends InjectableConfig
{
    /**
     * Configuration section.
     */
    const CONFIG = 'schemas/relations';

    /**
     * Relation sub classes.
     */
    const SCHEMA_CLASS = 'schema';
    const LOADER_CLASS = 'loader';
    const ACCESS_CLASS = 'access';

    /**
     * @var array
     */
    protected $config = [];

    /**
     * @param string $type
     * @param string $section
     *
     * @return bool
     */
    public function hasRelation(string $type, string $section = self::SCHEMA_CLASS): bool
    {
        return isset($this->config[$type][$section]);
    }

    /**
     * @param string $type
     * @param string $section
     *
     * @return string
     */
    public function relationClass(string $type, string $section): string
    {
        return $this->config[$type][$section];
    }
}