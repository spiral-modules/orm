<?php
/**
 * Spiral, Core Components
 *
 * @author Wolfy-J
 */

namespace Spiral\ORM\Schemas;

class NullLocator implements LocatorInterface
{
    /**
     * {@inheritdoc}
     */
    public function locateSchemas(): array
    {
        return [];
    }

    /**
     * {@inheritdoc}
     */
    public function locateSources(): array
    {
        return [];
    }
}