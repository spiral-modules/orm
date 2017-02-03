<?php
/**
 * Spiral, Core Components
 *
 * @author Wolfy-J
 */

namespace Spiral\ORM\Entities\Loaders;

use Spiral\Database\Builders\SelectQuery;
use Spiral\Database\Injections\Parameter;
use Spiral\ORM\Entities\Loaders\Traits\WhereTrait;
use Spiral\ORM\Entities\Nodes\AbstractNode;
use Spiral\ORM\Entities\Nodes\ArrayNode;
use Spiral\ORM\ORMInterface;
use Spiral\ORM\Record;

/**
 * Dedicated to load HAS_MANY relation data, POSTLOAD is preferred loading method. Additional where
 * conditions and morph keys are supported.
 *
 * Please note that OUTER and INNER keys defined from perspective of parent (reversed for our
 * purposes).
 */
class HasManyLoader extends RelationLoader
{
    use WhereTrait;

    /**
     * Default set of relation options. Child implementation might defined their of default options.
     *
     * @var array
     */
    protected $options = [
        'method' => self::POSTLOAD,
        'minify' => true,
        'alias'  => null,
        'using'  => null,
        'where'  => null,
    ];

    /**
     * {@inheritdoc}
     */
    protected function configureQuery(SelectQuery $query, array $outerKeys = []): SelectQuery
    {
        if (!empty($this->options['using'])) {
            //Use pre-defined query
            return parent::configureQuery($query, $outerKeys);
        }
        
        if ($this->isJoined()) {
            $query->join(
                $this->getMethod() == self::JOIN ? 'INNER' : 'LEFT',
                "{$this->getTable()} AS {$this->getAlias()}")
                ->on(
                    $this->localKey(Record::OUTER_KEY),
                    $this->parentKey(Record::INNER_KEY)
                );
        } else {
            //This relation is loaded using external query
            $query->where(
                $this->localKey(Record::OUTER_KEY),
                'IN',
                new Parameter($outerKeys)
            );
        }

        //When relation is joined we will use ON statements, when not - normal WHERE
        $whereTarget = $this->isJoined() ? 'onWhere' : 'where';

        //Where conditions specified in relation definition
        $this->setWhere($query, $this->getAlias(), $whereTarget, $this->schema[Record::WHERE]);

        //User specified WHERE conditions
        $this->setWhere($query, $this->getAlias(), $whereTarget, $this->options['where']);

        //Morphed records
        if (!empty($this->schema[Record::MORPH_KEY])) {
            $this->setWhere(
                $query,
                $this->getAlias(),
                $whereTarget,
                [
                    $this->localKey(Record::MORPH_KEY) => $this->orm->define(
                        $this->parent->getClass(),
                        ORMInterface::R_ROLE_NAME
                    )
                ]
            );
        }

        return parent::configureQuery($query);
    }

    /**
     * {@inheritdoc}
     */
    protected function initNode(): AbstractNode
    {
        $node = new ArrayNode(
            $this->schema[Record::RELATION_COLUMNS],
            $this->schema[Record::OUTER_KEY],
            $this->schema[Record::INNER_KEY],
            $this->schema[Record::SH_PRIMARY_KEY]
        );

        return $node->asJoined($this->isJoined());
    }
}