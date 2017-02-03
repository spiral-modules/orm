<?php
/**
 * Spiral, Core Components
 *
 * @author Wolfy-J
 */

namespace Spiral\ORM\Entities\Nodes;

use Spiral\ORM\Exceptions\NodeException;

/**
 * Represents data node in a tree with ability to parse line of results, split it into sub
 * relations, aggregate reference keys and etc.
 *
 * Nodes can be used as to parse one big and flat query, or when multiple queries provide their
 * data into one dataset, in both cases flow is identical from standpoint of Nodes (but offsets are
 * different).
 *
 * @todo NodeInterface, extension
 */
abstract class AbstractNode
{
    /**
     * Set of keys to be aggregated by Parser while parsing results.
     *
     * @var array
     */
    private $trackReferences = [];

    /**
     * Tree parts associated with reference keys and key values.
     *
     * $this->collectedReferences[id][ID_VALUE] = [ITEM1, ITEM2, ...].
     *
     * @var array
     */
    private $references = [];

    /**
     * Indicates that node data is joined to parent row.
     *
     * @var bool
     */
    private $joined = false;

    /**
     * Column names to be used to hydrate based on given query rows.
     *
     * @var array
     */
    protected $columns = [];

    /**
     * @var int
     */
    private $countColumns = 0;

    /**
     * Declared column which must be aggregated in a parent node. i.e. Parent Key
     *
     * @var null|string
     */
    protected $outerKey = null;

    /**
     * Node location in a tree. Set when node is registered.
     *
     * @invisible
     * @var string
     */
    protected $container;

    /**
     * @invisible
     * @var AbstractNode
     */
    protected $parent;

    /**
     * @var AbstractNode[]
     */
    protected $nodes = [];

    /**
     * @param array       $columns
     * @param string|null $outerKey Defines column name in parent Node to be aggregated.
     */
    public function __construct(array $columns, string $outerKey = null)
    {
        $this->columns = $columns;
        $this->countColumns = count($columns);
        $this->outerKey = $outerKey;
    }

    /**
     * Convert node into joined form (node will automatically parse parent row).
     *
     * @param bool $joined
     *
     * @return AbstractNode
     */
    public function asJoined(bool $joined = true)
    {
        $node = clone $this;
        $node->joined = $joined;

        return $node;
    }

    /**
     * Get list of reference key values aggregated by parent.
     *
     * @return array
     *
     * @throws NodeException
     */
    public function getReferences(): array
    {
        if (empty($this->parent)) {
            throw new NodeException("Unable to aggregate reference values, parent is missing");
        }

        if (empty($this->parent->references[$this->outerKey])) {
            return [];
        }

        return array_keys($this->parent->references[$this->outerKey]);
    }

    /**
     * Register new node into NodeTree. Nodes used to convert flat results into tree representation
     * using reference aggregations.
     *
     * @param string       $container
     * @param AbstractNode $node
     *
     * @throws NodeException
     */
    final public function registerNode(string $container, AbstractNode $node)
    {
        $node->container = $container;
        $node->parent = $this;

        $this->nodes[$container] = $node;

        if (!empty($node->outerKey)) {
            //This will make parser to aggregate such key in order to be used in later statement
            $this->trackReference($node->outerKey);
        }
    }

    /**
     * Destructing.
     */
    public function __destruct()
    {
        $this->parent = null;
        $this->nodes = [];
        $this->references = [];
        $this->trackReferences = [];
    }

    /**
     * Fetch sub node.
     *
     * @param string $container
     *
     * @return AbstractNode
     *
     * @throws NodeException
     */
    final public function fetchNode(string $container): AbstractNode
    {
        if (!isset($this->nodes[$container])) {
            throw new NodeException("Undefined node {$container}");
        }

        return $this->nodes[$container];
    }

    /**
     * Parser result work, fetch data and mount it into parent tree.
     *
     * @param int   $dataOffset
     * @param array $row
     *
     * @return int Must return number of handled columns.
     */
    final public function parseRow(int $dataOffset, array $row): int
    {
        //Fetching Node specific data from resulted row
        $data = $this->fetchData($dataOffset, $row);

        if ($this->deduplicate($data)) {
            //Create reference keys
            $this->collectReferences($data);

            //Make sure that all nested relations are registered
            $this->ensurePlaceholders($data);

            //Add data into result set
            $this->pushData($data);
        } elseif (!empty($this->parent)) {
            //Registering duplicates rows in each parent row
            $this->pushData($data);
        }

        $innerOffset = 0;
        foreach ($this->nodes as $container => $node) {
            if ($node->joined) {
                /**
                 * We are looking into branch like structure:
                 * node
                 *  - node
                 *      - node
                 *      - node
                 * node
                 *
                 * This means offset has to be calculated using all nested nodes
                 */
                $innerColumns = $node->parseRow($this->countColumns + $dataOffset, $row);

                //Counting next selection offset
                $dataOffset += $innerColumns;

                //Counting nested tree offset
                $innerOffset += $innerColumns;
            }
        }

        return $this->countColumns + $innerOffset;
    }

    /**
     * In many cases (for example if you have INLOAD of HAS_MANY relation) record data can be
     * replicated by many result rows (duplicated). To prevent incorrect data linking we have to
     * deduplicate such records.
     *
     * Method will return true if data is unique and handled previously or false in opposite case.
     *
     * Provided data array will be automatically linked with it's unique state using references
     * (pointer will receive different address).
     *
     * @param array $data Reference to parsed record data, reference will be pointed to valid and
     *                    existed data segment if such data was already parsed.
     *
     * @return bool Must return TRUE what data is unique in this selection.
     */
    abstract protected function deduplicate(array &$data): bool;

    /**
     * Register data result.
     *
     * @param array $data
     */
    abstract protected function pushData(array &$data);

    /**
     * Mount record data into internal data storage under specified container using reference key
     * (inner key) and reference criteria (outer key value).
     *
     * Example (default ORM Loaders):
     * $this->parent->mount('profile', 'id', 1, [
     *      'id' => 100,
     *      'user_id' => 1,
     *      ...
     * ]);
     *
     * In this example "id" argument is inner key of "user" record and it's linked to outer key
     * "user_id" in "profile" record, which defines reference criteria as 1.
     *
     * Attention, data WILL be referenced to new memory location!
     *
     * @param string $container
     * @param string $key
     * @param mixed  $criteria
     * @param array  $data      Data must be referenced to existed set if it was registered
     *                          previously.
     *
     * @throws NodeException
     */
    final protected function mount(
        string $container,
        string $key,
        $criteria,
        array &$data
    ) {
        if (!array_key_exists($criteria, $this->references[$key])) {
            throw new NodeException("Undefined reference {$key}.{$criteria}");
        }

        foreach ($this->references[$key][$criteria] as &$subset) {
            if (isset($subset[$container])) {
                //Back reference!
                $data = &$subset[$container];
            } else {
                $subset[$container] = &$data;
            }

            unset($subset);
        }
    }

    /**
     * Mount record data into internal data storage under specified container using reference key
     * (inner key) and reference criteria (outer key value).
     *
     * Example (default ORM Loaders):
     * $this->parent->mountArray('comments', 'id', 1, [
     *      'id' => 100,
     *      'user_id' => 1,
     *      ...
     * ]);
     *
     * In this example "id" argument is inner key of "user" record and it's linked to outer key
     * "user_id" in "profile" record, which defines reference criteria as 1.
     *
     * Add added records will be added as array items.
     *
     * @param string $container
     * @param string $key
     * @param mixed  $criteria
     * @param array  $data      Data must be referenced to existed set if it was registered
     *                          previously.
     *
     * @throws NodeException
     */
    final protected function mountArray(
        string $container,
        string $key,
        $criteria,
        array &$data
    ) {
        if (!array_key_exists($criteria, $this->references[$key])) {
            throw new NodeException("Undefined reference {$key}.{$criteria}");
        }

        foreach ($this->references[$key][$criteria] as &$subset) {
            if (!in_array($data, $subset[$container])) {
                $subset[$container][] = &$data;
            }

            unset($subset);
            continue;
        }
    }

    /**
     * Fetch record columns from query row, must use data offset to slice required part of query.
     *
     * @param int   $dataOffset
     * @param array $line
     *
     * @return array
     */
    protected function fetchData(int $dataOffset, array $line): array
    {
        try {
            //Combine column names with sliced piece of row
            return array_combine(
                $this->columns,
                array_slice($line, $dataOffset, $this->countColumns)
            );
        } catch (\Exception $e) {
            throw new NodeException("Unable to parse incoming row", $e->getCode(), $e);
        }
    }

    /**
     * Create internal references cache based on requested keys. For example, if we have request for
     * "id" as reference key, every record will create following structure:
     * $this->references[id][ID_VALUE] = ITEM.
     *
     * Only deduplicated data must be collected!
     *
     * @see deduplicate()
     *
     * @param array $data
     */
    private function collectReferences(array &$data)
    {
        foreach ($this->trackReferences as $key) {
            //Adding reference(s)
            $this->references[$key][$data[$key]][] = &$data;
        }
    }

    /**
     * Create placeholders for each of sub nodes.
     *
     * @param array $data
     */
    private function ensurePlaceholders(array &$data)
    {
        //Let's force placeholders for every sub loaded
        foreach ($this->nodes as $name => $node) {
            $data[$name] = $node instanceof ArrayInterface ? [] : null;
        }
    }

    /**
     * Add key to be tracked
     *
     * @param string $key
     *
     * @throws NodeException
     */
    private function trackReference(string $key)
    {
        if (!in_array($key, $this->columns)) {
            throw new NodeException("Unable to create reference, key {$key} does not exist");
        }

        if (!in_array($key, $this->trackReferences)) {
            //We are only tracking unique references
            $this->trackReferences[] = $key;
        }
    }
}