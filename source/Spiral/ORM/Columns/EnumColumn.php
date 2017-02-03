<?php
/**
 * Spiral, Core Components
 *
 * @author Wolfy-J
 */

namespace Spiral\ORM\Columns;

use Spiral\Core\Component;
use Spiral\Database\Schemas\Prototypes\AbstractColumn;
use Spiral\ORM\ColumnInterface;
use Spiral\ORM\Exceptions\AccessException;
use Spiral\ORM\Exceptions\EnumException;
use Spiral\ORM\RecordAccessorInterface;

/**
 * Mocks enums values and provides ability to describe associated AbstractColumn via set of
 * configuration constants.
 *
 * Extends Component so you can connect scope specific functionality.
 */
class EnumColumn extends Component implements RecordAccessorInterface, ColumnInterface
{
    /**
     * Set of allowed enum values.
     */
    const VALUES  = [];

    /**
     * Default value.
     */
    const DEFAULT = null;

    /**
     * @var bool
     */
    private $changed = false;

    /**
     * @var string
     */
    private $value;

    /**
     * {@inheritdoc}
     */
    public function __construct(string $value)
    {
        $this->value = $value;
    }

    /**
     * {@inheritdoc}
     */
    public function setValue($data)
    {
        if (!in_array($data, static::VALUES)) {
            throw new AccessException("Unable to set enum value, invalid value given");
        }

        $this->value = $data;
        $this->changed = true;
    }

    /**
     * {@inheritdoc}
     */
    public function packValue(): string
    {
        return $this->value;
    }

    /**
     * {@inheritdoc}
     */
    public function hasChanges(): bool
    {
        return $this->changed;
    }

    /**
     * {@inheritdoc}
     */
    public function flushChanges()
    {
        $this->changed = false;
    }

    /**
     * {@inheritdoc}
     */
    public function compileUpdates(string $field = '')
    {
        return $this->value;
    }

    /**
     * {@inheritdoc}
     */
    public function __toString()
    {
        return $this->packValue();
    }

    /**
     * {@inheritdoc}
     */
    public function jsonSerialize()
    {
        return $this->packValue();
    }

    /**
     * {@inheritdoc}
     */
    public static function describeColumn(AbstractColumn $column)
    {
        if (empty(static::VALUES)) {
            throw new EnumException("Unable to describe enum column, no values are given");
        }

        $column->enum(static::VALUES);

        if (!empty(static::DEFAULT)) {
            $column->defaultValue(static::DEFAULT)->nullable(false);
        }
    }
}