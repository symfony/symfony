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

/**
 * Transforms a Collection into a Choice field used for Multiple Select fields or checkbox groups.
 *
 * @author Benjamin Eberlei <kontakt@beberlei.de>
 */
class CollectionToChoiceTransformer extends BaseValueTransformer
{
    protected function configure()
    {
        $this->addRequiredOption('em');
        $this->addRequiredOption('className');

        parent::configure();
    }

    /**
     * @param array $ids
     * @param Collection $collection
     */
    public function reverseTransform($ids, $collection)
    {
        if (count($ids) == 0) {
            // don't check for collection count, a straight clear doesnt initialize the collection
            $collection->clear();
            return $collection;
        }

        $em = $this->getOption('em');
        $metadata = $em->getClassMetadata($this->getOption('className'));
        $reflField = $metadata->getReflectionProperty($metadata->identifier[0]);

        foreach ($collection AS $object) {
            $key = array_search($reflField->getValue($object), $ids);
            if (false === $key) {
                $collection->removeElement($object);
            } else {
                unset($ids[$key]);
            }
        }

        // @todo: This can be greatly optimized into a single SELECT .. WHERE id IN () query.
        foreach ($ids AS $id) {
            $entity = $em->find($this->getOption('className'), $id);
            if (!$entity) {
                throw  new TransformationFailedException("Selected entity of type '" . $this->getOption('className') . "' by id '" . $id . "' which is not present in the database.");
            }
            $collection->add($entity);
        }

        return $collection;
    }

    /**
     * @param Collection $value
     */
    public function transform($value)
    {
        $metadata = $this->getOption('em')->getClassMetadata($this->getOption('className'));
        $reflField = $metadata->getReflectionProperty($metadata->identifier[0]);

        $ids = array();
        foreach ($value AS $object) {
            $ids[] = $reflField->getValue($object);
        }
        return $ids;
    }
}