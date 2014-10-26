<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bridge\Propel1\Form\ChoiceList;

use \ModelCriteria;
use \BaseObject;
use \Persistent;
use Symfony\Component\Form\Exception\StringCastException;
use Symfony\Component\Form\Extension\Core\ChoiceList\ObjectChoiceList;
use Symfony\Component\OptionsResolver\Exception\InvalidOptionsException;
use Symfony\Component\OptionsResolver\Exception\MissingOptionsException;
use Symfony\Component\PropertyAccess\PropertyAccessorInterface;

/**
 * A choice list for object choices based on Propel model.
 *
 * @author William Durand <william.durand1@gmail.com>
 * @author Toni Uebernickel <tuebernickel@gmail.com>
 */
class ModelChoiceList extends ObjectChoiceList
{
    /**
     * The fields of which the identifier of the underlying class consists
     *
     * This property should only be accessed through identifier.
     *
     * @var array
     */
    protected $identifier = array();

    /**
     * The query to retrieve the choices of this list.
     *
     * @var ModelCriteria
     */
    protected $query;

    /**
     * The query to retrieve the preferred choices for this list.
     *
     * @var ModelCriteria
     */
    protected $preferredQuery;

    /**
     * Whether the model objects have already been loaded.
     *
     * @var bool
     */
    protected $loaded = false;

    /**
     * Whether to use the identifier for index generation.
     *
     * @var bool
     */
    private $identifierAsIndex = false;

    /**
     * Constructor.
     *
     * @see \Symfony\Bridge\Propel1\Form\Type\ModelType How to use the preferred choices.
     *
     * @param string                    $class            The FQCN of the model class to be loaded.
     * @param string                    $labelPath        A property path pointing to the property used for the choice labels.
     * @param array                     $choices          An optional array to use, rather than fetching the models.
     * @param ModelCriteria             $queryObject      The query to use retrieving model data from database.
     * @param string                    $groupPath        A property path pointing to the property used to group the choices.
     * @param array|ModelCriteria       $preferred        The preferred items of this choice.
     *                                                    Either an array if $choices is given,
     *                                                    or a ModelCriteria to be merged with the $queryObject.
     * @param PropertyAccessorInterface $propertyAccessor The reflection graph for reading property paths.
     * @param string                    $useAsIdentifier  a custom unique column (eg slug) to use instead of primary key.
     *
     * @throws MissingOptionsException In case the class parameter is empty.
     * @throws InvalidOptionsException In case the query class is not found.
     */
    public function __construct($class, $labelPath = null, $choices = null, $queryObject = null, $groupPath = null, $preferred = array(), PropertyAccessorInterface $propertyAccessor = null, $useAsIdentifier = null)
    {
        $this->class = $class;

        $queryClass = $this->class.'Query';
        if (!class_exists($queryClass)) {
            if (empty($this->class)) {
                throw new MissingOptionsException('The "class" parameter is empty, you should provide the model class');
            }
            throw new InvalidOptionsException(sprintf('The query class "%s" is not found, you should provide the FQCN of the model class', $queryClass));
        }

        $query = new $queryClass();

        $this->query = $queryObject ?: $query;
        if ($useAsIdentifier) {
            $this->identifier = array( $this->query->getTableMap()->getColumn($useAsIdentifier) );
        } else {
            $this->identifier = $this->query->getTableMap()->getPrimaryKeys();
        }

        $this->loaded = is_array($choices) || $choices instanceof \Traversable;

        if ($preferred instanceof ModelCriteria) {
            $this->preferredQuery = $preferred->mergeWith($this->query);
        }

        if (!$this->loaded) {
            // Make sure the constraints of the parent constructor are
            // fulfilled
            $choices = array();
            $preferred = array();
        }

        if (1 === count($this->identifier) && $this->isScalar(current($this->identifier))) {
            $this->identifierAsIndex = true;
        }

        parent::__construct($choices, $labelPath, $preferred, $groupPath, null, $propertyAccessor);
    }

    /**
     * Returns the class name of the model.
     *
     * @return string
     */
    public function getClass()
    {
        return $this->class;
    }

    /**
     * {@inheritdoc}
     */
    public function getChoices()
    {
        $this->load();

        return parent::getChoices();
    }

    /**
     * {@inheritdoc}
     */
    public function getValues()
    {
        $this->load();

        return parent::getValues();
    }

    /**
     * {@inheritdoc}
     */
    public function getPreferredViews()
    {
        $this->load();

        return parent::getPreferredViews();
    }

    /**
     * {@inheritdoc}
     */
    public function getRemainingViews()
    {
        $this->load();

        return parent::getRemainingViews();
    }

    /**
     * {@inheritdoc}
     */
    public function getChoicesForValues(array $values)
    {
        if (empty($values)) {
            return array();
        }

        /**
         * This performance optimization reflects a common scenario:
         * * A simple select of a model entry.
         * * The choice option "expanded" is set to false.
         * * The current request is the submission of the selected value.
         *
         * @see \Symfony\Component\Form\Extension\Core\DataTransformer\ChoicesToValuesTransformer::reverseTransform
         * @see \Symfony\Component\Form\Extension\Core\DataTransformer\ChoiceToValueTransformer::reverseTransform
         */
        if (!$this->loaded) {
            if (1 === count($this->identifier)) {
                $filterBy = 'filterBy'.current($this->identifier)->getPhpName();

                // The initial query is cloned, so all additional filters are applied correctly.
                $query = clone $this->query;
                $result = (array) $query
                    ->$filterBy($values)
                    ->find();

                // Preserve the keys as provided by the values.
                $models = array();
                foreach ($values as $index => $value) {
                    foreach ($result as $eachModel) {
                        if ($value === $this->createValue($eachModel)) {
                            // Make sure to convert to the right format
                            $models[$index] = $this->fixChoice($eachModel);

                            // If all values have been assigned, skip further loops.
                            unset($values[$index]);
                            if (0 === count($values)) {
                                break 2;
                            }
                        }
                    }
                }

                return $models;
            }
        }

        $this->load();

        return parent::getChoicesForValues($values);
    }

    /**
     * {@inheritdoc}
     */
    public function getValuesForChoices(array $models)
    {
        if (empty($models)) {
            return array();
        }

        if (!$this->loaded) {
            /**
             * This performance optimization assumes the validation of the respective values will be done by other means.
             *
             * It correlates with the performance optimization in {@link ModelChoiceList::getChoicesForValues()}
             * as it won't load the actual entries from the database.
             *
             * @see \Symfony\Component\Form\Extension\Core\DataTransformer\ChoicesToValuesTransformer::transform
             * @see \Symfony\Component\Form\Extension\Core\DataTransformer\ChoiceToValueTransformer::transform
             */
            if (1 === count($this->identifier)) {
                $values = array();
                foreach ($models as $index => $model) {
                    if ($model instanceof $this->class) {
                        // Make sure to convert to the right format
                        $values[$index] = $this->fixValue(current($this->getIdentifierValues($model)));
                    }
                }

                return $values;
            }
        }

        $this->load();

        $values = array();
        $availableValues = $this->getValues();

        /*
         * Overwriting default implementation.
         *
         * The two objects may represent the same entry in the database,
         * but if they originated from different queries, there are not the same object within the code.
         *
         * This happens when using m:n relations with either sides model as data_class of the form.
         * The choicelist will retrieve the list of available related models with a different query, resulting in different objects.
         */
        $choices = $this->fixChoices($models);
        foreach ($choices as $i => $givenChoice) {
            if (null === $givenChoice) {
                continue;
            }

            foreach ($this->getChoices() as $j => $choice) {
                if ($this->isEqual($choice, $givenChoice)) {
                    $values[$i] = $availableValues[$j];

                    // If all choices have been assigned, skip further loops.
                    unset($choices[$i]);
                    if (0 === count($choices)) {
                        break 2;
                    }
                }
            }
        }

        return $values;
    }

    /**
     * {@inheritdoc}
     *
     * @deprecated Deprecated since version 2.4, to be removed in 3.0.
     */
    public function getIndicesForChoices(array $models)
    {
        if (empty($models)) {
            return array();
        }

        $this->load();

        $indices = array();

        /*
         * Overwriting default implementation.
         *
         * The two objects may represent the same entry in the database,
         * but if they originated from different queries, there are not the same object within the code.
         *
         * This happens when using m:n relations with either sides model as data_class of the form.
         * The choicelist will retrieve the list of available related models with a different query, resulting in different objects.
         */
        $choices = $this->fixChoices($models);
        foreach ($choices as $i => $givenChoice) {
            if (null === $givenChoice) {
                continue;
            }

            foreach ($this->getChoices() as $j => $choice) {
                if ($this->isEqual($choice, $givenChoice)) {
                    $indices[$i] = $j;

                    // If all choices have been assigned, skip further loops.
                    unset($choices[$i]);
                    if (0 === count($choices)) {
                        break 2;
                    }
                }
            }
        }

        return $indices;
    }

    /**
     * {@inheritdoc}
     *
     * @deprecated Deprecated since version 2.4, to be removed in 3.0.
     */
    public function getIndicesForValues(array $values)
    {
        if (empty($values)) {
            return array();
        }

        $this->load();

        return parent::getIndicesForValues($values);
    }

    /**
     * Creates a new unique index for this model.
     *
     * If the model has a single-field identifier, this identifier is used.
     *
     * Otherwise a new integer is generated.
     *
     * @param mixed $model The choice to create an index for
     *
     * @return int|string     A unique index containing only ASCII letters,
     *                        digits and underscores.
     */
    protected function createIndex($model)
    {
        if ($this->identifierAsIndex) {
            return current($this->getIdentifierValues($model));
        }

        return parent::createIndex($model);
    }

    /**
     * Creates a new unique value for this model.
     *
     * If the model has a single-field identifier, this identifier is used.
     *
     * Otherwise a new integer is generated.
     *
     * @param mixed $model The choice to create a value for
     *
     * @return int|string     A unique value without character limitations.
     */
    protected function createValue($model)
    {
        if ($this->identifierAsIndex) {
            return (string) current($this->getIdentifierValues($model));
        }

        return parent::createValue($model);
    }

    /**
     * Loads the complete choice list entries, once.
     *
     * If data has been loaded the choice list is initialized with the retrieved data.
     */
    private function load()
    {
        if ($this->loaded) {
            return;
        }

        $models = (array) $this->query->find();

        $preferred = array();
        if ($this->preferredQuery instanceof ModelCriteria) {
            $preferred = (array) $this->preferredQuery->find();
        }

        try {
            // The second parameter $labels is ignored by ObjectChoiceList
            parent::initialize($models, array(), $preferred);

            $this->loaded = true;
        } catch (StringCastException $e) {
            throw new StringCastException(str_replace('argument $labelPath', 'option "property"', $e->getMessage()), null, $e);
        }
    }

    /**
     * Returns the values of the identifier fields of a model.
     *
     * Propel must know about this model, that is, the model must already
     * be persisted or added to the idmodel map before. Otherwise an
     * exception is thrown.
     *
     * @param object $model The model for which to get the identifier
     *
     * @return array
     */
    private function getIdentifierValues($model)
    {
        if (!$model instanceof $this->class) {
            return array();
        }

        if (1 === count($this->identifier) && current($this->identifier) instanceof \ColumnMap) {
            $phpName = current($this->identifier)->getPhpName();

            if (method_exists($model, 'get'.$phpName)) {
                return array($model->{'get'.$phpName}());
            }
        }

        if ($model instanceof Persistent) {
            return array($model->getPrimaryKey());
        }

        // readonly="true" models do not implement Persistent.
        if ($model instanceof BaseObject && method_exists($model, 'getPrimaryKey')) {
            return array($model->getPrimaryKey());
        }

        if (!method_exists($model, 'getPrimaryKeys')) {
            return array();
        }

        return $model->getPrimaryKeys();
    }

    /**
     * Whether this column contains scalar values (to be used as indices).
     *
     * @param \ColumnMap $column
     *
     * @return bool
     */
    private function isScalar(\ColumnMap $column)
    {
        return in_array($column->getPdoType(), array(
            \PDO::PARAM_BOOL,
            \PDO::PARAM_INT,
            \PDO::PARAM_STR,
        ));
    }

    /**
     * Check the given choices for equality.
     *
     * @param mixed $choice
     * @param mixed $givenChoice
     *
     * @return bool
     */
    private function isEqual($choice, $givenChoice)
    {
        if ($choice === $givenChoice) {
            return true;
        }

        if ($this->getIdentifierValues($choice) === $this->getIdentifierValues($givenChoice)) {
            return true;
        }

        return false;
    }
}
