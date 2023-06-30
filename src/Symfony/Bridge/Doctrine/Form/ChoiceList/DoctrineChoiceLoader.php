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
use Symfony\Component\Form\ChoiceList\Loader\AbstractChoiceLoader;
use Symfony\Component\Form\Exception\LogicException;

/**
 * Loads choices using a Doctrine object manager.
 *
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class DoctrineChoiceLoader extends AbstractChoiceLoader
{
    private ObjectManager $manager;
    private string $class;
    private ?IdReader $idReader;
    private ?EntityLoaderInterface $objectLoader;

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
            throw new \InvalidArgumentException(sprintf('The second argument `$idReader` of "%s" must be null when the query cannot be optimized because of composite id fields.', __METHOD__));
        }

        $this->manager = $manager;
        $this->class = $classMetadata->getName();
        $this->idReader = $idReader;
        $this->objectLoader = $objectLoader;
    }

    protected function loadChoices(): iterable
    {
        return $this->objectLoader
            ? $this->objectLoader->getEntities()
            : $this->manager->getRepository($this->class)->findAll();
    }

    protected function doLoadValuesForChoices(array $choices): array
    {
        // Optimize performance for single-field identifiers. We already
        // know that the IDs are used as values
        // Attention: This optimization does not check choices for existence
        if ($this->idReader) {
            throw new LogicException('Not defining the IdReader explicitly as a value callback when the query can be optimized is not supported.');
        }

        return parent::doLoadValuesForChoices($choices);
    }

    protected function doLoadChoicesForValues(array $values, ?callable $value): array
    {
        if ($this->idReader && null === $value) {
            throw new LogicException('Not defining the IdReader explicitly as a value callback when the query can be optimized is not supported.');
        }

        $idReader = null;
        if (\is_array($value) && $value[0] instanceof IdReader) {
            $idReader = $value[0];
        } elseif ($value instanceof \Closure && ($rThis = (new \ReflectionFunction($value))->getClosureThis()) instanceof IdReader) {
            $idReader = $rThis;
        }

        // Optimize performance in case we have an object loader and
        // a single-field identifier
        if ($idReader && $this->objectLoader) {
            $objects = [];
            $objectsById = [];

            // Maintain order and indices from the given $values
            // An alternative approach to the following loop is to add the
            // "INDEX BY" clause to the Doctrine query in the loader,
            // but I'm not sure whether that's doable in a generic fashion.
            foreach ($this->objectLoader->getEntitiesByIds($idReader->getIdField(), $values) as $object) {
                $objectsById[$idReader->getIdValue($object)] = $object;
            }

            foreach ($values as $i => $id) {
                if (isset($objectsById[$id])) {
                    $objects[$i] = $objectsById[$id];
                }
            }

            return $objects;
        }

        return parent::doLoadChoicesForValues($values, $value);
    }
}
