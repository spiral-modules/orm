<?php
/**
 * Spiral, Core Components
 *
 * @author Wolfy-J
 */

namespace Spiral\ORM\Schemas\Relations;

use Spiral\ORM\Exceptions\OptionsException;
use Spiral\ORM\Helpers\RelationOptions;
use Spiral\ORM\ORMInterface;
use Spiral\ORM\Record;
use Spiral\ORM\Schemas\Definitions\RelationDefinition;
use Spiral\ORM\Schemas\RelationInterface;
use Spiral\ORM\Schemas\Relations\Traits\TablesTrait;
use Spiral\ORM\Schemas\SchemaBuilder;

/**
 * Basic class for SQL specific relations.
 */
abstract class AbstractSchema implements RelationInterface
{
    use TablesTrait;

    /**
     * Relation type to be stored in packed schema.
     */
    const RELATION_TYPE = null;

    /**
     * Options to be packed in schema (not all options are required in runtime).
     */
    const PACK_OPTIONS = [];

    /**
     * Most of relations provides ability to specify many different configuration options, such
     * as key names, pivot table schemas, foreign key request, ability to be nullabe and etc.
     *
     * To simple schema definition in real projects we can fill some of this values automatically
     * based on some "environment" values such as parent/outer record table, role name, primary key
     * and etc.
     *
     * Example:
     * Record::INNER_KEY => '{outer:role}_{outer:primaryKey}'
     *
     * Result:
     * Outer Record is User with primary key "id" => "user_id"
     *
     * @var array
     */
    const OPTIONS_TEMPLATE = [];

    /**
     * @var RelationDefinition
     */
    protected $definition;

    /**
     * Provides ability to define missing relation options based on template. Column names will be
     * added automatically if target presented.
     *
     * @see self::OPTIONS_TEMPLATE
     * @var RelationOptions
     */
    protected $options;

    /**
     * @param RelationDefinition $definition
     */
    public function __construct(RelationDefinition $definition)
    {
        $this->definition = $definition;
        $this->options = new RelationOptions($definition, static::OPTIONS_TEMPLATE);
    }

    /**
     * {@inheritdoc}
     */
    public function getDefinition(): RelationDefinition
    {
        return $this->definition;
    }

    /**
     * {@inheritdoc}
     */
    public function packRelation(SchemaBuilder $builder): array
    {
        //Fetching all needed options
        $schema = $this->options->defineMultiple(static::PACK_OPTIONS);

        if (
            in_array(Record::RELATION_COLUMNS, static::PACK_OPTIONS)
            && empty($schema[Record::RELATION_COLUMNS])
            && !empty($this->definition->targetContext())
        ) {
            //Target table
            $table = $this->targetTable($builder);

            //Let's read list of columns
            foreach ($table->getColumns() as $column) {
                $schema[Record::RELATION_COLUMNS][] = $column->getName();
            }
        }


        $target = $this->getDefinition()->getTarget();
        if ($this->definition->isLateBinded()) {
            //Located while rendering
            $target = $this->definition->targetContext()->getClass();
        }

        return [
            ORMInterface::R_TYPE   => static::RELATION_TYPE,
            ORMInterface::R_CLASS  => $target,
            ORMInterface::R_SCHEMA => $schema
        ];
    }

    /**
     * Check if relation requests foreign key constraints to be created.
     *
     * @return bool
     */
    public function isConstrained()
    {
        $source = $this->definition->sourceContext();
        $target = $this->definition->targetContext();
        if (empty($target)) {
            return false;
        }

        if ($source->getDatabase() != $target->getDatabase()) {
            //Unable to create constrains for records in different databases
            return false;
        }

        return $this->option(Record::CREATE_CONSTRAINT);
    }

    /**
     * Define relation configuration option.
     *
     * @param string $option
     *
     * @return mixed
     *
     * @throws OptionsException
     */
    protected function option(string $option)
    {
        return $this->options->define($option);
    }

}