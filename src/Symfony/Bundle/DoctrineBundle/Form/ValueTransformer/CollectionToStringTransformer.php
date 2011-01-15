<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\DoctrineBundle\Form\ValueTransformer;

use Symfony\Component\Form\ValueTransformer\BaseValueTransformer;
use Symfony\Component\Form\ValueTransformer\TransformationFailedException;
use Doctrine\Common\Collections\Collection;

/**
 * Transforms an instance of Doctrine\Common\Collections\Collection into a string of unique names.
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
        $this->addOption('explode_callback', 'explode');
        $this->addOption('implode_callback', 'implode');
        $this->addOption('create_instance_callback', null);
        $this->addRequiredOption('em');
        $this->addRequiredOption('class_name');
        $this->addRequiredOption('field_name');

        parent::configure();
    }

    /**
     * @param string     $value
     * @param Collection $collection
     */
    public function reverseTransform($value, $collection)
    {
        if (strlen(trim($value)) == 0) {
            // don't check for collection count, a straight clear doesnt initialize the collection
            $collection->clear();
            return $collection;
        }

        $callback = $this->getOption('explode_callback');
        $values = call_user_func($callback, $this->getOption('separator'), $value);

        if ($this->getOption('trim') === true) {
            $values = array_map('trim', $values);
        }

        /* @var $em Doctrine\ORM\EntityManager */
        $em = $this->getOption('em');
        $className = $this->getOption('class_name');
        $reflField = $em->getClassMetadata($className)
                        ->getReflectionProperty($this->getOption('field_name'));

        // 1. removing elements that are not yet present anymore
        foreach ($collection as $object) {
            $uniqueIdent = $reflField->getValue($object);
            $key = array_search($uniqueIdent, $values);
            if (false === $key) {
                $collection->removeElement($object);
            } else {
                // found in the collection, no need to do anything with it so remove it
                unset($values[$key]);
            }
        }

        // 2. add elements that are known to the EntityManager but newly connected, query them from the repository
        if (count($values)) {
            $dql = sprintf('SELECT o FROM %s o WHERE o.%s IN (', $className, $this->getOption('field_name'));
            $query = $em->createQuery();
            $needles = array();
            $i = 0;
            foreach ($values as $val) {
                $query->setParameter(++$i, $val);
                $needles[] = '?'.$i;
            }
            $dql .= implode(',', $needles).')';
            $query->setDql($dql);
            $newElements = $query->getResult();

            foreach ($newElements as $object) {
                $collection->add($object);

                $uniqueIdent = $reflField->getValue($object);
                $key = array_search($uniqueIdent, $values);
                unset($values[$key]);
            }
        }

        // 3. new elements that are not in the repository have to be created and persisted then attached:
        if (count($values)) {
            $callback = $this->getOption('create_instance_callback');
            if (!$callback || !is_callable($callback)) {
                throw new TransformationFailedException('Cannot transform list of identifiers, because a new element was detected and it is unknown how to create an instance of this element.');
            }

            foreach ($values as $newValue) {
                $newInstance = call_user_func($callback, $newValue);
                if (!($newInstance instanceof $className)) {
                    throw new TransformationFailedException(sprintf('Error while trying to create a new instance for the identifier "%s". No new instance was created.', $newValue));
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
     * @param  Collection $value
     * @return string
     */
    public function transform($value)
    {
        if (null === $value) {
            return '';
        }

        $values = array();
        $em = $this->getOption('em');
        $reflField = $em->getClassMetadata($this->getOption('class_name'))
                        ->getReflectionProperty($this->getOption('field_name'));

        foreach ($value as $object) {
            $values[] = $reflField->getValue($object);
        }
        $callback = $this->getOption('implode_callback');

        return call_user_func($callback, $this->getOption('separator'), $values);
    }
}
