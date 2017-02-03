<?php
/**
 * Spiral, Core Components
 *
 * @author Wolfy-J
 */

namespace Spiral\ORM\Schemas\Relations;

use Doctrine\Common\Inflector\Inflector;
use Spiral\ORM\Exceptions\DefinitionException;
use Spiral\ORM\Exceptions\RelationSchemaException;
use Spiral\ORM\Helpers\ColumnRenderer;
use Spiral\ORM\ORMInterface;
use Spiral\ORM\Record;
use Spiral\ORM\Schemas\Definitions\RelationContext;
use Spiral\ORM\Schemas\Definitions\RelationDefinition;
use Spiral\ORM\Schemas\InversableRelationInterface;
use Spiral\ORM\Schemas\Relations\Traits\ForeignsTrait;
use Spiral\ORM\Schemas\Relations\Traits\MorphedTrait;
use Spiral\ORM\Schemas\Relations\Traits\TypecastTrait;
use Spiral\ORM\Schemas\SchemaBuilder;

/**
 * ManyToMorphed relation declares relation between parent record and set of outer records joined by
 * common interface. Relation allow to specify inner key (key in parent record), outer key (key in
 * outer records), morph key, pivot table name, names of pivot columns to store inner and outer key
 * values and set of additional columns. Relation DOES NOT to specify WHERE statement for outer
 * records. However you can specify where conditions for PIVOT table.
 *
 * You can declare this relation using same syntax as for ManyToMany except your target class
 * must be an interface.
 *
 * Attention, be very careful using morphing relations, you must know what you doing!
 * Attention #2, relation like that can not be preloaded!
 *
 * Example [Tag related to many TaggableInterface], relation name "tagged", relation requested to be
 * inversed using name "tags":
 * - relation will walk should every record implementing TaggableInterface to collect name and
 *   type of outer keys, if outer key is not consistent across records implementing this interface
 *   an exception will be raised, let's say that outer key is "id" in every record
 * - relation will create pivot table named "tagged_map" (if allowed), where table name generated
 *   based on relation name (you can change name)
 * - relation will create pivot key named "tag_ud" related to Tag primary key
 * - relation will create pivot key named "tagged_id" related to primary key of outer records,
 *   singular relation name used to generate key like that
 * - relation will create pivot key named "tagged_type" to store role of outer record
 * - relation will create unique index on "tag_id", "tagged_id" and "tagged_type" columns if allowed
 * - relation will create additional columns in pivot table if any requested
 *
 * Using in records:
 * You can use inversed relation as usual ManyToMany, however in Tag record relation access will be
 * little bit more complex - every linked record will create inner ManyToMany relation:
 * $tag->tagged->users->count(); //Where "users" is plural form of one outer records
 *
 * You can defined your own inner relation names by using MORPHED_ALIASES option when defining
 * relation.
 *
 * Attention, relation do not support WHERE statement on outer records.
 *
 * @see BelongsToMorhedSchema
 * @see ManyToManySchema
 */
class ManyToMorphedSchema extends AbstractSchema implements InversableRelationInterface
{
    use TypecastTrait, ForeignsTrait, MorphedTrait;

    /**
     * Relation type.
     */
    const RELATION_TYPE = Record::MANY_TO_MORPHED;

    /**
     * Size of string column dedicated to store outer role name. Used in polymorphic relations.
     * Even simple relations might include morph key (usually such relations created via inversion
     * of polymorphic relation).
     *
     * @see RecordSchema::getRole()
     */
    const MORPH_COLUMN_SIZE = 32;

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
        Record::MORPH_KEY
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
        Record::OUTER_KEY         => ORMInterface::R_PRIMARY_KEY,

        //Name field where parent record inner key will be stored in pivot table, role + innerKey
        //by default
        Record::THOUGHT_INNER_KEY => '{source:role}_{option:innerKey}',

        //Name field where inner key of outer record (outer key) will be stored in pivot table,
        //role + outerKey by default
        Record::THOUGHT_OUTER_KEY => '{relation:name}_id',

        //Declares what specific record pivot record linking to
        Record::MORPH_KEY         => '{relation:name}_type',

        //Set constraints (foreign keys) by default, attention only set for source table
        Record::CREATE_CONSTRAINT => true,

        //@link https://en.wikipedia.org/wiki/Foreign_key
        Record::CONSTRAINT_ACTION => 'CASCADE',

        //Relation allowed to create indexes in pivot table
        Record::CREATE_INDEXES    => true,

        //Name of pivot table to be declared, default value is not stated as it will be generated
        //based on roles of inner and outer records
        Record::PIVOT_TABLE       => '{relation:name}_map',

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
    ];

    /**
     * {@inheritdoc}
     */
    public function inverseDefinition(SchemaBuilder $builder, $inverseTo): \Generator
    {
        if (!is_string($inverseTo)) {
            throw new DefinitionException("Inversed relation must be specified as string");
        }

        foreach ($this->findTargets($builder) as $schema) {
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
                    Record::INNER_KEY         => $this->findOuter($builder)->getName(),
                    Record::THOUGHT_INNER_KEY => $this->option(Record::THOUGHT_OUTER_KEY),
                    Record::THOUGHT_OUTER_KEY => $this->option(Record::THOUGHT_INNER_KEY),
                    Record::CREATE_CONSTRAINT => false,
                    Record::CREATE_INDEXES    => $this->option(Record::CREATE_INDEXES),
                    Record::CREATE_PIVOT      => false, //Table creation hes been already handled
                    //We have to include morphed key in here
                    Record::PIVOT_COLUMNS     => $this->option(Record::PIVOT_COLUMNS) + [
                            $this->option(Record::MORPH_KEY) => 'string'
                        ],
                    Record::WHERE_PIVOT       => $this->option(Record::WHERE_PIVOT),
                    Record::MORPH_KEY         => $this->option(Record::MORPH_KEY)
                ]
            );

            //In back order :)
            yield $inversed->withContext(
                RelationContext::createContent(
                    $schema,
                    $builder->requestTable($schema->getTable(), $schema->getDatabase())
                ),
                $this->definition->sourceContext()
            );
        }
    }

    /**
     * {@inheritdoc}
     *
     * Note: pivot table will be build from direction of source, please do not attempt to create
     * many to many relations between databases without specifying proper database.
     */
    public function declareTables(SchemaBuilder $builder): array
    {
        $sourceContext = $this->definition->sourceContext();

        if (!interface_exists($target = $this->definition->getTarget())) {
            throw new RelationSchemaException("Morphed relations can only be pointed to an interface");
        }

        if (!$this->option(Record::CREATE_PIVOT)) {
            //No pivot table creation were requested, noting really to do
            return [];
        }

        $outerKey = $this->findOuter($builder);
        if (empty($outerKey)) {
            throw new RelationSchemaException("Unable to build morphed relation, no outer record found");
        }

        //Make sure all tables has same outer
        $this->verifyOuter($builder, $outerKey);

        $pivotTable = $builder->requestTable(
            $this->option(Record::PIVOT_TABLE),
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
        $thoughtOuterKey->setType($this->resolveType($outerKey));

        //Morph key
        $thoughtMorphKey = $pivotTable->column($this->option(Record::MORPH_KEY));
        $thoughtMorphKey->nullable(false);
        $thoughtMorphKey->string(static::MORPH_COLUMN_SIZE);

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
                $thoughtOuterKey->getName(),
                $thoughtMorphKey->getName()
            ])->unique();
        }

        //There is only 1 constrain
        if ($this->isConstrained()) {
            $this->createForeign(
                $pivotTable,
                $thoughtInnerKey,
                $sourceContext->getColumn($this->option(Record::INNER_KEY)),
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
        $schema = &$packed[ORMInterface::R_SCHEMA];

        //Must be resolved thought builder (can't be defined manually)
        $schema[Record::OUTER_KEY] = $this->findOuter($builder)->getName();

        //Clarifying location
        $schema[Record::PIVOT_DATABASE] = $this->definition->sourceContext()->getDatabase();
        $schema[Record::PIVOT_COLUMNS] = array_keys($schema[Record::PIVOT_COLUMNS]);

        //Ensure that inner keys are always presented
        $schema[Record::PIVOT_COLUMNS] = array_merge(
            [
                $this->option(Record::THOUGHT_INNER_KEY),
                $this->option(Record::THOUGHT_OUTER_KEY),
                $this->option(Record::MORPH_KEY)
            ],
            $schema[Record::PIVOT_COLUMNS]
        );

        //Model-role mapping
        foreach ($this->findTargets($builder) as $outer) {
            /*
             * //Must be pluralized
             * $tag->tagged->posts->count();
             */
            $role = Inflector::pluralize($outer->getRole());

            //Role => model mapping
            $schema[Record::MORPHED_ALIASES][$role] = $outer->getClass();
        }

        return $packed;
    }
}