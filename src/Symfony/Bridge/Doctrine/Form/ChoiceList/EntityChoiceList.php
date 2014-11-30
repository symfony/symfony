<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bridge\Doctrine\Form\ChoiceList;

use Symfony\Component\Form\Exception\RuntimeException;
use Symfony\Component\Form\Exception\StringCastException;
use Symfony\Component\Form\Extension\Core\ChoiceList\ObjectChoiceList;
use Doctrine\Common\Persistence\ObjectManager;
use Symfony\Component\PropertyAccess\PropertyAccessorInterface;

/**
 * A choice list presenting a list of Doctrine entities as choices
 *
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class EntityChoiceList extends ObjectChoiceList
{
    /**
     * @var ObjectManager
     */
    private $em;

    /**
     * @var string
     */
    private $class;

    /**
     * @var \Doctrine\Common\Persistence\Mapping\ClassMetadata
     */
    private $classMetadata;

    /**
     * Contains the query builder that builds the query for fetching the
     * entities
     *
     * This property should only be accessed through queryBuilder.
     *
     * @var EntityLoaderInterface
     */
    private $entityLoader;

    /**
     * The identifier field, if the identifier is not composite
     *
     * @var array
     */
    private $idField = null;

    /**
     * Whether to use the identifier for index generation
     *
     * @var bool
     */
    private $idAsIndex = false;

    /**
     * Whether to use the identifier for value generation
     *
     * @var bool
     */
    private $idAsValue = false;

    /**
     * Whether the entities have already been loaded.
     *
     * @var bool
     */
    private $loaded = false;

    /**
     * The preferred entities.
     *
     * @var array
     */
    private $preferredEntities = array();

    /**
     * Creates a new entity choice list.
     *
     * @param ObjectManager             $manager           An EntityManager instance
     * @param string                    $class             The class name
     * @param string                    $labelPath         The property path used for the label
     * @param EntityLoaderInterface     $entityLoader      An optional query builder
     * @param array|\Traversable|null   $entities          An array of choices or null to lazy load
     * @param array                     $preferredEntities An array of preferred choices
     * @param string                    $groupPath         A property path pointing to the property used
     *                                                     to group the choices. Only allowed if
     *                                                     the choices are given as flat array.
     * @param PropertyAccessorInterface $propertyAccessor  The reflection graph for reading property paths.
     */
    public function __construct(ObjectManager $manager, $class, $labelPath = null, EntityLoaderInterface $entityLoader = null, $entities = null,  array $preferredEntities = array(), $groupPath = null, PropertyAccessorInterface $propertyAccessor = null)
    {
        $this->em = $manager;
        $this->entityLoader = $entityLoader;
        $this->classMetadata = $manager->getClassMetadata($class);
        $this->class = $this->classMetadata->getName();
        $this->loaded = is_array($entities) || $entities instanceof \Traversable;
        $this->preferredEntities = $preferredEntities;

        $identifier = $this->classMetadata->getIdentifierFieldNames();

        if (1 === count($identifier)) {
            $this->idField = $identifier[0];
            $this->idAsValue = true;

            if (in_array($this->classMetadata->getTypeOfField($this->idField), array('integer', 'smallint', 'bigint'))) {
                $this->idAsIndex = true;
            }
        }

        if (!$this->loaded) {
            // Make sure the constraints of the parent constructor are
            // fulfilled
            $entities = array();
        }

        parent::__construct($entities, $labelPath, $preferredEntities, $groupPath, null, $propertyAccessor);
    }

    /**
     * Returns the list of entities
     *
     * @return array
     *
     * @see Symfony\Component\Form\Extension\Core\ChoiceList\ChoiceListInterface
     */
    public function getChoices()
    {
        if (!$this->loaded) {
            $this->load();
        }

        return parent::getChoices();
    }

    /**
     * Returns the values for the entities
     *
     * @return array
     *
     * @see Symfony\Component\Form\Extension\Core\ChoiceList\ChoiceListInterface
     */
    public function getValues()
    {
        if (!$this->loaded) {
            $this->load();
        }

        return parent::getValues();
    }

    /**
     * Returns the choice views of the preferred choices as nested array with
     * the choice groups as top-level keys.
     *
     * @return array
     *
     * @see Symfony\Component\Form\Extension\Core\ChoiceList\ChoiceListInterface
     */
    public function getPreferredViews()
    {
        if (!$this->loaded) {
            $this->load();
        }

        return parent::getPreferredViews();
    }

    /**
     * Returns the choice views of the choices that are not preferred as nested
     * array with the choice groups as top-level keys.
     *
     * @return array
     *
     * @see Symfony\Component\Form\Extension\Core\ChoiceList\ChoiceListInterface
     */
    public function getRemainingViews()
    {
        if (!$this->loaded) {
            $this->load();
        }

        return parent::getRemainingViews();
    }

    /**
     * Returns the entities corresponding to the given values.
     *
     * @param array $values
     *
     * @return array
     *
     * @see Symfony\Component\Form\Extension\Core\ChoiceList\ChoiceListInterface
     */
    public function getChoicesForValues(array $values)
    {
        // Performance optimization
        // Also prevents the generation of "WHERE id IN ()" queries through the
        // entity loader. At least with MySQL and on the development machine
        // this was tested on, no exception was thrown for such invalid
        // statements, consequently no test fails when this code is removed.
        // https://github.com/symfony/symfony/pull/8981#issuecomment-24230557
        if (empty($values)) {
            return array();
        }

        if (!$this->loaded) {
            // Optimize performance in case we have an entity loader and
            // a single-field identifier
            if ($this->idAsValue && $this->entityLoader) {
                $unorderedEntities = $this->entityLoader->getEntitiesByIds($this->idField, $values);
                $entitiesByValue = array();
                $entities = array();

                // Maintain order and indices from the given $values
                // An alternative approach to the following loop is to add the
                // "INDEX BY" clause to the Doctrine query in the loader,
                // but I'm not sure whether that's doable in a generic fashion.
                foreach ($unorderedEntities as $entity) {
                    $value = $this->fixValue(current($this->getIdentifierValues($entity)));
                    $entitiesByValue[$value] = $entity;
                }

                foreach ($values as $i => $value) {
                    if (isset($entitiesByValue[$value])) {
                        $entities[$i] = $entitiesByValue[$value];
                    }
                }

                return $entities;
            }

            $this->load();
        }

        return parent::getChoicesForValues($values);
    }

    /**
     * Returns the values corresponding to the given entities.
     *
     * @param array $entities
     *
     * @return array
     *
     * @see Symfony\Component\Form\Extension\Core\ChoiceList\ChoiceListInterface
     */
    public function getValuesForChoices(array $entities)
    {
        // Performance optimization
        if (empty($entities)) {
            return array();
        }

        if (!$this->loaded) {
            // Optimize performance for single-field identifiers. We already
            // know that the IDs are used as values

            // Attention: This optimization does not check choices for existence
            if ($this->idAsValue) {
                $values = array();

                foreach ($entities as $i => $entity) {
                    if ($entity instanceof $this->class) {
                        // Make sure to convert to the right format
                        $values[$i] = $this->fixValue(current($this->getIdentifierValues($entity)));
                    }
                }

                return $values;
            }

            $this->load();
        }

        return parent::getValuesForChoices($entities);
    }

    /**
     * Returns the indices corresponding to the given entities.
     *
     * @param array $entities
     *
     * @return array
     *
     * @see Symfony\Component\Form\Extension\Core\ChoiceList\ChoiceListInterface
     */
    public function getIndicesForChoices(array $entities)
    {
        // Performance optimization
        if (empty($entities)) {
            return array();
        }

        if (!$this->loaded) {
            // Optimize performance for single-field identifiers. We already
            // know that the IDs are used as indices

            // Attention: This optimization does not check choices for existence
            if ($this->idAsIndex) {
                $indices = array();

                foreach ($entities as $i => $entity) {
                    if ($entity instanceof $this->class) {
                        // Make sure to convert to the right format
                        $indices[$i] = $this->fixIndex(current($this->getIdentifierValues($entity)));
                    }
                }

                return $indices;
            }

            $this->load();
        }

        return parent::getIndicesForChoices($entities);
    }

    /**
     * Returns the entities corresponding to the given values.
     *
     * @param array $values
     *
     * @return array
     *
     * @see Symfony\Component\Form\Extension\Core\ChoiceList\ChoiceListInterface
     */
    public function getIndicesForValues(array $values)
    {
        // Performance optimization
        if (empty($values)) {
            return array();
        }

        if (!$this->loaded) {
            // Optimize performance for single-field identifiers.

            // Attention: This optimization does not check values for existence
            if ($this->idAsIndex && $this->idAsValue) {
                return $this->fixIndices($values);
            }

            $this->load();
        }

        return parent::getIndicesForValues($values);
    }

    /**
     * Creates a new unique index for this entity.
     *
     * If the entity has a single-field identifier, this identifier is used.
     *
     * Otherwise a new integer is generated.
     *
     * @param mixed $entity The choice to create an index for
     *
     * @return int|string A unique index containing only ASCII letters,
     *                    digits and underscores.
     */
    protected function createIndex($entity)
    {
        if ($this->idAsIndex) {
            return $this->fixIndex(current($this->getIdentifierValues($entity)));
        }

        return parent::createIndex($entity);
    }

    /**
     * Creates a new unique value for this entity.
     *
     * If the entity has a single-field identifier, this identifier is used.
     *
     * Otherwise a new integer is generated.
     *
     * @param mixed $entity The choice to create a value for
     *
     * @return int|string A unique value without character limitations.
     */
    protected function createValue($entity)
    {
        if ($this->idAsValue) {
            return (string) current($this->getIdentifierValues($entity));
        }

        return parent::createValue($entity);
    }

    /**
     * {@inheritdoc}
     */
    protected function fixIndex($index)
    {
        $index = parent::fixIndex($index);

        // If the ID is a single-field integer identifier, it is used as
        // index. Replace any leading minus by underscore to make it a valid
        // form name.
        if ($this->idAsIndex && $index < 0) {
            $index = strtr($index, '-', '_');
        }

        return $index;
    }

    /**
     * Loads the list with entities.
     */
    private function load()
    {
        if ($this->entityLoader) {
            $entities = $this->entityLoader->getEntities();
        } else {
            $entities = $this->em->getRepository($this->class)->findAll();
        }

        try {
            // The second parameter $labels is ignored by ObjectChoiceList
            parent::initialize($entities, array(), $this->preferredEntities);
        } catch (StringCastException $e) {
            throw new StringCastException(str_replace('argument $labelPath', 'option "property"', $e->getMessage()), null, $e);
        }

        $this->loaded = true;
    }

    /**
     * Returns the values of the identifier fields of an entity.
     *
     * Doctrine must know about this entity, that is, the entity must already
     * be persisted or added to the identity map before. Otherwise an
     * exception is thrown.
     *
     * @param object $entity The entity for which to get the identifier
     *
     * @return array The identifier values
     *
     * @throws RuntimeException If the entity does not exist in Doctrine's identity map
     */
    private function getIdentifierValues($entity)
    {
        if (!$this->em->contains($entity)) {
            throw new RuntimeException(
                'Entities passed to the choice field must be managed. Maybe '.
                'persist them in the entity manager?'
            );
        }

        $this->em->initializeObject($entity);

        return $this->classMetadata->getIdentifierValues($entity);
    }
}
