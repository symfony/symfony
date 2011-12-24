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

use Symfony\Component\Form\Util\PropertyPath;
use Symfony\Component\Form\Exception\FormException;
use Symfony\Component\Form\Exception\UnexpectedTypeException;
use Symfony\Component\Form\Extension\Core\ChoiceList\ArrayChoiceList;
use Doctrine\Common\Persistence\ObjectManager;

class EntityChoiceList extends ArrayChoiceList
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
     * The entities from which the user can choose
     *
     * This array is either indexed by ID (if the ID is a single field)
     * or by key in the choices array (if the ID consists of multiple fields)
     *
     * This property is initialized by initializeChoices(). It should only
     * be accessed through getEntity() and getEntities().
     *
     * @var array
     */
    private $entities = array();

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
     * The fields of which the identifier of the underlying class consists
     *
     * This property should only be accessed through identifier.
     *
     * @var array
     */
    private $identifier = array();

    /**
     * Property path to access the key value of this choice-list.
     *
     * @var PropertyPath
     */
    private $propertyPath;

    /**
     * Closure or PropertyPath string on Entity to use for grouping of entities
     *
     * @var mixed
     */
    private $groupBy;

    /**
     * Constructor.
     *
     * @param ObjectManager         $manager           An EntityManager instance
     * @param string                $class        The class name
     * @param string                $property     The property name
     * @param EntityLoaderInterface $entityLoader An optional query builder
     * @param array|\Closure        $choices      An array of choices or a function returning an array
     * @param string                $groupBy
     */
    public function __construct(ObjectManager $manager, $class, $property = null, EntityLoaderInterface $entityLoader = null, $choices = null, $groupBy = null)
    {
        $this->em = $manager;
        $this->class = $class;
        $this->entityLoader = $entityLoader;
        $this->classMetadata = $manager->getClassMetadata($class);
        $this->identifier = $this->classMetadata->getIdentifierFieldNames();
        $this->groupBy = $groupBy;

        // The property option defines, which property (path) is used for
        // displaying entities as strings
        if ($property) {
            $this->propertyPath = new PropertyPath($property);
        } elseif (!method_exists($this->classMetadata->getName(), '__toString')) {
            // Otherwise expect a __toString() method in the entity
            throw new FormException('Entities passed to the choice field must have a "__toString()" method defined (or you can also override the "property" option).');
        }

        if (!is_array($choices) && !$choices instanceof \Closure && !is_null($choices)) {
            throw new UnexpectedTypeException($choices, 'array or \Closure or null');
        }

        $this->choices = $choices;
    }

    /**
     * Initializes the choices and returns them.
     *
     * If the entities were passed in the "choices" option, this method
     * does not have any significant overhead. Otherwise, if a query builder
     * was passed in the "query_builder" option, this builder is now used
     * to construct a query which is executed. In the last case, all entities
     * for the underlying class are fetched from the repository.
     *
     * @return array  An array of choices
     */
    protected function load()
    {
        parent::load();

        if (is_array($this->choices)) {
            $entities = $this->choices;
        } elseif ($entityLoader = $this->entityLoader) {
            $entities = $entityLoader->getEntities();
        } else {
            $entities = $this->em->getRepository($this->class)->findAll();
        }

        $this->choices = array();
        $this->entities = array();

        if ($this->groupBy) {
            $entities = $this->groupEntities($entities, $this->groupBy);
        }

        $this->loadEntities($entities);

        return $this->choices;
    }

    private function groupEntities($entities, $groupBy)
    {
        $grouped = array();
        $path   = new PropertyPath($groupBy);

        foreach ($entities as $entity) {
            // Get group name from property path
            try {
                $group  = (string) $path->getValue($entity);
            } catch (UnexpectedTypeException $e) {
                // PropertyPath cannot traverse entity
                $group = null;
            }

            if (empty($group)) {
                $grouped[] = $entity;
            } else {
                $grouped[$group][] = $entity;
            }
        }

        return $grouped;
    }

    /**
     * Converts entities into choices with support for groups.
     *
     * The choices are generated from the entities. If the entities have a
     * composite identifier, the choices are indexed using ascending integers.
     * Otherwise the identifiers are used as indices.
     *
     * If the option "property" was passed, the property path in that option
     * is used as option values. Otherwise this method tries to convert
     * objects to strings using __toString().
     *
     * @param array  $entities An array of entities
     * @param string $group    A group name
     */
    private function loadEntities($entities, $group = null)
    {
        foreach ($entities as $key => $entity) {
            if (is_array($entity)) {
                // Entities are in named groups
                $this->loadEntities($entity, $key);
                continue;
            }

            if ($this->propertyPath) {
                // If the property option was given, use it
                $value = $this->propertyPath->getValue($entity);
            } else {
                $value = (string) $entity;
            }

            if (count($this->identifier) > 1) {
                // When the identifier consists of multiple field, use
                // naturally ordered keys to refer to the choices
                $id = $key;
            } else {
                // When the identifier is a single field, index choices by
                // entity ID for performance reasons
                $id = current($this->getIdentifierValues($entity));
            }

            if (null === $group) {
                // Flat list of choices
                $this->choices[$id] = $value;
            } else {
                // Nested choices
                $this->choices[$group][$id] = $value;
            }

            $this->entities[$id] = $entity;
        }
    }

    /**
     * Returns the fields of which the identifier of the underlying class consists.
     *
     * @return array
     */
    public function getIdentifier()
    {
        return $this->identifier;
    }

    /**
     * Returns the according entities for the choices.
     *
     * If the choices were not initialized, they are initialized now. This
     * is an expensive operation, except if the entities were passed in the
     * "choices" option.
     *
     * @return array  An array of entities
     */
    public function getEntities()
    {
        if (!$this->loaded) {
            $this->load();
        }

        return $this->entities;
    }

    /**
     * Returns the entities for the given keys.
     *
     * If the underlying entities have composite identifiers, the choices
     * are initialized. The key is expected to be the index in the choices
     * array in this case.
     *
     * If they have single identifiers, they are either fetched from the
     * internal entity cache (if filled) or loaded from the database.
     *
     * @param  array $keys  The choice key (for entities with composite
     *                      identifiers) or entity ID (for entities with single
     *                      identifiers)
     * @return object[]     The matching entity
     */
    public function getEntitiesByKeys(array $keys)
    {
        if (!$this->loaded) {
            $this->load();
        }

        $found = array();

        foreach ($keys as $key) {
            if (isset($this->entities[$key])) {
                $found[] = $this->entities[$key];
            }
        }

        return $found;
    }

    /**
     * Returns the values of the identifier fields of an entity.
     *
     * Doctrine must know about this entity, that is, the entity must already
     * be persisted or added to the identity map before. Otherwise an
     * exception is thrown.
     *
     * @param  object $entity The entity for which to get the identifier
     *
     * @return array          The identifier values
     *
     * @throws FormException  If the entity does not exist in Doctrine's identity map
     */
    public function getIdentifierValues($entity)
    {
        if (!$this->em->contains($entity)) {
            throw new FormException('Entities passed to the choice field must be managed');
        }

        $this->em->initializeObject($entity);

        return $this->classMetadata->getIdentifierValues($entity);
    }
}
