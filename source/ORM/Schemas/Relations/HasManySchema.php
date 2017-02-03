<?php
/**
 * Spiral, Core Components
 *
 * @author Wolfy-J
 */

namespace Spiral\ORM\Schemas\Relations;

use Spiral\ORM\Exceptions\DefinitionException;
use Spiral\ORM\Exceptions\RelationSchemaException;
use Spiral\ORM\Record;
use Spiral\ORM\Schemas\Definitions\RelationDefinition;
use Spiral\ORM\Schemas\InversableRelationInterface;
use Spiral\ORM\Schemas\Relations\Traits\ForeignsTrait;
use Spiral\ORM\Schemas\Relations\Traits\TypecastTrait;
use Spiral\ORM\Schemas\SchemaBuilder;

/**
 * Declares simple has many relation. Relations like that used when parent record has many child
 * with
 * [outer] key linked to value of [inner] key of parent mode. Relation allow specifying default
 * WHERE statement. Attention, WHERE statement will not be used in populating newly created record
 * fields.
 *
 * Example, [User has many Comments], user primary key is "id":
 * - relation will create outer key "user_id" in "comments" table (or other table name), nullable
 *   by default
 * - relation will create index on column "user_id" in "comments" table if allowed
 * - relation will create foreign key "comments"."user_id" => "users"."id" if allowed
 *
 * Note: relation can point to morphed records
 */
class HasManySchema extends AbstractSchema implements InversableRelationInterface
{
    use TypecastTrait, ForeignsTrait;

    /**
     * Relation type.
     */
    const RELATION_TYPE = Record::HAS_MANY;

    /**
     * Options needed in runtime.
     */
    const PACK_OPTIONS = [
        Record::INNER_KEY,
        Record::OUTER_KEY,
        Record::NULLABLE,
        Record::WHERE,
        Record::RELATION_COLUMNS,
        Record::MORPH_KEY
    ];

    /**
     * {@inheritdoc}
     */
    const OPTIONS_TEMPLATE = [
        //Let's use parent record primary key as default inner key
        Record::INNER_KEY         => '{source:primaryKey}',

        //Outer key will be based on parent record role and inner key name
        Record::OUTER_KEY         => '{source:role}_{option:innerKey}',

        //Set constraints (foreign keys) by default
        Record::CREATE_CONSTRAINT => true,

        //@link https://en.wikipedia.org/wiki/Foreign_key
        Record::CONSTRAINT_ACTION => 'CASCADE',

        //We are going to make all relations nullable by default, so we can add fields to existed
        //tables without raising an exceptions
        Record::NULLABLE          => true,

        //Relation allowed to create indexes in outer table
        Record::CREATE_INDEXES    => true,

        //HasMany allow us to define default WHERE statement for relation in a simplified array form
        Record::WHERE             => [],

        //Relation can point to morphed record
        Record::MORPH_KEY         => null
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
                "Unable to inverse relation %s.%s, unspecified relation target",
                $this->definition->sourceContext()->getClass(),
                $this->definition->getName()
            ));
        }

        /**
         * We are going to simply replace outer key with inner key and keep the rest of options intact.
         */
        $inversed = new RelationDefinition(
            $inverseTo,
            Record::BELONGS_TO,
            $this->definition->sourceContext()->getClass(),
            [
                Record::INNER_KEY         => $this->option(Record::OUTER_KEY),
                Record::OUTER_KEY         => $this->option(Record::INNER_KEY),
                Record::CREATE_CONSTRAINT => $this->option(Record::CREATE_CONSTRAINT),
                Record::CONSTRAINT_ACTION => $this->option(Record::CONSTRAINT_ACTION),
                Record::CREATE_INDEXES    => $this->option(Record::CREATE_INDEXES),
                Record::NULLABLE          => $this->option(Record::NULLABLE),
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
     */
    public function declareTables(SchemaBuilder $builder): array
    {
        $sourceTable = $this->sourceTable($builder);
        $targetTable = $this->targetTable($builder);

        if (!$sourceTable->hasColumn($this->option(Record::INNER_KEY))) {
            throw new RelationSchemaException(sprintf("Inner key '%s'.'%s' (%s) does not exists",
                $sourceTable->getName(),
                $this->option(Record::INNER_KEY),
                $this->definition->getName()
            ));
        }

        //Column to be used as outer key
        $outerKey = $targetTable->column($this->option(Record::OUTER_KEY));

        //Column to be used as inner key
        $innerKey = $sourceTable->column($this->option(Record::INNER_KEY));

        //Syncing types
        $outerKey->setType($this->resolveType($innerKey));

        //If nullable
        $outerKey->nullable($this->option(Record::NULLABLE));

        //Do we need indexes?
        if ($this->option(Record::CREATE_INDEXES)) {
            $targetTable->index([$outerKey->getName()]);
        }

        if ($this->isConstrained()) {
            $this->createForeign(
                $targetTable,
                $outerKey,
                $innerKey,
                $this->option(Record::CONSTRAINT_ACTION),
                $this->option(Record::CONSTRAINT_ACTION)
            );
        }

        return [$targetTable];
    }
}