<?php
/**
 * Spiral, Core Components
 *
 * @author Wolfy-J
 */

namespace Spiral\ORM\Helpers;

use Doctrine\Common\Inflector\Inflector;
use Spiral\ORM\Exceptions\OptionsException;
use Spiral\ORM\Record;
use Spiral\ORM\Schemas\Definitions\RelationDefinition;

/**
 * Provides ability to work with user defined relations and fill missing options based on source
 * and target contexts.
 */
class RelationOptions
{
    /**
     * @var RelationDefinition
     */
    private $definition;

    /**
     * Most of relations provides ability to specify many different configuration options, such
     * as key names, pivot table schemas, foreign key request, ability to be nullabe and etc.
     *
     * To simple schema definition in real projects we can fill some of this values automatically
     * based on some "environment" values such as parent/outer record table, role name, primary key
     * and etc.
     *
     * Example:
     * Record::INNER_KEY => '{outer:role}_{outer:primaryKey}'
     *
     * Result:
     * Outer Record is User with primary key "id" => "user_id"
     *
     * @var array
     */
    private $template;

    /**
     * Options calculated based on values provided by RelationDefinition and calculated via
     * template.
     *
     * @var array
     */
    private $options;

    /**
     * @param RelationDefinition $definition
     * @param array              $template Relation options template.
     */
    public function __construct(RelationDefinition $definition, array $template = [])
    {
        $this->definition = $definition;
        $this->template = $template;
        $this->options = $this->calculateOptions($definition->getOptions());
    }

    /**
     * Get value for a specific option. Attention, option MUST exist in template in order to
     * be retrievable.
     *
     * @param string $option
     *
     * @return mixed
     *
     * @throws OptionsException
     */
    public function define(string $option)
    {
        if (!array_key_exists($option, $this->options)) {
            throw new OptionsException("Undefined relation option '{$option}'");
        }

        return $this->options[$option];
    }

    /**
     * All relation options.
     *
     * @param array $options Options to be defined.
     *
     * @return array
     */
    public function defineMultiple(array $options): array
    {
        return array_intersect_key($this->options, array_flip($options));
    }

    /**
     * Calculate options based on given template
     *
     * @param array $userOptions Options provided by user.
     *
     * @return array Calculated options.
     */
    protected function calculateOptions(array $userOptions): array
    {
        foreach ($this->template as $property => $pattern) {
            if (isset($userOptions[$property])) {
                //Specified by user
                continue;
            }

            if (!is_string($pattern)) {
                //Some options are actually array of options
                $userOptions[$property] = $pattern;
                continue;
            }

            //Let's create option value using default proposer values
            $userOptions[$property] = \Spiral\interpolate(
                $pattern,
                $this->proposedOptions($userOptions)
            );
        }

        return $userOptions;
    }

    /**
     * Create set of options to specify missing relation definition fields.
     *
     * @param array $userOptions User options.
     *
     * @return array
     */
    protected function proposedOptions(array $userOptions): array
    {
        $source = $this->definition->sourceContext();

        $proposed = [
            //Relation name
            'relation:name'     => $this->definition->getName(),

            //Relation name in plural form
            'relation:plural'   => Inflector::pluralize($this->definition->getName()),

            //Relation name in singular form
            'relation:singular' => Inflector::singularize($this->definition->getName()),

            //Parent record role name
            'source:role'       => $source->getRole(),

            //Parent record table name
            'source:table'      => $source->getTable(),

            //Parent record primary key
            'source:primaryKey' => $source->getPrimary()->getName(),
        ];

        //Some options may use values declared in other definition fields
        $aliases = [
            Record::OUTER_KEY   => 'outerKey',
            Record::INNER_KEY   => 'innerKey',
            Record::PIVOT_TABLE => 'pivotTable',
        ];

        foreach ($aliases as $property => $alias) {
            if (isset($userOptions[$property])) {
                //Let's create some default options based on user specified values
                $proposed['option:' . $alias] = $userOptions[$property];
            }
        }

        if (!empty($this->definition->targetContext())) {
            $target = $this->definition->targetContext();

            $proposed = $proposed + [
                    //Outer role name
                    'target:role'       => $target->getRole(),

                    //Outer record table
                    'target:table'      => $target->getTable(),

                    //Outer record primary key
                    'target:primaryKey' => !empty($target->getPrimary()) ? $target->getPrimary()->getName() : '',
                ];
        }

        return $proposed;
    }
}