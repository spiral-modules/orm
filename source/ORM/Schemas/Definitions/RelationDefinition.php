<?php
/**
 * Spiral, Core Components
 *
 * @author Wolfy-J
 */

namespace Spiral\ORM\Schemas\Definitions;

use Spiral\ORM\Exceptions\ORMException;
use Spiral\ORM\Exceptions\SchemaException;
use Spiral\ORM\RecordEntity;

/**
 * Defines relation in schema.
 */
final class RelationDefinition
{
    /**
     * Relation name.
     *
     * @var string
     */
    private $name;

    /**
     * @var string
     */
    private $type;

    /**
     * @var string
     */
    private $target;

    /**
     * @var array
     */
    private $options = [];

    /**
     * Name or definition or inversion.
     *
     * @var string|array
     */
    private $inverse = null;

    /**
     * Defines where relation comes from.
     *
     * @var RelationContext
     */
    private $sourceContext;

    /**
     * Defines where relation points to (if any).
     *
     * @var RelationContext|null
     */
    private $targetContext;

    /**
     * @param string       $name
     * @param string       $type
     * @param string       $target
     * @param array        $options
     * @param string|array $inverse Name or definition of relation to inversed to.
     */
    public function __construct(
        string $name,
        string $type,
        string $target,
        array $options,
        $inverse = null
    ) {
        $this->name = $name;
        $this->type = $type;
        $this->target = $target;
        $this->options = $options;
        $this->inverse = $inverse;
    }

    /**
     * @return string
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * @return string
     */
    public function getType(): string
    {
        return $this->type;
    }

    /**
     * Target class name, see more information about target context via targetContext() method.
     *
     * @return string
     */
    public function getTarget(): string
    {
        return $this->target;
    }

    /**
     * Indicates that relation must be late binded. In relations like that targetContext() can
     * return null.
     *
     * @return bool
     */
    public function isLateBinded(): bool
    {
        return !empty($this->options[RecordEntity::LATE_BINDING]);
    }

    /**
     * @return array
     */
    public function getOptions(): array
    {
        return $this->options;
    }

    /**
     * Source context (where relation comes from).
     *
     * @return RelationContext
     */
    public function sourceContext(): RelationContext
    {
        if (empty($this->sourceContext)) {
            throw new SchemaException("Source context not set");
        }

        return $this->sourceContext;
    }

    /**
     * Target context if any.
     *
     * @return null|RelationContext
     */
    public function targetContext()
    {
        return $this->targetContext;
    }

    /**
     * Set relation contexts.
     *
     * @param RelationContext      $source
     * @param RelationContext|null $target
     *
     * @return RelationDefinition
     */
    public function withContext(RelationContext $source, RelationContext $target = null): self
    {
        $definition = clone $this;
        $definition->sourceContext = $source;
        $definition->targetContext = $target;

        return $definition;
    }

    /**
     * Create version of definition with different set of options.
     *
     * @param array $options
     *
     * @return RelationDefinition
     */
    public function withOptions(array $options): self
    {
        $definition = clone $this;
        $definition->options = $options;

        return $definition;
    }

    /**
     * Checks if inversion if required.
     *
     * @return bool
     */
    public function needInversion(): bool
    {
        return !empty($this->inverse);
    }

    /**
     * Name of relation to be inversed to.
     *
     * @return string|array
     */
    public function getInverse()
    {
        if (!$this->needInversion()) {
            throw new ORMException("Unable to get inversed name, not inversable");
        }

        return $this->inverse;
    }
}