<?php
/**
 * Spiral, Core Components
 *
 * @author Wolfy-J
 */

namespace Spiral\ORM\Schemas\Relations;

use Spiral\ORM\Exceptions\DefinitionException;
use Spiral\ORM\Exceptions\RelationSchemaException;
use Spiral\ORM\ORMInterface;
use Spiral\ORM\Record;
use Spiral\ORM\Schemas\Definitions\RelationContext;
use Spiral\ORM\Schemas\Definitions\RelationDefinition;
use Spiral\ORM\Schemas\InversableRelationInterface;
use Spiral\ORM\Schemas\Relations\Traits\MorphedTrait;
use Spiral\ORM\Schemas\Relations\Traits\TypecastTrait;
use Spiral\ORM\Schemas\SchemaBuilder;

/**
 * BelongsToMorphed are almost identical to BelongsTo except it parent Record defined by role value
 * stored in [morph key] and parent key in [inner key].
 *
 * You can define BelongsToMorphed relation using syntax for BelongsTo but declaring outer class
 * as interface, meaning you should not only declare inversed relation name, but also it's type -
 * HAS_ONE or HAS_MANY.
 *
 * Example: 'parent' => [self::BELONGS_TO_MORPHED => 'Records\CommentableInterface']
 *
 * Attention, be very careful using morphing relations, you must know what you doing!
 * Attention #2, relation like that can not be preloaded!
 *
 * Attention #3, inverse morphed relation to use it efficiently (inversed into HAS_ONE relation).
 *
 * Example, [Comment can belong to any CommentableInterface record], relation name "parent",
 * relation requested to be inversed into HAS_MANY "comments":
 * - relation will walk should every record implementing CommentableInterface to collect name and
 *   type of outer keys, if outer key is not consistent across records implementing this interface
 *   an exception will be raised, let's say that outer key is "id" in every record
 * - relation will create inner key "parent_id" in "comments" table (or other table name), nullable
 *   by default
 * - relation will create "parent_type" morph key in "comments" table, nullable by default
 * - relation will create complex index index on columns "parent_id" and "parent_type" in
 *   "comments" table if allowed
 * - due relation is inversable every record implementing CommentableInterface will receive
 *   HAS_MANY relation "comments" pointing to Comment record using record role value
 *
 * @see BelongsToSchema
 */
class BelongsToMorphedSchema extends AbstractSchema implements InversableRelationInterface
{
    use MorphedTrait, TypecastTrait;

    /**
     * Relation type.
     */
    const RELATION_TYPE = Record::BELONGS_TO_MORPHED;

    /**
     * Size of string column dedicated to store outer role name. Used in polymorphic relations.
     * Even simple relations might include morph key (usually such relations created via inversion
     * of polymorphic relation).
     *
     * @see RecordSchema::getRole()
     */
    const MORPH_COLUMN_SIZE = 32;

    /**
     * Options needed in runtime.
     */
    const PACK_OPTIONS = [
        Record::INNER_KEY,
        Record::OUTER_KEY,
        Record::MORPH_KEY,
        Record::NULLABLE
    ];

    /**
     * {@inheritdoc}
     */
    const OPTIONS_TEMPLATE = [
        //By default morphed relations points to PRIMARY KEY
        Record::OUTER_KEY      => ORMInterface::R_PRIMARY_KEY,

        //Inner key will be based on singular name of relation and outer key name
        Record::INNER_KEY      => '{relation:singular}_id',

        //Inner key will be based on singular name of relation and outer key name
        Record::MORPH_KEY      => '{relation:singular}_type',

        //Relation allowed to create indexes in inner table
        Record::CREATE_INDEXES => true,

        //We are going to make all relations nullable by default, so we can add fields to existed
        //tables without raising an exceptions
        Record::NULLABLE       => true,
    ];

    /**
     * {@inheritdoc}
     *
     * Relation will be inversed into every associated parent.
     */
    public function inverseDefinition(SchemaBuilder $builder, $inverseTo): \Generator
    {
        if (!is_array($inverseTo) || count($inverseTo) != 2) {
            throw new DefinitionException(
                "BelongsToMorphed relation inverse must be defined as [type, outer relation name]"
            );
        }

        foreach ($this->findTargets($builder) as $schema) {
            /**
             * We are going to simply replace outer key with inner key and keep the rest of options intact.
             */
            $inversed = new RelationDefinition(
                $inverseTo[1],
                $inverseTo[0],
                $this->definition->sourceContext()->getClass(),
                [
                    Record::INNER_KEY         => $this->findOuter($builder)->getName(),
                    Record::OUTER_KEY         => $this->option(Record::INNER_KEY),
                    Record::CREATE_CONSTRAINT => false,
                    Record::CREATE_INDEXES    => $this->option(Record::CREATE_INDEXES),
                    Record::NULLABLE          => $this->option(Record::NULLABLE),
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
     */
    public function declareTables(SchemaBuilder $builder): array
    {
        $sourceTable = $this->sourceTable($builder);

        if (!interface_exists($target = $this->definition->getTarget())) {
            throw new RelationSchemaException("Morphed relations can only be pointed to an interface");
        }

        $outerKey = $this->findOuter($builder);
        if (empty($outerKey)) {
            throw new RelationSchemaException(sprintf(
                "Unable to build morphed relation, no outer record(s) found for '%s' from '%s'",
                $this->getDefinition()->getTarget(),
                $this->getDefinition()->sourceContext()->getClass()
            ));
        }

        //Make sure all tables has same outer
        $this->verifyOuter($builder, $outerKey);

        //Column to be used as inner key
        $innerKey = $sourceTable->column($this->option(Record::INNER_KEY));

        //Syncing types
        $innerKey->setType($this->resolveType($outerKey));

        //If nullable
        $innerKey->nullable($this->option(Record::NULLABLE));

        //Morph key is always string
        $morphKey = $sourceTable->column($this->option(Record::MORPH_KEY));
        $morphKey->string(static::MORPH_COLUMN_SIZE);

        //Do we need indexes?
        if ($this->option(Record::CREATE_INDEXES)) {
            //Compound outer key
            $sourceTable->index([$innerKey->getName(), $morphKey->getName()]);
        }

        //No constrains to create
        return [$sourceTable];
    }

    /**
     * {@inheritdoc}
     */
    public function packRelation(SchemaBuilder $builder): array
    {
        $packed = parent::packRelation($builder);

        $schema = &$packed[ORMInterface::R_SCHEMA];
        $schema[Record::OUTER_KEY] = $this->findOuter($builder)->getName();

        foreach ($this->findTargets($builder) as $outer) {
            //Role => model mapping
            $schema[Record::MORPHED_ALIASES][$outer->getRole()] = $outer->getClass();
        }

        return $packed;
    }
}
