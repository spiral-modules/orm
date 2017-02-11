<?php
/**
 * Spiral, Core Components
 *
 * @author Wolfy-J
 */

namespace Spiral\ORM\Schemas;

use Doctrine\Common\Inflector\Inflector;
use Spiral\Database\Configs\DatabasesConfig;
use Spiral\Database\Schemas\Prototypes\AbstractTable;
use Spiral\Models\AccessorInterface;
use Spiral\Models\Exceptions\AccessException;
use Spiral\Models\Reflections\ReflectionEntity;
use Spiral\ORM\Configs\MutatorsConfig;
use Spiral\ORM\Entities\RecordInstantiator;
use Spiral\ORM\Exceptions\DefinitionException;
use Spiral\ORM\Helpers\ColumnRenderer;
use Spiral\ORM\Record;
use Spiral\ORM\RecordAccessorInterface;
use Spiral\ORM\RecordEntity;
use Spiral\ORM\Schemas\Definitions\IndexDefinition;
use Spiral\ORM\Schemas\Definitions\RelationDefinition;

class RecordSchema implements SchemaInterface
{
    /**
     * @var ReflectionEntity
     */
    private $reflection;

    /**
     * @invisible
     * @var MutatorsConfig
     */
    private $mutatorsConfig;

    /**
     * @invisible
     * @var ColumnRenderer
     */
    private $renderer;

    /**
     * @param ReflectionEntity    $reflection
     * @param MutatorsConfig      $mutators
     * @param ColumnRenderer|null $rendered
     */
    public function __construct(
        ReflectionEntity $reflection,
        MutatorsConfig $mutators,
        ColumnRenderer $rendered = null
    ) {
        $this->reflection = $reflection;
        $this->mutatorsConfig = $mutators;
        $this->renderer = $rendered ?? new ColumnRenderer();
    }

    /**
     * {@inheritdoc}
     */
    public function getClass(): string
    {
        return $this->reflection->getName();
    }

    /**
     * {@inheritdoc}
     */
    public function getRole(): string
    {
        $role = $this->reflection->getProperty('model_role');

        //When role not defined we are going to use short class name
        return $role ?? lcfirst(Inflector::tableize($this->reflection->getShortName()));
    }

    /**
     * @return ReflectionEntity
     */
    public function getReflection(): ReflectionEntity
    {
        return $this->reflection;
    }

    /**
     * {@inheritdoc}
     */
    public function getInstantiator(): string
    {
        return $this->reflection->getProperty('instantiator') ?? RecordInstantiator::class;
    }

    /**
     * {@inheritdoc}
     */
    public function getDatabase()
    {
        $database = $this->reflection->getProperty('database');
        if (empty($database)) {
            //Empty database to be used
            return null;
        }

        return $database;
    }

    /**
     * {@inheritdoc}
     */
    public function getTable(): string
    {
        $table = $this->reflection->getProperty('table');
        if (empty($table)) {
            //Generate table using short class name
            $table = Inflector::tableize($this->reflection->getShortName());
            $table = Inflector::pluralize($table);
        }

        return $table;
    }

    /**
     * Fields and their types declared in Record model.
     *
     * @return array
     */
    public function getFields(): array
    {
        $fields = $this->reflection->getSchema();

        foreach ($fields as $field => $type) {
            if ($this->isRelation($type)) {
                unset($fields[$field]);
            }
        }

        return $fields;
    }

    /**
     * Returns set of declared indexes.
     *
     * Example:
     * const INDEXES = [
     *      [self::UNIQUE, 'email'],
     *      [self::INDEX, 'status', 'balance'],
     *      [self::INDEX, 'public_id']
     * ];
     *
     * @do generator
     *
     * @return \Generator|IndexDefinition[]
     *
     * @throws DefinitionException
     */
    public function getIndexes(): \Generator
    {
        $definitions = $this->reflection->getProperty('indexes') ?? [];

        foreach ($definitions as $definition) {
            yield $this->castIndex($definition);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function declareTable(AbstractTable $table): AbstractTable
    {
        return $this->renderer->renderColumns(
            $this->getFields(),
            $this->getDefaults(),
            $table
        );
    }

    /**
     * {@inheritdoc}
     */
    public function getRelations(): \Generator
    {
        $schema = $this->reflection->getSchema();

        foreach ($schema as $name => $definition) {
            if (!$this->isRelation($definition)) {
                continue;
            }

            /**
             * We expect relations to be defined in a following form:
             *
             * [type => target, option => value, option => value]
             */
            $type = key($definition);
            $target = $definition[$type];
            unset($definition[$type]);

            //Defining relation
            yield new RelationDefinition(
                $name,
                $type,
                $target,
                $definition,
                $definition[Record::INVERSE] ?? null
            );
        }
    }

    /**
     * {@inheritdoc}
     */
    public function packSchema(SchemaBuilder $builder, AbstractTable $table): array
    {
        return [
            RecordEntity::SH_PRIMARY_KEY => current($table->getPrimaryKeys()),

            //Default entity values
            RecordEntity::SH_DEFAULTS    => $this->packDefaults($table),

            //Entity behaviour
            RecordEntity::SH_SECURED     => $this->reflection->getSecured(),
            RecordEntity::SH_FILLABLE    => $this->reflection->getFillable(),

            //Mutators can be altered based on ORM\SchemasConfig
            RecordEntity::SH_MUTATORS    => $this->buildMutators($table),
        ];
    }

    /**
     * Generate set of default values to be used by record.
     *
     * @param AbstractTable $table
     *
     * @return array
     */
    protected function packDefaults(AbstractTable $table): array
    {
        //We need mutators to normalize default values
        $mutators = $this->buildMutators($table);

        $defaults = [];
        foreach ($table->getColumns() as $column) {
            $field = $column->getName();

            $default = $column->getDefaultValue();

            //For non null values let's apply mutators to typecast it
            if (!is_null($default) && !is_object($default) && !$column->isNullable()) {
                $default = $this->mutateValue($mutators, $field, $default);
            }

            $defaults[$field] = $default;
        }

        return $defaults;
    }

    /**
     * Generate set of mutators associated with entity fields using user defined and automatic
     * mutators.
     *
     * @see MutatorsConfig
     *
     * @param AbstractTable $table
     *
     * @return array
     */
    protected function buildMutators(AbstractTable $table): array
    {
        $mutators = $this->reflection->getMutators();

        //Trying to resolve mutators based on field type
        foreach ($table->getColumns() as $column) {
            //Resolved mutators
            $resolved = [];

            if (!empty($filter = $this->mutatorsConfig->getMutators($column->abstractType()))) {
                //Mutator associated with type directly
                $resolved += $filter;
            } elseif (!empty($filter = $this->mutatorsConfig->getMutators('php:' . $column->phpType()))) {
                //Mutator associated with php type
                $resolved += $filter;
            }

            //Merging mutators and default mutators
            foreach ($resolved as $mutator => $filter) {
                if (!array_key_exists($column->getName(), $mutators[$mutator])) {
                    $mutators[$mutator][$column->getName()] = $filter;
                }
            }
        }

        foreach ($this->getFields() as $field => $type) {
            if (
                class_exists($type)
                && is_a($type, RecordAccessorInterface::class, true)
            ) {
                //Direct column accessor definition
                $mutators['accessor'][$field] = $type;
            }
        }

        return $mutators;
    }

    /**
     * Check if field schema/type defines relation.
     *
     * @param mixed $type
     *
     * @return bool
     */
    protected function isRelation($type): bool
    {
        if (is_array($type)) {
            return true;
        }

        return false;
    }

    /**
     * @param array $definition
     *
     * @return IndexDefinition
     *
     * @throws DefinitionException
     */
    protected function castIndex(array $definition)
    {
        $unique = null;
        $columns = [];

        foreach ($definition as $chunk) {
            if ($chunk == RecordEntity::INDEX || $chunk == RecordEntity::UNIQUE) {
                $unique = $chunk === RecordEntity::UNIQUE;
                continue;
            }

            $columns[] = $chunk;
        }

        if (is_null($unique)) {
            throw new DefinitionException(
                "Record '{$this}' has index definition with unspecified index type"
            );
        }

        if (empty($columns)) {
            throw new DefinitionException(
                "Record '{$this}' has index definition without any column associated to"
            );
        }

        return new IndexDefinition($columns, $unique);
    }

    /**
     * Default defined values.
     *
     * @return array
     */
    protected function getDefaults(): array
    {
        //Process defaults
        return $this->reflection->getProperty('defaults') ?? [];
    }

    /**
     * Process value thought associated mutator if any.
     *
     * @param array  $mutators
     * @param string $field
     * @param mixed  $default
     *
     * @return mixed
     */
    protected function mutateValue(array $mutators, string $field, $default)
    {
        //Let's process default value using associated setter
        if (isset($mutators[RecordEntity::MUTATOR_SETTER][$field])) {
            try {
                $setter = $mutators[RecordEntity::MUTATOR_SETTER][$field];
                $default = call_user_func($setter, $default);

                return $default;
            } catch (\Exception $exception) {
                //Unable to generate default value, use null or empty array as fallback
            }
        }

        if (isset($mutators[RecordEntity::MUTATOR_ACCESSOR][$field])) {
            $default = $this->accessorDefault(
                $default,
                $mutators[RecordEntity::MUTATOR_ACCESSOR][$field]
            );

            return $default;
        }

        return $default;
    }

    /**
     * Pass value thought accessor to ensure it's default.
     *
     * @param mixed  $default
     * @param string $accessor
     *
     * @return mixed
     *
     * @throws AccessException
     */
    protected function accessorDefault($default, string $accessor)
    {
        /**
         * @var AccessorInterface $instance
         */
        $instance = new $accessor($default, [/*no context given*/]);
        $default = $instance->packValue();

        if (is_object($default)) {
            //Some accessors might want to return objects (DateTime, StorageObject), default to null
            $default = null;
        }

        return $default;
    }
}