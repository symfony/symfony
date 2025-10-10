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

use Doctrine\Persistence\Mapping\ClassMetadata;
use Doctrine\Persistence\ObjectManager;
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
    private readonly bool $singleId;
    private readonly bool $intId;
    private readonly string $idField;
    private readonly ?self $associationIdReader;

    public function __construct(
        private readonly ObjectManager $om,
        private readonly ClassMetadata $classMetadata,
    ) {
        $ids = $classMetadata->getIdentifierFieldNames();
        $idType = $classMetadata->getTypeOfField(current($ids));

        $singleId = 1 === \count($ids);
        $this->idField = current($ids);

        // single field association are resolved, since the schema column could be an int
        if ($singleId && $classMetadata->hasAssociation($this->idField)) {
            $this->associationIdReader = new self($om, $om->getClassMetadata(
                $classMetadata->getAssociationTargetClass($this->idField)
            ));

            $singleId = $this->associationIdReader->isSingleId();
            $this->intId = $this->associationIdReader->isIntId();
        } else {
            $this->intId = $singleId && \in_array($idType, ['integer', 'smallint', 'bigint'], true);
            $this->associationIdReader = null;
        }

        $this->singleId = $singleId;
    }

    /**
     * Returns whether the class has a single-column ID.
     */
    public function isSingleId(): bool
    {
        return $this->singleId;
    }

    /**
     * Returns whether the class has a single-column integer ID.
     */
    public function isIntId(): bool
    {
        return $this->intId;
    }

    /**
     * Returns the ID value for an object.
     *
     * This method assumes that the object has a single-column ID.
     */
    public function getIdValue(?object $object = null): string
    {
        if (!$object) {
            return '';
        }

        if (!$this->om->contains($object)) {
            throw new RuntimeException(\sprintf('Entity of type "%s" passed to the choice field must be managed. Maybe you forget to persist it in the entity manager?', get_debug_type($object)));
        }

        $this->om->initializeObject($object);

        $idValue = current($this->classMetadata->getIdentifierValues($object));

        if ($this->associationIdReader) {
            $idValue = $this->associationIdReader->getIdValue($idValue);
        }

        return (string) $idValue;
    }

    /**
     * Returns the name of the ID field.
     *
     * This method assumes that the object has a single-column ID.
     */
    public function getIdField(): string
    {
        return $this->idField;
    }
}
