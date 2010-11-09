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
 * Transforms a Doctrine Entity into its identifier value and back.
 *
 * This only works with single-field primary key fields.
 *
 * @author Benjamin Eberlei <kontakt@beberlei.de>
 */
class EntityToIDTransformer extends BaseValueTransformer
{
    protected function configure()
    {
        $this->addRequiredOption('em');
        $this->addRequiredOption('className');

        parent::configure();
    }

    /**
     * Reverse Transforming the selected id value to an Doctrine Entity.
     *
     * This handles NULL, the EntityManager#find method returns null if no entity was found.
     *
     * @param  int|string $newId
     * @param  object $oldEntity
     * @return object
     */
    public function reverseTransform($newId, $oldEntity)
    {
        if (empty($newId)) {
            return null;
        }

        return $this->getOption('em')->find($this->getOption('className'), $newId);
    }

    /**
     * @param  object $entity
     * @return int|string
     */
    public function transform($entity)
    {
        if (empty($entity)) {
            return 0;
        }

        return current( $this->getOption('em')->getUnitOfWork()->getEntityIdentifier($entity) );
    }
}