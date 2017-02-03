<?php
/**
 * Spiral, Core Components
 *
 * @author Wolfy-J
 */

namespace Spiral\ORM\Entities;

use Psr\SimpleCache\CacheInterface;
use Spiral\Core\Component;
use Spiral\Core\Traits\SaturateTrait;
use Spiral\Database\Builders\SelectQuery;
use Spiral\Models\EntityInterface;
use Spiral\ORM\Entities\Loaders\RootLoader;
use Spiral\ORM\Entities\Nodes\OutputNode;
use Spiral\ORM\Exceptions\SelectorException;
use Spiral\ORM\ORMInterface;
use Spiral\ORM\RecordInterface;
use Spiral\Pagination\PaginatorAwareInterface;
use Spiral\Pagination\PaginatorInterface;

/**
 * Attention, RecordSelector DOES NOT extends QueryBuilder but mocks it!
 *
 * @method $this where(...$args);
 * @method $this andWhere(...$args);
 * @method $this orWhere(...$args);
 *
 * @method $this having(...$args);
 * @method $this andHaving(...$args);
 * @method $this orHaving(...$args);
 *
 * @method $this paginate($limit = 25, $page = 'page')
 *
 * @method $this orderBy($expression, $direction = 'ASC');
 *
 * @method int avg($identifier) Perform aggregation (AVG) based on column or expression value.
 * @method int min($identifier) Perform aggregation (MIN) based on column or expression value.
 * @method int max($identifier) Perform aggregation (MAX) based on column or expression value.
 * @method int sum($identifier) Perform aggregation (SUM) based on column or expression value.
 */
class RecordSelector extends Component implements \IteratorAggregate, \Countable, PaginatorAwareInterface
{
    use SaturateTrait;

    /**
     * @var string
     */
    private $class;

    /**
     * @invisible
     * @var ORMInterface
     */
    private $orm;

    /**
     * @var RootLoader
     */
    private $loader;

    /**
     * @param string       $class
     * @param ORMInterface $orm
     */
    public function __construct(string $class, ORMInterface $orm)
    {
        $this->class = $class;
        $this->orm = $orm;

        $this->loader = new RootLoader(
            $class,
            $orm->define($class, ORMInterface::R_SCHEMA),
            $orm
        );
    }

    /**
     * Get associated ORM instance, can be used to create separate query/selection using same
     * (nested) memory scope for ORM cache.
     *
     * @see ORM::selector()
     * @return ORMInterface
     */
    public function getORM(): ORMInterface
    {
        return $this->orm;
    }

    /**
     * Get associated class.
     *
     * @return string
     */
    public function getClass(): string
    {
        return $this->class;
    }

    /**
     * Get alias used for primary table.
     *
     * @return string
     */
    public function getAlias(): string
    {
        return $this->loader->getAlias();
    }

    /**
     * Columns to be selected, please note, primary will always be included, DO not include
     * column aliases in here, aliases will be added automatically. Creates selector as response.
     *
     * @param array $columns
     *
     * @return RecordSelector
     */
    public function withColumns(array $columns): self
    {
        $selector = clone $this;
        $selector->loader = $selector->loader->withColumns($columns);

        return $selector;
    }

    /**
     * Request primary selector loader to pre-load relation name. Any type of loader can be used
     * for
     * data preloading. ORM loaders by default will select the most efficient way to load related
     * data which might include additional select query or left join. Loaded data will
     * automatically pre-populate record relations. You can specify nested relations using "."
     * separator.
     *
     * Examples:
     *
     * //Select users and load their comments (will cast 2 queries, HAS_MANY comments)
     * User::find()->with('comments');
     *
     * //You can load chain of relations - select user and load their comments and post related to
     * //comment
     * User::find()->with('comments.post');
     *
     * //We can also specify custom where conditions on data loading, let's load only public
     * comments. User::find()->load('comments', [
     *      'where' => ['{@}.status' => 'public']
     * ]);
     *
     * Please note using "{@}" column name, this placeholder is required to prevent collisions and
     * it will be automatically replaced with valid table alias of pre-loaded comments table.
     *
     * //In case where your loaded relation is MANY_TO_MANY you can also specify pivot table
     * conditions,
     * //let's pre-load all approved user tags, we can use same placeholder for pivot table alias
     * User::find()->load('tags', [
     *      'wherePivot' => ['{@}.approved' => true]
     * ]);
     *
     * //In most of cases you don't need to worry about how data was loaded, using external query
     * or
     * //left join, however if you want to change such behaviour you can force load method to
     * INLOAD
     * User::find()->load('tags', [
     *      'method'     => Loader::INLOAD,
     *      'wherePivot' => ['{@}.approved' => true]
     * ]);
     *
     * Attention, you will not be able to correctly paginate in this case and only ORM loaders
     * support different loading types.
     *
     * You can specify multiple loaders using array as first argument.
     *
     * Example:
     * User::find()->load(['posts', 'comments', 'profile']);
     *
     * Attention, consider disabling entity map if you want to use recursive loading (i.e.
     * post.tags.posts), but first think why you even need recursive relation loading.
     *
     * @see with()
     *
     * @param string|array $relation
     * @param array        $options
     *
     * @return $this|RecordSelector
     */
    public function load($relation, array $options = []): self
    {
        if (is_array($relation)) {
            foreach ($relation as $name => $subOption) {
                if (is_string($subOption)) {
                    //Array of relation names
                    $this->load($subOption, $options);
                } else {
                    //Multiple relations or relation with addition load options
                    $this->load($name, $subOption + $options);
                }
            }

            return $this;
        }

        //We are requesting primary loaded to pre-load nested relation
        $this->loader->loadRelation($relation, $options);

        return $this;
    }

    /**
     * With method is very similar to load() one, except it will always include related data to
     * parent query using INNER JOIN, this method can be applied only to ORM loaders and relations
     * using same database as parent record.
     *
     * Method generally used to filter data based on some relation condition.
     * Attention, with() method WILL NOT load relation data, it will only make it accessible in
     * query.
     *
     * By default joined tables will be available in query based on relation name, you can change
     * joined table alias using relation option "alias".
     *
     * Do not forget to set DISTINCT flag while including HAS_MANY and MANY_TO_MANY relations. In
     * other scenario you will not able to paginate data well.
     *
     * Examples:
     *
     * //Find all users who have comments comments
     * User::find()->with('comments');
     *
     * //Find all users who have approved comments (we can use comments table alias in where
     * statement).
     * User::find()->with('comments')->where('comments.approved', true);
     *
     * //Find all users who have posts which have approved comments
     * User::find()->with('posts.comments')->where('posts_comments.approved', true);
     *
     * //Custom join alias for post comments relation
     * $user->with('posts.comments', [
     *      'alias' => 'comments'
     * ])->where('comments.approved', true);
     *
     * //If you joining MANY_TO_MANY relation you will be able to use pivot table used as relation
     * name
     * //plus "_pivot" postfix. Let's load all users with approved tags.
     * $user->with('tags')->where('tags_pivot.approved', true);
     *
     * //You can also use custom alias for pivot table as well
     * User::find()->with('tags', [
     *      'pivotAlias' => 'tags_connection'
     * ])
     * ->where('tags_connection.approved', false);
     *
     * You can safely combine with() and load() methods.
     *
     * //Load all users with approved comments and pre-load all their comments
     * User::find()->with('comments')->where('comments.approved', true)
     *             ->load('comments');
     *
     * //You can also use custom conditions in this case, let's find all users with approved
     * comments
     * //and pre-load such approved comments
     * User::find()->with('comments')->where('comments.approved', true)
     *             ->load('comments', [
     *                  'where' => ['{@}.approved' => true]
     *              ]);
     *
     * //As you might notice previous construction will create 2 queries, however we can simplify
     * //this construction to use already joined table as source of data for relation via "using"
     * //keyword
     * User::find()->with('comments')
     *             ->where('comments.approved', true)
     *             ->load('comments', ['using' => 'comments']);
     *
     * //You will get only one query with INNER JOIN, to better understand this example let's use
     * //custom alias for comments in with() method.
     * User::find()->with('comments', ['alias' => 'commentsR'])
     *             ->where('commentsR.approved', true)
     *             ->load('comments', ['using' => 'commentsR']);
     *
     * @see load()
     *
     * @param string|array $relation
     * @param array        $options
     *
     * @return $this|RecordSelector
     */
    public function with($relation, array $options = []): self
    {
        if (is_array($relation)) {
            foreach ($relation as $name => $options) {
                if (is_string($options)) {
                    //Array of relation names
                    $this->with($options, []);
                } else {
                    //Multiple relations or relation with addition load options
                    $this->with($name, $options);
                }
            }

            return $this;
        }

        //Requesting primary loader to join nested relation, will only work for ORM loaders
        $this->loader->loadRelation($relation, $options, true);

        return $this;
    }

    /**
     * Shortcut to where method to set AND condition for parent record primary key.
     *
     * @param string|int $id
     *
     * @return RecordSelector
     *
     * @throws SelectorException
     */
    public function wherePK($id): self
    {
        if (empty($this->loader->primaryKey())) {
            //This MUST never happen due ORM requires PK now for every entity
            throw new SelectorException("Unable to set wherePK condition, no proper PK were defined");
        }

        //Adding condition to initial query
        $this->loader->initialQuery()->where([
            //Must be already aliased
            $this->loader->primaryKey() => $id
        ]);

        return $this;
    }

    /**
     * Find one entity or return null.
     *
     * @param array|null $query
     *
     * @return EntityInterface|null
     */
    public function findOne(array $query = null)
    {
        $data = (clone $this)->where($query)->fetchData();

        if (empty($data[0])) {
            return null;
        }

        return $this->orm->make($this->class, $data[0], ORMInterface::STATE_LOADED, true);
    }

    /**
     * Get RecordIterator (entity iterator) for a requested data. Provide cache key and lifetime in
     * order to cache request data.
     *
     * @param string              $cacheKey
     * @param int|\DateInterval   $ttl
     * @param CacheInterface|null $cache Can be automatically resoled via ORM container scope.
     *
     * @return RecordIterator|RecordInterface[]
     */
    public function getIterator(
        string $cacheKey = '',
        $ttl = 0,
        CacheInterface $cache = null
    ): RecordIterator {
        if (!empty($cacheKey)) {
            /**
             * When no cache is provided saturate it using container scope
             *
             * @var CacheInterface $cache
             */
            $cache = $this->saturate($cache, CacheInterface::class);

            if ($cache->has($cacheKey)) {
                $data = $cache->get($cacheKey);
            } else {
                //Cache parsed tree with all sub queries executed!
                $cache->set($cacheKey, $data = $this->fetchData(), $ttl);
            }
        } else {
            $data = $this->fetchData();
        }

        return new RecordIterator($data, $this->class, $this->orm);
    }

    /**
     * Attention, column will be quoted by driver!
     *
     * @param string|null $column When column is null DISTINCT(PK) will be generated.
     *
     * @return int
     */
    public function count(string $column = null): int
    {
        if (is_null($column)) {
            if (!empty($this->loader->primaryKey())) {
                //@tuneyourserver solves the issue with counting on queries with joins.
                $column = "DISTINCT({$this->loader->primaryKey()})";
            } else {
                $column = '*';
            }
        }

        return $this->compiledQuery()->count($column);
    }

    /**
     * Query used as basement for relation.
     *
     * @return SelectQuery
     */
    public function initialQuery(): SelectQuery
    {
        return $this->loader->initialQuery();
    }

    /**
     * Get compiled version of SelectQuery, attentionly only first level query access is allowed.
     *
     * @return SelectQuery
     */
    public function compiledQuery(): SelectQuery
    {
        return $this->loader->compiledQuery();
    }

    /**
     * Load data tree from databases and linked loaders in a form of array.
     *
     * @param OutputNode $node When empty node will be created automatically by root relation
     *                         loader.
     *
     * @return array
     */
    public function fetchData(OutputNode $node = null): array
    {
        /** @var OutputNode $node */
        $node = $node ?? $this->loader->createNode();

        //Working with parser defined by loader itself
        $this->loader->loadData($node);

        return $node->getResult();
    }

    /**
     * {@inheritdoc}
     */
    public function hasPaginator(): bool
    {
        return $this->loader->initialQuery()->hasPaginator();
    }

    /**
     * {@inheritdoc}
     */
    public function setPaginator(PaginatorInterface $paginator)
    {
        $this->loader->initialQuery()->setPaginator($paginator);
    }

    /**
     * {@inheritdoc}
     */
    public function getPaginator(): PaginatorInterface
    {
        return $this->loader->initialQuery()->getPaginator();
    }

    /**
     * Bypassing call to primary select query.
     *
     * @param string $name
     * @param        $arguments
     *
     * @return $this|mixed
     */
    public function __call(string $name, array $arguments)
    {
        if (in_array(strtoupper($name), ['AVG', 'MIN', 'MAX', 'SUM'])) {
            //One of aggregation requests
            $result = call_user_func_array([$this->compiledQuery(), $name], $arguments);
        } else {
            //Where condition or statement
            $result = call_user_func_array([$this->loader->initialQuery(), $name], $arguments);
        }

        if ($result === $this->loader->initialQuery()) {
            return $this;
        }

        return $result;
    }

    /**
     * Cloning with loader tree cloning.
     *
     * @attention at this moment binded query parameters would't be cloned!
     */
    public function __clone()
    {
        $this->loader = clone $this->loader;
    }

    /**
     * Remove nested loaders and clean ORM link.
     */
    public function __destruct()
    {
        $this->orm = null;
        $this->loader = null;
    }

    /**
     * @return \Interop\Container\ContainerInterface|null
     */
    protected function iocContainer()
    {
        if ($this->orm instanceof Component) {
            //Working inside ORM container scope
            return $this->orm->iocContainer();
        }

        return parent::iocContainer();
    }
}