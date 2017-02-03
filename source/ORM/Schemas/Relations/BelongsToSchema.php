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
 * Declares that parent record belongs to some parent based on value in [inner] key. Basically this
 * relation is mirror copy of HasOne/HasMany relation.
 *
 * BelongsTo relations can not be inversed!
 *
 * Example, [Post has one User, relation name "author"], user primary key is "id":
 * - relation will create inner key "author_id" in "posts" table (or other table name), nullable by
 *   default
 * - relation will create index on column "author_id" in "posts" table if allowed
 * - relation will create foreign key "posts"."author_id" => "users"."id" if allowed
 */
class BelongsToSchema extends AbstractSchema implements InversableRelationInterface
{
    use TypecastTrait, ForeignsTrait;

    /**
     * Relation type.
     */
    const RELATION_TYPE = Record::BELONGS_TO;

    /**
     * Options needed in runtime.
     */
    const PACK_OPTIONS = [
        Record::INNER_KEY,
        Record::OUTER_KEY,
        Record::NULLABLE,
        Record::RELATION_COLUMNS
    ];

    /**
     * {@inheritdoc}
     */
    const OPTIONS_TEMPLATE = [
        //Outer key is primary key of related record by default
        Record::OUTER_KEY         => '{target:primaryKey}',

        //Inner key will be based on singular name of relation and outer key name
        Record::INNER_KEY         => '{relation:singular}_{option:outerKey}',

        //Set constraints (foreign keys) by default
        Record::CREATE_CONSTRAINT => true,

        //@link https://en.wikipedia.org/wiki/Foreign_key
        Record::CONSTRAINT_ACTION => 'CASCADE',

        //Relation allowed to create indexes in inner table
        Record::CREATE_INDEXES    => true,

        //We are going to make all relations nullable by default, so we can add fields to existed
        //tables without raising an exceptions
        Record::NULLABLE          => true,
    ];

    /**
     * {@inheritdoc}
     */
    public function inverseDefinition(SchemaBuilder $builder, $inverseTo): \Generator
    {
        if (!is_array($inverseTo) || count($inverseTo) != 2) {
            throw new DefinitionException(
                "BelongsTo relation inverse must be defined as [type, outer relation name]"
            );
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
            $inverseTo[1],
            $inverseTo[0],
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

        if (!$targetTable->hasColumn($this->option(Record::OUTER_KEY))) {
            throw new RelationSchemaException(sprintf("Outer key '%s'.'%s' (%s) does not exists",
                $targetTable->getName(),
                $this->option(Record::OUTER_KEY),
                $this->definition->getName()
            ));
        }

        //Column to be used as outer key
        $outerKey = $targetTable->column($this->option(Record::OUTER_KEY));

        //Column to be used as inner key
        $innerKey = $sourceTable->column($this->option(Record::INNER_KEY));

        //Syncing types
        $innerKey->setType($this->resolveType($outerKey));

        //If nullable
        $innerKey->nullable($this->option(Record::NULLABLE));

        //Do we need indexes?
        if ($this->option(Record::CREATE_INDEXES)) {
            //Always belongs to one parent
            $sourceTable->index([$innerKey->getName()]);
        }

        if ($this->isConstrained()) {
            $this->createForeign(
                $sourceTable,
                $innerKey,
                $outerKey,
                $this->option(Record::CONSTRAINT_ACTION),
                $this->option(Record::CONSTRAINT_ACTION)
            );
        }

        return [$sourceTable];
    }
}