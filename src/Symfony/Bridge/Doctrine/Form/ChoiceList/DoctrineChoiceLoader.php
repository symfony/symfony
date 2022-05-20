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

use Doctrine\Persistence\ObjectManager;
use Symfony\Component\Form\ChoiceList\ArrayChoiceList;
use Symfony\Component\Form\ChoiceList\Loader\ChoiceLoaderInterface;

/**
 * Loads choices using a Doctrine object manager.
 *
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class DoctrineChoiceLoader implements ChoiceLoaderInterface
{
    private $manager;
    private $class;
    private $idReader;
    private $objectLoader;

    /**
     * @var array|null
     */
    private $choices;

    /**
     * Creates a new choice loader.
     *
     * Optionally, an implementation of {@link EntityLoaderInterface} can be
     * passed which optimizes the object loading for one of the Doctrine
     * mapper implementations.
     *
     * @param string $class The class name of the loaded objects
     */
    public function __construct(ObjectManager $manager, string $class, IdReader $idReader = null, EntityLoaderInterface $objectLoader = null)
    {
        $classMetadata = $manager->getClassMetadata($class);

        if ($idReader && !$idReader->isSingleId()) {
            @trigger_error(sprintf('Passing an instance of "%s" to "%s" with an entity class "%s" that has a composite id is deprecated since Symfony 4.3 and will throw an exception in 5.0.', IdReader::class, __CLASS__, $class), \E_USER_DEPRECATED);

            // In Symfony 5.0
            // throw new \InvalidArgumentException(sprintf('The second argument `$idReader` of "%s" must be null when the query cannot be optimized because of composite id fields.', __METHOD__));
        }

        if ((5 > \func_num_args() || false !== func_get_arg(4)) && null === $idReader) {
            $idReader = new IdReader($manager, $classMetadata);

            if ($idReader->isSingleId()) {
                @trigger_error(sprintf('Not explicitly passing an instance of "%s" to "%s" when it can optimize single id entity "%s" has been deprecated in 4.3 and will not apply any optimization in 5.0.', IdReader::class, __CLASS__, $class), \E_USER_DEPRECATED);
            } else {
                $idReader = null;
            }
        }

        $this->manager = $manager;
        $this->class = $classMetadata->getName();
        $this->idReader = $idReader;
        $this->objectLoader = $objectLoader;
    }

    /**
     * {@inheritdoc}
     */
    public function loadChoiceList($value = null)
    {
        if (null === $this->choices) {
            $this->choices = $this->objectLoader
                ? $this->objectLoader->getEntities()
                : $this->manager->getRepository($this->class)->findAll();
        }

        return new ArrayChoiceList($this->choices, $value);
    }

    /**
     * {@inheritdoc}
     */
    public function loadValuesForChoices(array $choices, $value = null)
    {
        // Performance optimization
        if (empty($choices)) {
            return [];
        }

        // Optimize performance for single-field identifiers. We already
        // know that the IDs are used as values
        $optimize = $this->idReader && (null === $value || \is_array($value) && $value[0] === $this->idReader);

        // Attention: This optimization does not check choices for existence
        if ($optimize && !$this->choices && $this->idReader->isSingleId()) {
            $values = [];

            // Maintain order and indices of the given objects
            foreach ($choices as $i => $object) {
                if ($object instanceof $this->class) {
                    // Make sure to convert to the right format
                    $values[$i] = (string) $this->idReader->getIdValue($object);
                }
            }

            return $values;
        }

        return $this->loadChoiceList($value)->getValuesForChoices($choices);
    }

    /**
     * {@inheritdoc}
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
            return [];
        }

        // Optimize performance in case we have an object loader and
        // a single-field identifier
        $optimize = $this->idReader && (null === $value || \is_array($value) && $this->idReader === $value[0]);

        if ($optimize && !$this->choices && $this->objectLoader && $this->idReader->isSingleId()) {
            $unorderedObjects = $this->objectLoader->getEntitiesByIds($this->idReader->getIdField(), $values);
            $objectsById = [];
            $objects = [];

            // Maintain order and indices from the given $values
            // An alternative approach to the following loop is to add the
            // "INDEX BY" clause to the Doctrine query in the loader,
            // but I'm not sure whether that's doable in a generic fashion.
            foreach ($unorderedObjects as $object) {
                $objectsById[(string) $this->idReader->getIdValue($object)] = $object;
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
