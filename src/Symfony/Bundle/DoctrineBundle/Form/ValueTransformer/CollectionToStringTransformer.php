<?php

namespace Symfony\Bundle\DoctrineBundle\Form\ValueTransformer;

use Symfony\Component\Form\ValueTransformer\BaseValueTransformer;
use Symfony\Component\Form\ValueTransformer\TransformationFailedException;

/*
 * This file is part of the Symfony framework.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

/**
 * Transforms an instance of Doctrine\Common\Collections\Colletion into a string of unique names.
 *
 * Use-Cases for this transformer include: List of Tag-Names, List Of Group/User-Names or the like.
 * 
 * This transformer only makes sense if you know the list of related collections to be small and
 * that they have a unique identifier field that is of meaning to the user (Tag Names) and is
 * enforced to be unique in the storage.
 *
 * This transformer can cause the following SQL operations to happen in the case of an ORM collection:
 * 1. Initialize the whole collection using one SELECT query
 * 2. For each removed element issue an UPDATE or DELETE stmt (depending on one-to-many or many-to-many)
 * 3. For each inserted element issue an INSERT or UPDATE stmt (depending on one-to-many or many-to-many)
 * 4. Extra updates if necessary by the ORM.
 *
 * @todo Refactor to make 'fieldName' optional (identifier).
 *
 * @author Benjamin Eberlei <kontakt@beberlei.de>
 */
class CollectionToStringTransformer extends BaseValueTransformer
{
    protected function configure()
    {
        $this->addOption('trim', true);
        $this->addOption('separator', ',');
        $this->addOption('explodeCallback', 'explode');
        $this->addOption('implodeCallback', 'implode');
        $this->addOption('createInstanceCallback', null);
        $this->addRequiredOption('em');
        $this->addRequiredOption('className');
        $this->addRequiredOption('fieldName');
    }

    /**
     * @param string $value
     * @param Doctrine\Common\Collections\Collection $collection
     */
    public function reverseTransform($value, $collection)
    {
        if (strlen(trim($value)) == 0) {
            // don't check for collection count, a straight clear doesnt initialize the collection
            $collection->clear();
            return $collection;
        }

        $callback = $this->getOption('explodeCallback');
        $values = call_user_func($callback, $this->getOption('separator'), $value);

        if ($this->getOption('trim') === true) {
            $values = array_map('trim', $values);
        }

        /* @var $em Doctrine\ORM\EntityManager */
        $em = $this->getOption('em');
        $className = $this->getOption('className');
        $reflField = $em->getClassMetadata($className)
                        ->getReflectionProperty($this->getOption('fieldName'));

        // 1. removing elements that are not yet present anymore
        foreach ($collection AS $object) {
            $uniqueIdent = $reflField->getValue($object);
            $key = \array_search($uniqueIdent, $values);
            if ($key === false) {
                $collection->removeElement($object);
            } else {
                // found in the collection, no need to do anything with it so remove it
                unset($values[$key]);
            }
        }

        // 2. add elements that are known to the EntityManager but newly connected, query them from the repository
        if (count($values)) {
            $dql = "SELECT o FROM " . $className . " o WHERE o." . $this->getOption('fieldName') . " IN (";
            $query = $em->createQuery();
            $needles = array();
            $i = 0;
            foreach ($values AS $val) {
                $query->setParameter(++$i, $val);
                $needles[] = "?" . $i;
            }
            $dql .= implode(",", $needles) . ")";
            $query->setDql($dql);
            $newElements = $query->getResult();

            foreach ($newElements AS $object) {
                $collection->add($object);

                $uniqueIdent = $reflField->getValue($object);
                $key = \array_search($uniqueIdent, $values);
                unset($values[$key]);
            }
        }

        // 3. new elements that are not in the repository have to be created and persisted then attached:
        if (count($values)) {
            $callback = $this->getOption('createInstanceCallback');
            if (!$callback || !\is_callable($callback)) {
                throw new TransformationFailedException("Cannot transform list of identifiers, because a new ".
                    "element was detected and it is unknown how to create an instance of this element.");
            }

            foreach ($values AS $newValue) {
                $newInstance = \call_user_func($callback, $newValue);
                if (!($newInstance instanceof $className)) {
                    throw new TransformationFailedException("Error while trying to create a new instance for ".
                        "the identifier '" . $newValue . "'. No new instance was created.");
                }
                $collection->add($newInstance);
                $em->persist($newInstance);
            }
        }

        return $collection;
    }

    /**
     * Transform a Doctrine Collection into a string of identifies with a separator.
     *
     * @param  Doctrine\Common\Collections\Collection $value
     * @return string
     */
    public function transform($value)
    {
        $values = array();
        $em = $this->getOption('em');
        $reflField = $em->getClassMetadata($this->getOption('className'))
                        ->getReflectionProperty($this->getOption('fieldName'));

        foreach ($value AS $object) {
            $values[] = $reflField->getValue($object);
        }
        $callback = $this->getOption('implodeCallback');
        return call_user_func($callback, $this->getOption('separator'), $values);
    }
}