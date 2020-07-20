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

/**
 * Loads choices using a Doctrine object manager.
 *
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class DoctrineChoiceLoader extends AbstractChoiceLoader
{
    private $manager;
    private $class;
    private $idReader;
    private $objectLoader;

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

    /**
     * {@inheritdoc}
     */
    protected function loadChoices(): iterable
    {
        return $this->objectLoader
            ? $this->objectLoader->getEntities()
            : $this->manager->getRepository($this->class)->findAll();
    }

    /**
     * @internal to be remove in Symfony 6
     */
    protected function doLoadValuesForChoices(array $choices): array
    {
        // Optimize performance for single-field identifiers. We already
        // know that the IDs are used as values
        // Attention: This optimization does not check choices for existence
        if ($this->idReader) {
            trigger_deprecation('symfony/doctrine-bridge', '5.1', 'Not defining explicitly the IdReader as value callback when query can be optimized is deprecated. Don\'t pass the IdReader to "%s" or define the "choice_value" option instead.', __CLASS__);
            // Maintain order and indices of the given objects
            $values = [];
            foreach ($choices as $i => $object) {
                if ($object instanceof $this->class) {
                    $values[$i] = $this->idReader->getIdValue($object);
                }
            }

            return $values;
        }

        return parent::doLoadValuesForChoices($choices);
    }

    protected function doLoadChoicesForValues(array $values, ?callable $value): array
    {
        $legacy = $this->idReader && null === $value;

        if ($legacy) {
            trigger_deprecation('symfony/doctrine-bridge', '5.1', 'Not defining explicitly the IdReader as value callback when query can be optimized is deprecated. Don\'t pass the IdReader to "%s" or define the "choice_value" option instead.', __CLASS__);
        }

        // Optimize performance in case we have an object loader and
        // a single-field identifier
        if (($legacy || \is_array($value) && $this->idReader === $value[0]) && $this->objectLoader) {
            $objects = [];
            $objectsById = [];

            // Maintain order and indices from the given $values
            // An alternative approach to the following loop is to add the
            // "INDEX BY" clause to the Doctrine query in the loader,
            // but I'm not sure whether that's doable in a generic fashion.
            foreach ($this->objectLoader->getEntitiesByIds($this->idReader->getIdField(), $values) as $object) {
                $objectsById[$this->idReader->getIdValue($object)] = $object;
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
