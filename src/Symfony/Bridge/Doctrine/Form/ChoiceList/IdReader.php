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
use Symfony\Component\Form\Exception\RuntimeException;

/**
 * A utility for reading object IDs.
 *
 * @author Bernhard Schussek <bschussek@gmail.com>
 *
 * @internal
 */
class IdReader
{
    private $om;
    private $classMetadata;
    private $singleId;
    private $intId;
    private $idField;

    /**
     * @var IdReader|null
     */
    private $associationIdReader;

    public function __construct(ObjectManager $om, ClassMetadata $classMetadata)
    {
        $ids = $classMetadata->getIdentifierFieldNames();
        $idType = $classMetadata->getTypeOfField(current($ids));

        $this->om = $om;
        $this->classMetadata = $classMetadata;
        $this->singleId = 1 === count($ids);
        $this->intId = $this->singleId && in_array($idType, array('integer', 'smallint', 'bigint'));
        $this->idField = current($ids);

        // single field association are resolved, since the schema column could be an int
        if ($this->singleId && $classMetadata->hasAssociation($this->idField)) {
            $this->associationIdReader = new self($om, $om->getClassMetadata(
                $classMetadata->getAssociationTargetClass($this->idField)
            ));

            $this->singleId = $this->associationIdReader->isSingleId();
            $this->intId = $this->associationIdReader->isIntId();
        }
    }

    /**
     * Returns whether the class has a single-column ID.
     *
     * @return bool returns `true` if the class has a single-column ID and
     *              `false` otherwise
     */
    public function isSingleId()
    {
        return $this->singleId;
    }

    /**
     * Returns whether the class has a single-column integer ID.
     *
     * @return bool returns `true` if the class has a single-column integer ID
     *              and `false` otherwise
     */
    public function isIntId()
    {
        return $this->intId;
    }

    /**
     * Returns the ID value for an object.
     *
     * This method assumes that the object has a single-column ID.
     *
     * @param object $object The object
     *
     * @return mixed The ID value
     */
    public function getIdValue($object)
    {
        if (!$object) {
            return;
        }

        if (!$this->om->contains($object)) {
            throw new RuntimeException(sprintf('Entity of type "%s" passed to the choice field must be managed. Maybe you forget to persist it in the entity manager?', get_class($object)));
        }

        $this->om->initializeObject($object);

        $idValue = current($this->classMetadata->getIdentifierValues($object));

        if ($this->associationIdReader) {
            $idValue = $this->associationIdReader->getIdValue($idValue);
        }

        return $idValue;
    }

    /**
     * Returns the name of the ID field.
     *
     * This method assumes that the object has a single-column ID.
     *
     * @return string The name of the ID field
     */
    public function getIdField()
    {
        return $this->idField;
    }
}
