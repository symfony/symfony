<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Form;

use Symfony\Component\Form\ValueTransformer\TransformationFailedException;
use Symfony\Component\Form\Exception\FormException;
use Symfony\Component\Form\Exception\InvalidOptionsException;
use Doctrine\Common\Collections\Collection;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\QueryBuilder;
use Doctrine\ORM\NoResultException;

/**
 * A field for selecting one or more from a list of Doctrine 2 entities
 *
 * You at least have to pass the entity manager and the entity class in the
 * options "em" and "class".
 *
 * <code>
 * $form->add(new EntityChoiceField('tags', array(
 *     'em' => $em,
 *     'class' => 'Application\Entity\Tag',
 * )));
 * </code>
 *
 * Additionally to the options in ChoiceField, the following options are
 * available:
 *
 *  * em:             The entity manager. Required.
 *  * class:          The class of the selectable entities. Required.
 *  * property:       The property displayed as value of the choices. If this
 *                    option is not available, the field will try to convert
 *                    objects into strings using __toString().
 *  * query_builder:  The query builder for fetching the selectable entities.
 *                    You can also pass a closure that receives the repository
 *                    as single argument and returns a query builder.
 *
 * The following sample outlines the use of the "query_builder" option
 * with closures.
 *
 * <code>
 * $form->add(new EntityChoiceField('tags', array(
 *     'em' => $em,
 *     'class' => 'Application\Entity\Tag',
 *     'query_builder' => function ($repository) {
 *         return $repository->createQueryBuilder('t')->where('t.enabled = 1');
 *     },
 * )));
 * </code>
 *
 * @author Bernhard Schussek <bernhard.schussek@symfony.com>
 */
class EntityChoiceField extends ChoiceField
{
    /**
     * The entities from which the user can choose
     *
     * This array is either indexed by ID (if the ID is a single field)
     * or by key in the choices array (if the ID consists of multiple fields)
     *
     * This property is initialized by initializeChoices(). It should only
     * be accessed through getEntity() and getEntities().
     *
     * @var Collection
     */
    protected $entities = null;

    /**
     * Contains the query builder that builds the query for fetching the
     * entities
     *
     * This property should only be accessed through getQueryBuilder().
     *
     * @var Doctrine\ORM\QueryBuilder
     */
    protected $queryBuilder = null;

    /**
     * The fields of which the identifier of the underlying class consists
     *
     * This property should only be accessed through getIdentifierFields().
     *
     * @var array
     */
    protected $identifier = array();

    /**
     * A cache for \ReflectionProperty instances for the underlying class
     *
     * This property should only be accessed through getReflProperty().
     *
     * @var array
     */
    protected $reflProperties = array();

    /**
     * A cache for the UnitOfWork instance of Doctrine
     *
     * @var Doctrine\ORM\UnitOfWork
     */
    protected $unitOfWork = null;

    /**
     * {@inheritDoc}
     */
    protected function configure()
    {
        $this->addRequiredOption('em');
        $this->addRequiredOption('class');
        $this->addOption('property');
        $this->addOption('query_builder');

        // Override option - it is not required for this subclass
        $this->addOption('choices', array());

        parent::configure();

        // The entities can be passed directly in the "choices" option.
        // In this case, initializing the entity cache is a cheap operation
        // so do it now!
        if (is_array($this->getOption('choices')) && count($this->getOption('choices')) > 0) {
            $this->initializeChoices();
        }

        // If a query builder was passed, it must be a closure or QueryBuilder
        // instance
        if ($qb = $this->getOption('query_builder')) {
            if (!($qb instanceof QueryBuilder || $qb instanceof \Closure)) {
                throw new InvalidOptionsException(
                    'The option "query_builder" most contain a closure or a QueryBuilder instance',
                    array('query_builder'));
            }
        }
    }

    /**
     * Returns the query builder instance for the choices of this field
     *
     * @return Doctrine\ORM\QueryBuilder  The query builder
     * @throws InvalidOptionsException    When the query builder was passed as
     *                                    closure and that closure does not
     *                                    return a QueryBuilder instance
     */
    protected function getQueryBuilder()
    {
        if (!$this->getOption('query_builder')) {
            return null;
        }

        if (!$this->queryBuilder) {
            $qb = $this->getOption('query_builder');

            if ($qb instanceof \Closure) {
                $class = $this->getOption('class');
                $em = $this->getOption('em');
                $qb = $qb($em->getRepository($class));

                if (!$qb instanceof QueryBuilder) {
                    throw new InvalidOptionsException(
                        'The closure in the option "query_builder" should return a QueryBuilder instance',
                        array('query_builder'));
                }
            }

            $this->queryBuilder = $qb;
        }

        return $this->queryBuilder;
    }

    /**
     * Returns the unit of work of the entity manager
     *
     * This object is cached for faster lookups.
     *
     * @return Doctrine\ORM\UnitOfWork  The unit of work
     */
    protected function getUnitOfWork()
    {
        if (!$this->unitOfWork) {
            $this->unitOfWork = $this->getOption('em')->getUnitOfWork();
        }

        return $this->unitOfWork;
    }

    /**
     * Initializes the choices and returns them
     *
     * The choices are generated from the entities. If the entities have a
     * composite identifier, the choices are indexed using ascending integers.
     * Otherwise the identifiers are used as indices.
     *
     * If the entities were passed in the "choices" option, this method
     * does not have any significant overhead. Otherwise, if a query builder
     * was passed in the "query_builder" option, this builder is now used
     * to construct a query which is executed. In the last case, all entities
     * for the underlying class are fetched from the repository.
     *
     * If the option "property" was passed, the property path in that option
     * is used as option values. Otherwise this method tries to convert
     * objects to strings using __toString().
     *
     * @return array  An array of choices
     */
    protected function getInitializedChoices()
    {
        if ($this->getOption('choices')) {
            $entities = parent::getInitializedChoices();
        } else if ($qb = $this->getQueryBuilder()) {
            $entities = $qb->getQuery()->execute();
        } else {
            $class = $this->getOption('class');
            $em = $this->getOption('em');
            $entities = $em->getRepository($class)->findAll();
        }

        $propertyPath = null;
        $choices = array();
        $this->entities = array();

        // The propery option defines, which property (path) is used for
        // displaying entities as strings
        if ($this->getOption('property')) {
            $propertyPath = new PropertyPath($this->getOption('property'));
        }

        foreach ($entities as $key => $entity) {
            if ($propertyPath) {
                // If the property option was given, use it
                $value = $propertyPath->getValue($entity);
            } else {
                // Otherwise expect a __toString() method in the entity
                $value = (string)$entity;
            }

            if (count($this->getIdentifierFields()) > 1) {
                // When the identifier consists of multiple field, use
                // naturally ordered keys to refer to the choices
                $choices[$key] = $value;
                $this->entities[$key] = $entity;
            } else {
                // When the identifier is a single field, index choices by
                // entity ID for performance reasons
                $id = current($this->getIdentifierValues($entity));
                $choices[$id] = $value;
                $this->entities[$id] = $entity;
            }
        }

        return $choices;
    }

    /**
     * Returns the according entities for the choices
     *
     * If the choices were not initialized, they are initialized now. This
     * is an expensive operation, except if the entities were passed in the
     * "choices" option.
     *
     * @return array  An array of entities
     */
    protected function getEntities()
    {
        if (!$this->entities) {
            // indirectly initializes the entities property
            $this->initializeChoices();
        }

        return $this->entities;
    }

    /**
     * Returns the entity for the given key
     *
     * If the underlying entities have composite identifiers, the choices
     * are initialized. The key is expected to be the index in the choices
     * array in this case.
     *
     * If they have single identifiers, they are either fetched from the
     * internal entity cache (if filled) or loaded from the database.
     *
     * @param  string $key  The choice key (for entities with composite
     *                      identifiers) or entity ID (for entities with single
     *                      identifiers)
     * @return object       The matching entity
     */
    protected function getEntity($key)
    {
        $id = $this->getIdentifierFields();

        if (count($id) > 1) {
            // $key is a collection index
            $entities = $this->getEntities();
            return $entities[$key];
        } else if ($this->entities) {
            return $this->entities[$key];
        } else if ($qb = $this->getQueryBuilder()) {
            // should we clone the builder?
            $alias = $qb->getRootAlias();
            $where = $qb->expr()->eq($alias.'.'.current($id), $key);

            return $qb->andWhere($where)->getQuery()->getSingleResult();
        }

        return $this->getOption('em')->find($this->getOption('class'), $key);
    }

    /**
     * Returns the \ReflectionProperty instance for a property of the
     * underlying class
     *
     * @param  string $property     The name of the property
     * @return \ReflectionProperty  The reflection instance
     */
    protected function getReflProperty($property)
    {
        if (!isset($this->reflProperties[$property])) {
            $this->reflProperties[$property] = new \ReflectionProperty($this->getOption('class'), $property);
            $this->reflProperties[$property]->setAccessible(true);
        }

        return $this->reflProperties[$property];
    }

    /**
     * Returns the fields included in the identifier of the underlying class
     *
     * @return array  An array of field names
     */
    protected function getIdentifierFields()
    {
        if (!$this->identifier) {
            $metadata = $this->getOption('em')->getClassMetadata($this->getOption('class'));
            $this->identifier = $metadata->getIdentifierFieldNames();
        }

        return $this->identifier;
    }

    /**
     * Returns the values of the identifier fields of an entity
     *
     * Doctrine must know about this entity, that is, the entity must already
     * be persisted or added to the identity map before. Otherwise an
     * exception is thrown.
     *
     * @param  object $entity  The entity for which to get the identifier
     * @throws FormException   If the entity does not exist in Doctrine's
     *                         identity map
     */
    protected function getIdentifierValues($entity)
    {
        if (!$this->getUnitOfWork()->isInIdentityMap($entity)) {
            throw new FormException('Entities passed to the choice field must be managed');
        }

        return $this->getUnitOfWork()->getEntityIdentifier($entity);
    }

    /**
     * Merges the selected and deselected entities into the collection passed
     * when calling setData()
     *
     * @see parent::processData()
     */
    protected function processData($data)
    {
        // reuse the existing collection to optimize for Doctrine
        if ($data instanceof Collection) {
            $currentData = $this->getData();

            if (!$currentData) {
                $currentData = $data;
            } else if (count($data) === 0) {
                $currentData->clear();
            } else {
                // merge $data into $currentData
                foreach ($currentData as $entity) {
                    if (!$data->contains($entity)) {
                        $currentData->removeElement($entity);
                    } else {
                        $data->removeElement($entity);
                    }
                }

                foreach ($data as $entity) {
                    $currentData->add($entity);
                }
            }

            return $currentData;
        }

        return $data;
    }

    /**
     * Transforms choice keys into entities
     *
     * @param  mixed $keyOrKeys   An array of keys, a single key or NULL
     * @return Collection|object  A collection of entities, a single entity
     *                            or NULL
     */
    protected function reverseTransform($keyOrKeys)
    {
        $keyOrKeys = parent::reverseTransform($keyOrKeys);

        if (null === $keyOrKeys) {
            return $this->getOption('multiple') ? new ArrayCollection() : null;
        }

        $notFound = array();

        if (count($this->getIdentifierFields()) > 1) {
            $notFound = array_diff((array)$keyOrKeys, array_keys($this->getEntities()));
        } else if ($this->entities) {
            $notFound = array_diff((array)$keyOrKeys, array_keys($this->entities));
        }

        if (0 === count($notFound)) {
            if (is_array($keyOrKeys)) {
                $result = new ArrayCollection();

                // optimize this into a SELECT WHERE IN query
                foreach ($keyOrKeys as $key) {
                    try {
                        $result->add($this->getEntity($key));
                    } catch (NoResultException $e) {
                        $notFound[] = $key;
                    }
                }
            } else {
                try {
                    $result = $this->getEntity($keyOrKeys);
                } catch (NoResultException $e) {
                    $notFound[] = $keyOrKeys;
                }
            }
        }

        if (count($notFound) > 0) {
            throw new TransformationFailedException('The entities with keys "%s" could not be found', implode('", "', $notFound));
        }

        return $result;
    }

    /**
     * Transforms entities into choice keys
     *
     * @param  Collection|object  A collection of entities, a single entity or
     *                            NULL
     * @return mixed              An array of choice keys, a single key or
     *                            NULL
     */
    protected function transform($collectionOrEntity)
    {
        if (null === $collectionOrEntity) {
            return $this->getOption('multiple') ? array() : '';
        }

        if (count($this->identifier) > 1) {
            // load all choices
            $availableEntities = $this->getEntities();

            if ($collectionOrEntity instanceof Collection) {
                $result = array();

                foreach ($collectionOrEntity as $entity) {
                    // identify choices by their collection key
                    $key = array_search($entity, $availableEntities);
                    $result[] = $key;
                }
            } else {
                $result = array_search($collectionOrEntity, $availableEntities);
            }
        } else {
            if ($collectionOrEntity instanceof Collection) {
                $result = array();

                foreach ($collectionOrEntity as $entity) {
                    $result[] = current($this->getIdentifierValues($entity));
                }
            } else {
                $result = current($this->getIdentifierValues($collectionOrEntity));
            }
        }


        return parent::transform($result);
    }
}