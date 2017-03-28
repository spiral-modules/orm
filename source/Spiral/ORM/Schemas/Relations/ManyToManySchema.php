<?php
/**
 * Spiral, Core Components
 *
 * @author Wolfy-J
 */

namespace Spiral\ORM\Schemas\Relations;

use Spiral\ORM\Exceptions\DefinitionException;
use Spiral\ORM\Exceptions\RelationSchemaException;
use Spiral\ORM\Helpers\ColumnRenderer;
use Spiral\ORM\ORMInterface;
use Spiral\ORM\Record;
use Spiral\ORM\Schemas\Definitions\RelationDefinition;
use Spiral\ORM\Schemas\InversableRelationInterface;
use Spiral\ORM\Schemas\Relations\Traits\ForeignsTrait;
use Spiral\ORM\Schemas\Relations\Traits\TypecastTrait;
use Spiral\ORM\Schemas\SchemaBuilder;

/**
 * ManyToMany relation declares that two records related to each other using pivot table data.
 * Relation allow to specify inner key (key in parent record), outer key (key in outer record),
 * pivot table name, names of pivot columns to store inner and outer key values and set of
 * additional columns. Relation allow specifying default WHERE statement for outer records and
 * pivot table separately.
 *
 * Attention, MANY to MANY can only be used inside same database.
 *
 * Example (User related to many Tag records):
 * - relation will create pivot table named "tag_user_map" (if allowed), where table name generated
 *   based on roles of inner and outer tables sorted in ABC order (you can change name)
 * - relation will create pivot key named "user_id" related to User primary key
 * - relation will create pivot key named "tag_id" related to Tag primary key
 * - relation will create unique index on "user_id" and "tag_id" columns if allowed
 * - relation will create foreign key "tag_user_map"."user_id" => "users"."id" if allowed
 * - relation will create foreign key "tag_user_map"."tag_id" => "tags"."id" if allowed
 * - relation will create additional columns in pivot table if any requested
 */
class ManyToManySchema extends AbstractSchema implements InversableRelationInterface
{
    use TypecastTrait, ForeignsTrait;

    /**
     * Relation type.
     */
    const RELATION_TYPE = Record::MANY_TO_MANY;

    /**
     * Options to be packed.
     */
    const PACK_OPTIONS = [
        Record::PIVOT_TABLE,
        Record::OUTER_KEY,
        Record::INNER_KEY,
        Record::THOUGHT_INNER_KEY,
        Record::THOUGHT_OUTER_KEY,
        Record::RELATION_COLUMNS,
        Record::PIVOT_COLUMNS,
        Record::WHERE_PIVOT,
        Record::WHERE,
        Record::MORPH_KEY,
        Record::ORDER_BY
    ];

    /**
     * Default postfix for pivot tables.
     */
    const PIVOT_POSTFIX = '_map';

    /**
     * {@inheritdoc}
     *
     * @invisible
     */
    const OPTIONS_TEMPLATE = [
        //Inner key of parent record will be used to fill "THOUGHT_INNER_KEY" in pivot table
        Record::INNER_KEY         => '{source:primaryKey}',

        //We are going to use primary key of outer table to fill "THOUGHT_OUTER_KEY" in pivot table
        //This is technically "inner" key of outer record, we will name it "outer key" for simplicity
        Record::OUTER_KEY         => '{target:primaryKey}',

        //Name field where parent record inner key will be stored in pivot table, role + innerKey
        //by default
        Record::THOUGHT_INNER_KEY => '{source:role}_{option:innerKey}',

        //Name field where inner key of outer record (outer key) will be stored in pivot table,
        //role + outerKey by default
        Record::THOUGHT_OUTER_KEY => '{target:role}_{option:outerKey}',

        //Set constraints in pivot table (foreign keys)
        Record::CREATE_CONSTRAINT => true,

        //@link https://en.wikipedia.org/wiki/Foreign_key
        Record::CONSTRAINT_ACTION => 'CASCADE',

        //Relation allowed to create indexes in pivot table
        Record::CREATE_INDEXES    => true,

        //Name of pivot table to be declared, default value is not stated as it will be generated
        //based on roles of inner and outer records
        Record::PIVOT_TABLE       => null,

        //Relation allowed to create pivot table
        Record::CREATE_PIVOT      => true,

        //Additional set of columns to be added into pivot table, you can use same column definition
        //type as you using for your records
        Record::PIVOT_COLUMNS     => [],

        //Set of default values to be used for pivot table
        Record::PIVOT_DEFAULTS    => [],

        //WHERE statement in a form of simplified array definition to be applied to pivot table
        //data.
        Record::WHERE_PIVOT       => [],

        //WHERE statement to be applied for data in outer data while loading relation data
        //can not be inversed. Attention, WHERE conditions not used in has(), link() and sync()
        //methods.
        Record::WHERE             => [],

        //Used when relation is created as inverse of ManyToMorphed relation
        Record::MORPH_KEY         => null,

        //Order
        Record::ORDER_BY          => []
    ];

    /**
     *{@inheritdoc}
     */
    public function inverseDefinition(SchemaBuilder $builder, $inverseTo): \Generator
    {
        if (!is_string($inverseTo)) {
            throw new DefinitionException("Inversed relation must be specified as string");
        }

        if (empty($this->definition->targetContext())) {
            throw new DefinitionException(sprintf(
                "Unable to inverse relation '%s.''%s', unspecified relation target",
                $this->definition->sourceContext()->getClass(),
                $this->definition->getName()
            ));
        }

        /**
         * We are going to simply replace outer key with inner key and keep the rest of options intact.
         */
        $inversed = new RelationDefinition(
            $inverseTo,
            Record::MANY_TO_MANY,
            $this->definition->sourceContext()->getClass(),
            [
                Record::PIVOT_TABLE       => $this->option(Record::PIVOT_TABLE),
                Record::OUTER_KEY         => $this->option(Record::INNER_KEY),
                Record::INNER_KEY         => $this->option(Record::OUTER_KEY),
                Record::THOUGHT_INNER_KEY => $this->option(Record::THOUGHT_OUTER_KEY),
                Record::THOUGHT_OUTER_KEY => $this->option(Record::THOUGHT_INNER_KEY),
                Record::CREATE_CONSTRAINT => $this->option(Record::CREATE_CONSTRAINT),
                Record::CONSTRAINT_ACTION => $this->option(Record::CONSTRAINT_ACTION),
                Record::CREATE_INDEXES    => $this->option(Record::CREATE_INDEXES),
                Record::CREATE_PIVOT      => false, //Table creation hes been already handled
                Record::PIVOT_COLUMNS     => $this->option(Record::PIVOT_COLUMNS),
                Record::WHERE_PIVOT       => $this->option(Record::WHERE_PIVOT),
            ]
        );

        //In back order :)
        yield $inversed->withContext(
            $this->definition->targetContext(),
            $this->definition->sourceContext()
        );
    }

    /**
     * {@inheritdoc}
     *
     * Note: pivot table will be build from direction of source, please do not attempt to create
     * many to many relations between databases without specifying proper database.
     */
    public function declareTables(SchemaBuilder $builder): array
    {
        if (!$this->option(Record::CREATE_PIVOT)) {
            //No pivot table creation were requested, noting really to do
            return [];
        }

        $sourceTable = $this->sourceTable($builder);
        $targetTable = $this->targetTable($builder);

        $sourceContext = $this->definition->sourceContext();
        $targetContext = $this->definition->targetContext();

        if (
            $sourceTable->getDriver() != $targetTable->getDriver()
            || $sourceContext->getDatabase() != $sourceContext->getDatabase()
        ) {
            //todo: support cross database and cross driver many to many
            throw new RelationSchemaException(
                "ManyToMany relations can only exists inside same database"
            );
        }

        $pivotTable = $builder->requestTable(
            $this->pivotTable(),
            $sourceContext->getDatabase(),
            false,
            true
        );

        /*
         * Declare columns in map/pivot table.
         */
        $thoughtInnerKey = $pivotTable->column($this->option(Record::THOUGHT_INNER_KEY));
        $thoughtInnerKey->nullable(false);
        $thoughtInnerKey->setType($this->resolveType(
            $sourceContext->getColumn($this->option(Record::INNER_KEY))
        ));

        $thoughtOuterKey = $pivotTable->column($this->option(Record::THOUGHT_OUTER_KEY));
        $thoughtOuterKey->nullable(false);
        $thoughtOuterKey->setType($this->resolveType(
            $targetContext->getColumn($this->option(Record::OUTER_KEY))
        ));

        /*
         * Declare user columns in pivot table.
         */
        $rendered = new ColumnRenderer();
        $rendered->renderColumns(
            $this->option(Record::PIVOT_COLUMNS),
            $this->option(Record::PIVOT_DEFAULTS),
            $pivotTable
        );

        //Map might only contain unique link between source and target
        if ($this->option(Record::CREATE_INDEXES)) {
            $pivotTable->index([
                $thoughtInnerKey->getName(),
                $thoughtOuterKey->getName()
            ])->unique();
        }

        //There is 2 constrains between map table and source and table
        if ($this->isConstrained()) {
            $this->createForeign(
                $pivotTable,
                $thoughtInnerKey,
                $sourceContext->getColumn($this->option(Record::INNER_KEY)),
                $this->option(Record::CONSTRAINT_ACTION),
                $this->option(Record::CONSTRAINT_ACTION)
            );

            $this->createForeign(
                $pivotTable,
                $thoughtOuterKey,
                $targetContext->getColumn($this->option(Record::OUTER_KEY)),
                $this->option(Record::CONSTRAINT_ACTION),
                $this->option(Record::CONSTRAINT_ACTION)
            );
        }

        return [$pivotTable];
    }

    /**
     * {@inheritdoc}
     */
    public function packRelation(SchemaBuilder $builder): array
    {
        $packed = parent::packRelation($builder);

        //Let's clarify pivot columns
        $schema = &$packed[ORMInterface::R_SCHEMA];

        //Pivot table location (for now always in context database)
        $schema[Record::PIVOT_TABLE] = $this->pivotTable();
        $schema[Record::PIVOT_DATABASE] = $this->definition->sourceContext()->getDatabase();

        $schema[Record::PIVOT_COLUMNS] = array_keys($schema[Record::PIVOT_COLUMNS]);

        //Ensure that inner keys are always presented
        $schema[Record::PIVOT_COLUMNS] = array_merge(
            [
                $this->option(Record::THOUGHT_INNER_KEY),
                $this->option(Record::THOUGHT_OUTER_KEY)
            ],
            $schema[Record::PIVOT_COLUMNS]
        );

        return $packed;
    }

    /**
     * Generate name of pivot table or fetch if from schema.
     *
     * @return string
     */
    protected function pivotTable(): string
    {
        if (!empty($this->option(Record::PIVOT_TABLE))) {
            return $this->option(Record::PIVOT_TABLE);
        }

        $source = $this->definition->sourceContext();
        $target = $this->definition->targetContext();

        //Generating pivot table name
        $names = [$source->getRole(), $target->getRole()];
        asort($names);

        return implode('_', $names) . static::PIVOT_POSTFIX;
    }
}