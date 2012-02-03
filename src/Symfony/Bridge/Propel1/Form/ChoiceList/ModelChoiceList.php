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

use Symfony\Component\Form\Util\PropertyPath;
use Symfony\Component\Form\Exception\FormException;
use Symfony\Component\Form\Extension\Core\ChoiceList\ObjectChoiceList;

/**
 * Widely inspirated by the EntityChoiceList (Symfony2).
 *
 * @author William Durand <william.durand1@gmail.com>
 */
class ModelChoiceList extends ObjectChoiceList
{
    /**
     * The models from which the user can choose
     *
     * This array is either indexed by ID (if the ID is a single field)
     * or by key in the choices array (if the ID consists of multiple fields)
     *
     * This property is initialized by initializeChoices(). It should only
     * be accessed through getModel() and getModels().
     *
     * @var array
     */
    private $models = array();

    /**
     * The fields of which the identifier of the underlying class consists
     *
     * This property should only be accessed through identifier.
     *
     * @var array
     */
    private $identifier = array();

    /**
     * TableMap
     *
     * @var \TableMap
     */
    private $table = null;

    /**
     * Property path
     *
     * @var \Symfony\Component\Form\Util\PropertyPath
     */
    private $propertyPath = null;

    /**
     * Query
     */
    private $query = null;

    /**
     * @param string $class
     * @param string $property
     * @param array $choices
     * @param \ModelCriteria $queryObject
     */
    public function __construct($class, $property = null, $choices = array(), $queryObject = null)
    {
        $this->class        = $class;

        $queryClass         = $this->class . 'Query';
        $query              = new $queryClass();

        $this->table        = $query->getTableMap();
        $this->identifier   = $this->table->getPrimaryKeys();
        $this->query        = $queryObject ?: $query;

        // The property option defines, which property (path) is used for
        // displaying models as strings
        if ($property) {
            $this->propertyPath = new PropertyPath($property);
        }

        parent::__construct($choices);
    }

    public function getIdentifier()
    {
        return $this->identifier;
    }

    /**
     * Returns the according models for the choices
     *
     * If the choices were not initialized, they are initialized now. This
     * is an expensive operation, except if the models were passed in the
     * "choices" option.
     *
     * @return array  An array of models
     */
    public function getModels()
    {
        if (!$this->loaded) {
            $this->load();
        }

        return $this->models;
    }

    /**
     * Returns the model for the given key
     *
     * If the underlying models have composite identifiers, the choices
     * are intialized. The key is expected to be the index in the choices
     * array in this case.
     *
     * If they have single identifiers, they are either fetched from the
     * internal model cache (if filled) or loaded from the database.
     *
     * @param  string $key  The choice key (for models with composite
     *                      identifiers) or model ID (for models with single
     *                      identifiers)
     * @return object       The matching model
     */
    public function getModel($key)
    {
        if (!$this->loaded) {
            $this->load();
        }

        try {
            if (count($this->identifier) > 1) {
                // $key is a collection index
                $models = $this->getModels();

                return isset($models[$key]) ? $models[$key] : null;
            }

            if ($this->models) {
                return isset($this->models[$key]) ? $this->models[$key] : null;
            }

            $queryClass = $this->class . 'Query';

            return $queryClass::create()->findPk($key);
        } catch (NoResultException $e) {
            return null;
        }
    }

    /**
     * Returns the values of the identifier fields of an model
     *
     * Propel must know about this model, that is, the model must already
     * be persisted or added to the idmodel map before. Otherwise an
     * exception is thrown.
     *
     * @param  object $model  The model for which to get the identifier
     * @throws FormException   If the model does not exist
     */
    public function getIdentifierValues($model)
    {
        if ($model instanceof \BaseObject) {
            return array($model->getPrimaryKey());
        }

        return $model->getPrimaryKeys();
    }

    /**
     * Initializes the choices and returns them
     *
     * The choices are generated from the models. If the models have a
     * composite identifier, the choices are indexed using ascending integers.
     * Otherwise the identifiers are used as indices.
     *
     * If the models were passed in the "choices" option, this method
     * does not have any significant overhead. Otherwise, if a query object
     * was passed in the "query" option, this query is now used and executed.
     * In the last case, all models for the underlying class are fetched.
     *
     * If the option "property" was passed, the property path in that option
     * is used as option values. Otherwise this method tries to convert
     * objects to strings using __toString().
     *
     * @return array  An array of choices
     */
    protected function load()
    {
        parent::load();

        if ($this->choices) {
            $models = $this->choices;
        } else {
            $models = $this->query->find();
        }

        $this->choices = array();
        $this->models = array();

        foreach ($models as $key => $model) {
            if ($this->propertyPath) {
                // If the property option was given, use it
                $value = $this->propertyPath->getValue($model);
            } else {
                // Otherwise expect a __toString() method in the model
                $value = (string)$model;
            }

            if (count($this->identifier) > 1) {
                // When the identifier consists of multiple field, use
                // naturally ordered keys to refer to the choices
                $this->choices[$key] = $value;
                $this->models[$key] = $model;
            } else {
                // When the identifier is a single field, index choices by
                // model ID for performance reasons
                $id = current($this->getIdentifierValues($model));
                $this->choices[$id] = $value;
                $this->models[$id] = $model;
            }
        }
    }
}
