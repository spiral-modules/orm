<?php
/**
 * Spiral, Core Components
 *
 * @author Wolfy-J
 */

namespace Spiral\ORM\Schemas;

use Psr\Log\LoggerInterface;
use Spiral\Database\DatabaseManager;
use Spiral\Database\Exceptions\DBALException;
use Spiral\Database\Exceptions\DriverException;
use Spiral\Database\Exceptions\QueryException;
use Spiral\Database\Helpers\SynchronizationPool;
use Spiral\Database\Schemas\Prototypes\AbstractTable;
use Spiral\ORM\Exceptions\DefinitionException;
use Spiral\ORM\Exceptions\DoubleReferenceException;
use Spiral\ORM\Exceptions\SchemaException;
use Spiral\ORM\ORMInterface;
use Spiral\ORM\Schemas\Definitions\RelationContext;

class SchemaBuilder
{
    /**
     * @var DatabaseManager
     */
    private $manager;

    /**
     * @var RelationBuilder
     */
    private $relations;

    /**
     * @var AbstractTable[]
     */
    private $tables = [];

    /**
     * @var SchemaInterface[]
     */
    private $schemas = [];

    /**
     * Class names of sources associated with specific class.
     *
     * @var array
     */
    private $sources = [];

    /**
     * @param DatabaseManager $manager
     * @param RelationBuilder $relations
     */
    public function __construct(DatabaseManager $manager, RelationBuilder $relations)
    {
        $this->manager = $manager;
        $this->relations = $relations;
    }

    /**
     * Add new model schema into pool.
     *
     * @param SchemaInterface $schema
     *
     * @return self|$this
     */
    public function addSchema(SchemaInterface $schema): SchemaBuilder
    {
        $this->schemas[$schema->getClass()] = $schema;

        return $this;
    }

    /**
     * @param string $class
     *
     * @return bool
     */
    public function hasSchema(string $class): bool
    {
        return isset($this->schemas[$class]);
    }

    /**
     * @param string $class
     *
     * @return SchemaInterface
     *
     * @throws SchemaException
     */
    public function getSchema(string $class): SchemaInterface
    {
        if (!$this->hasSchema($class)) {
            throw new SchemaException("Unable to find schema for class '{$class}'");
        }

        return $this->schemas[$class];
    }

    /**
     * All available document schemas.
     *
     * @return SchemaInterface[]
     */
    public function getSchemas(): array
    {
        return $this->schemas;
    }

    /**
     * Associate source class with entity class. Source will be automatically associated with given
     * class and all classes from the same collection which extends it.
     *
     * @param string $class
     * @param string $source
     *
     * @return SchemaBuilder
     *
     * @throws SchemaException
     */
    public function addSource(string $class, string $source): SchemaBuilder
    {
        if (!$this->hasSchema($class)) {
            throw new SchemaException("Unable to add source to '{$class}', class is unknown to ORM");
        }

        $this->sources[$class] = $source;

        return $this;
    }

    /**
     * Check if given entity has associated source.
     *
     * @param string $class
     *
     * @return bool
     */
    public function hasSource(string $class): bool
    {
        return array_key_exists($class, $this->sources);
    }

    /**
     * Get source associated with specific class, if any.
     *
     * @param string $class
     *
     * @return string|null
     */
    public function getSource(string $class)
    {
        if (!$this->hasSource($class)) {
            return null;
        }

        return $this->sources[$class];
    }

    /**
     * Get all associated sources.
     *
     * @return array
     */
    public function getSources(): array
    {
        return $this->sources;
    }

    /**
     * Get all created relations.
     *
     * @return RelationInterface[]
     */
    public function getRelations(): array
    {
        return $this->relations->getRelations();
    }

    /**
     * Process all added schemas and relations in order to created needed tables, indexes and etc.
     * Attention, this method will return new instance of SchemaBuilder without affecting original
     * object. You MUST call this method before calling packSchema() method.
     *
     * Attention, this methods DOES NOT write anything into database, use pushSchema() to push
     * changes into database using automatic diff generation. You can also access list of
     * generated/changed tables via getTables() to create your own migrations.
     *
     * @see packSchema()
     * @see pushSchema()
     * @see getTables()
     *
     * @return SchemaBuilder
     *
     * @throws SchemaException
     */
    public function renderSchema(): SchemaBuilder
    {
        //Declaring tables associated with records
        $this->renderModels();

        //Defining all relations declared by our schemas
        $this->renderRelations();

        //Inverse relations (if requested)
        $this->relations->inverseRelations($this);

        //Rendering needed columns, FKs and indexes needed for our relations (if relation is ORM specific)
        foreach ($this->relations->declareTables($this) as $table) {
            $this->pushTable($table);
        }

        return $this;
    }

    /**
     * Request table schema by name/database combination. Attention, you can only save table by
     * pushing it back using pushTable() method.
     *
     * @see pushTable()
     *
     * @param string      $table
     * @param string|null $database
     * @param bool        $resetState When set to true current table state will be reset in order
     *                                to allow model to redefine it's schema.
     * @param bool        $unique     Set to true (default), to throw an exception when table
     *                                already referenced by another model.
     *
     * @return AbstractTable          Unlinked.
     *
     * @throws DoubleReferenceException When two records refers to same table and unique option
     *                                  set.
     */
    public function requestTable(
        string $table,
        string $database = null,
        bool $unique = false,
        bool $resetState = false
    ): AbstractTable {
        //Requesting thought DatabaseManager
        $schema = $this->manager->database($database)->table($table)->getSchema();

        if (isset($this->tables[$schema->getDriver()->getName() . '.' . $table])) {
            $schema = $this->tables[$schema->getDriver()->getName() . '.' . $table];

            if ($unique) {
                throw new DoubleReferenceException(
                    "Table '{$table}' of '{$database} 'been requested by multiple models"
                );
            }
        } else {

            $this->tables[$schema->getDriver()->getName() . '.' . $table] = $schema;
        }

        $schema = clone $schema;

        if ($resetState) {
            //Emptying our current state (initial not affected)
            $schema->setState(null);
        }

        return $schema;
    }

    /**
     * Get all defined tables, make sure to call renderSchema() first. Attention, all given tables
     * will be returned in detached state.
     *
     * @return AbstractTable[]
     *
     * @throws SchemaException
     */
    public function getTables(): array
    {
        if (empty($this->tables) && !empty($this->schemas)) {
            throw new SchemaException(
                "Unable to get tables, no tables are were found, call renderSchema() first"
            );
        }

        $result = [];
        foreach ($this->tables as $table) {
            //Detaching
            $result[] = clone $table;
        }

        return $result;
    }

    /**
     * Indication that tables in database require syncing before being matched with ORM models.
     *
     * @return bool
     */
    public function hasChanges(): bool
    {
        foreach ($this->getTables() as $table) {
            if ($table->getComparator()->hasChanges()) {
                return true;
            }
        }

        return false;
    }

    /**
     * Save every change made to generated tables. Method utilizes default DBAL diff mechanism,
     * use getTables() method in order to generate your own migrations.
     *
     * @param LoggerInterface|null $logger
     *
     * @throws SchemaException
     * @throws DBALException
     * @throws QueryException
     * @throws DriverException
     */
    public function pushSchema(LoggerInterface $logger = null)
    {
        $bus = new SynchronizationPool($this->getTables());
        $bus->run($logger);
    }

    /**
     * Pack declared schemas in a normalized form, make sure to call renderSchema() first.
     *
     * @return array
     *
     * @throws SchemaException
     */
    public function packSchema(): array
    {
        if (empty($this->tables) && !empty($this->schemas)) {
            throw new SchemaException(
                "Unable to pack schema, no defined tables were found, call renderSchema() first"
            );
        }

        $result = [];
        foreach ($this->schemas as $class => $schema) {
            //Table which is being related to model schema
            $table = $this->requestTable($schema->getTable(), $schema->getDatabase(), false);

            $relations = $this->relations->packRelations($schema->getClass(), $this);

            $result[$class] = [
                ORMInterface::R_INSTANTIATOR => $schema->getInstantiator(),

                //Model role name
                ORMInterface::R_ROLE_NAME    => $schema->getRole(),

                //Primary keys
                ORMInterface::R_PRIMARY_KEY  => current($table->getPrimaryKeys()),

                //Schema includes list of fields, default values and nullable fields
                ORMInterface::R_SCHEMA       => $schema->packSchema($this, clone $table),

                ORMInterface::R_SOURCE_CLASS => $this->getSource($class),

                //Data location (database name either fetched from schema or default database name used)
                ORMInterface::R_DATABASE     => $schema->getDatabase() ?? $this->manager->database()->getName(),
                ORMInterface::R_TABLE        => $schema->getTable(),

                //Pack model specific relations
                ORMInterface::R_RELATIONS    => $relations
            ];
        }

        return $result;
    }

    /**
     * Walk thought schemas and define structure of associated tables.
     *
     * @throws SchemaException
     * @throws DBALException
     */
    protected function renderModels()
    {
        foreach ($this->schemas as $schema) {
            /**
             * Attention, this method will request table schema from DBAL and will empty it's state
             * so schema can define it from ground zero!
             */
            $table = $this->requestTable(
                $schema->getTable(),
                $schema->getDatabase(),
                true,
                true
            );

            //Render table schema
            $table = $schema->declareTable($table);

            //Working with indexes
            foreach ($schema->getIndexes() as $index) {
                $table->index($index->getColumns())->unique($index->isUnique());
                $table->index($index->getColumns())->setName($index->getName());
            }

            /*
             * Attention, this is critical section:
             *
             * In order to work efficiently (like for real), ORM does require every table
             * to have 1 and only 1 primary key, this is crucial for things like in memory
             * cache, transaction command priority pipeline, multiple queue commands for one entity
             * and etc.
             *
             * It is planned to support user defined PKs in a future using unique indexes and record
             * schema.
             *
             * You are free to select any name for PK field.
             */
            if (count($table->getPrimaryKeys()) !== 1) {
                throw new SchemaException(
                    "Every record must have singular primary key (primary, bigPrimary types)"
                );
            }

            //And put it back :)
            $this->pushTable($table);
        }
    }

    /**
     * Walk thought all record schemas, fetch and declare needed relations using relation manager.
     *
     * @throws SchemaException
     * @throws DefinitionException
     */
    protected function renderRelations()
    {
        foreach ($this->schemas as $schema) {
            foreach ($schema->getRelations() as $name => $relation) {

                //Source context defines where relation comes from
                $sourceContext = RelationContext::createContent(
                    $schema,
                    $this->requestTable($schema->getTable(), $schema->getDatabase())
                );

                //Target context might only exist if relation points to another record in ORM,
                //in some cases it might point outside of ORM scope
                $targetContext = null;

                if ($this->hasSchema($relation->getTarget())) {
                    $target = $this->getSchema($relation->getTarget());

                    $targetContext = RelationContext::createContent(
                        $target,
                        $this->requestTable($target->getTable(), $target->getDatabase())
                    );
                }

                $this->relations->registerRelation(
                    $this,
                    $relation->withContext($sourceContext, $targetContext)
                );
            }
        }
    }

    /**
     * Update table state.
     *
     * @param AbstractTable $schema
     *
     * @throws SchemaException
     */
    protected function pushTable(AbstractTable $schema)
    {
        //We have to make sure that local table name is used
        $table = substr($schema->getName(), strlen($schema->getPrefix()));

        if (empty($this->tables[$schema->getDriver()->getName() . '.' . $table])) {
            throw new SchemaException("AbstractTable must be requested before pushing back");
        }

        $this->tables[$schema->getDriver()->getName() . '.' . $table] = $schema;
    }
}