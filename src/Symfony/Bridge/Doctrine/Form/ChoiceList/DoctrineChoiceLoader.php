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

use Doctrine\Common\Persistence\Mapping\ClassMetadata;
use Doctrine\Common\Persistence\ObjectManager;
use Symfony\Component\Form\ChoiceList\ChoiceListInterface;
use Symfony\Component\Form\ChoiceList\Factory\ChoiceListFactoryInterface;
use Symfony\Component\Form\ChoiceList\Loader\ChoiceLoaderInterface;
use Symfony\Component\Form\Exception\RuntimeException;

/**
 * Loads choices using a Doctrine object manager.
 *
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class DoctrineChoiceLoader implements ChoiceLoaderInterface
{
    /**
     * @var ChoiceListFactoryInterface
     */
    private $factory;

    /**
     * @var ObjectManager
     */
    private $manager;

    /**
     * @var string
     */
    private $class;

    /**
     * @var ClassMetadata
     */
    private $classMetadata;

    /**
     * @var null|EntityLoaderInterface
     */
    private $objectLoader;

    /**
     * The identifier field, unless the identifier is composite
     *
     * @var null|string
     */
    private $idField = null;

    /**
     * Whether to use the identifier for value generation
     *
     * @var bool
     */
    private $compositeId = true;

    /**
     * @var ChoiceListInterface
     */
    private $choiceList;

    /**
     * Returns the value of the identifier field of an object.
     *
     * Doctrine must know about this object, that is, the object must already
     * be persisted or added to the identity map before. Otherwise an
     * exception is thrown.
     *
     * This method assumes that the object has a single-column identifier and
     * will return a single value instead of an array.
     *
     * @param object $object The object for which to get the identifier
     *
     * @return int|string The identifier value
     *
     * @throws RuntimeException If the object does not exist in Doctrine's identity map
     *
     * @internal Should not be accessed by user-land code. This method is public
     *           only to be usable as callback.
     */
    public static function getIdValue(ObjectManager $om, ClassMetadata $classMetadata, $object)
    {
        if (!$om->contains($object)) {
            throw new RuntimeException(
                'Entities passed to the choice field must be managed. Maybe '.
                'persist them in the entity manager?'
            );
        }

        $om->initializeObject($object);

        return current($classMetadata->getIdentifierValues($object));
    }

    /**
     * Creates a new choice loader.
     *
     * Optionally, an implementation of {@link EntityLoaderInterface} can be
     * passed which optimizes the object loading for one of the Doctrine
     * mapper implementations.
     *
     * @param ChoiceListFactoryInterface $factory      The factory for creating
     *                                                 the loaded choice list
     * @param ObjectManager              $manager      The object manager
     * @param string                     $class        The class name of the
     *                                                 loaded objects
     * @param null|EntityLoaderInterface $objectLoader The objects loader
     */
    public function __construct(ChoiceListFactoryInterface $factory, ObjectManager $manager, $class, EntityLoaderInterface $objectLoader = null)
    {
        $this->factory = $factory;
        $this->manager = $manager;
        $this->classMetadata = $manager->getClassMetadata($class);
        $this->class = $this->classMetadata->getName();
        $this->objectLoader = $objectLoader;

        $identifier = $this->classMetadata->getIdentifierFieldNames();

        if (1 === count($identifier)) {
            $this->idField = $identifier[0];
            $this->compositeId = false;
        }
    }

    /**
     * {@inheritdoc}
     */
    public function loadChoiceList($value = null)
    {
        if ($this->choiceList) {
            return $this->choiceList;
        }

        $objects = $this->objectLoader
            ? $this->objectLoader->getEntities()
            : $this->manager->getRepository($this->class)->findAll();

        // If the class has a multi-column identifier, we cannot index the
        // objects by their IDs
        if ($this->compositeId) {
            $this->choiceList = $this->factory->createListFromChoices($objects, $value);

            return $this->choiceList;
        }

        // Index the objects by ID
        $objectsById = array();

        foreach ($objects as $object) {
            $id = self::getIdValue($this->manager, $this->classMetadata, $object);
            $objectsById[$id] = $object;
        }

        $this->choiceList = $this->factory->createListFromChoices($objectsById, $value);

        return $this->choiceList;
    }

    /**
     * Loads the values corresponding to the given objects.
     *
     * The values are returned with the same keys and in the same order as the
     * corresponding objects in the given array.
     *
     * Optionally, a callable can be passed for generating the choice values.
     * The callable receives the object as first and the array key as the second
     * argument.
     *
     * @param array $objects       An array of objects. Non-existing objects in
     *                             this array are ignored
     * @param null|callable $value The callable generating the choice values
     *
     * @return string[] An array of choice values
     */
    public function loadValuesForChoices(array $objects, $value = null)
    {
        // Performance optimization
        if (empty($objects)) {
            return array();
        }

        // Optimize performance for single-field identifiers. We already
        // know that the IDs are used as values

        // Attention: This optimization does not check choices for existence
        if (!$this->choiceList && !$this->compositeId) {
            $values = array();

            // Maintain order and indices of the given objects
            foreach ($objects as $i => $object) {
                if ($object instanceof $this->class) {
                    // Make sure to convert to the right format
                    $values[$i] = (string) self::getIdValue($this->manager, $this->classMetadata, $object);
                }
            }

            return $values;
        }

        return $this->loadChoiceList($value)->getValuesForChoices($objects);
    }

    /**
     * Loads the objects corresponding to the given values.
     *
     * The objects are returned with the same keys and in the same order as the
     * corresponding values in the given array.
     *
     * Optionally, a callable can be passed for generating the choice values.
     * The callable receives the object as first and the array key as the second
     * argument.
     *
     * @param string[] $values     An array of choice values. Non-existing
     *                             values in this array are ignored
     * @param null|callable $value The callable generating the choice values
     *
     * @return array An array of objects
     */
    public function loadChoicesForValues(array $values, $value = null)
    {
        // Performance optimization
        // Also prevents the generation of "WHERE id IN ()" queries through the
        // object loader. At least with MySQL and on the development machine
        // this was tested on, no exception was thrown for such invalid
        // statements, consequently no test fails when this code is removed.
        // https://github.com/symfony/symfony/pull/8981#issuecomment-24230557
        if (empty($values)) {
            return array();
        }

        // Optimize performance in case we have an object loader and
        // a single-field identifier
        if (!$this->choiceList && !$this->compositeId && $this->objectLoader) {
            $unorderedObjects = $this->objectLoader->getEntitiesByIds($this->idField, $values);
            $objectsById = array();
            $objects = array();

            // Maintain order and indices from the given $values
            // An alternative approach to the following loop is to add the
            // "INDEX BY" clause to the Doctrine query in the loader,
            // but I'm not sure whether that's doable in a generic fashion.
            foreach ($unorderedObjects as $object) {
                $id = self::getIdValue($this->manager, $this->classMetadata, $object);
                $objectsById[$id] = $object;
            }

            foreach ($values as $i => $id) {
                if (isset($objectsById[$id])) {
                    $objects[$i] = $objectsById[$id];
                }
            }

            return $objects;
        }

        return $this->loadChoiceList($value)->getChoicesForValues($values);
    }
}
