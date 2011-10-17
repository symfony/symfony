<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bridge\Doctrine\Form\DataTransformer;

use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\DataTransformerInterface;
use Symfony\Component\Form\FormError;
use Symfony\Component\Form\Exception\TransformationFailedException;
use Symfony\Component\Form\Exception\UnexpectedTypeException;
use Symfony\Component\Form\Exception\FormException;
use Symfony\Component\Form\Util\PropertyPath;

use Doctrine\ORM\EntityManager;
use Doctrine\ORM\NoResultException;

class OneEntityToIdTransformer implements DataTransformerInterface
{
    private $em;
    private $class;
    private $property;
    private $queryBuilder;

    private $unitOfWork;

    public function __construct(EntityManager $em, $class, $property, $queryBuilder)
    {
        if (null !== $queryBuilder && ! $queryBuilder instanceof \Closure) {
            throw new UnexpectedTypeException($queryBuilder, '\Closure');
        } 

        if (null === $class) {
            throw new UnexpectedTypeException($class, 'string');
        }

        $this->em = $em;
        $this->unitOfWork = $em->getUnitOfWork();
        $this->class = $class;
        $this->queryBuilder = $queryBuilder;

        if ($property) {
            $this->property = $property;
        }
    }

    /**
     * Fetch the id of the entity to populate the form
     */
    public function transform($data)
    {
        if (null === $data) {
            return null;
        }
        if (!$this->unitOfWork->isInIdentityMap($data)) {
            throw new FormException('Entities passed to the choice field must be managed');
        }

        if ($this->property) {
            $propertyPath = new PropertyPath($this->property);
            return $propertyPath->getValue($data);
        }

        return current($this->unitOfWork->getEntityIdentifier($data));
    }

    /**
     * Try to fetch the entity from its id in the database
     */
    public function reverseTransform($data)
    {
        if (!$data) {
            return null;
        }

        $em = $this->em;
        $repository = $em->getRepository($this->class);

        if ($qb = $this->queryBuilder) {
            // Call the closure with the repository and the id
            $qb = $qb($repository, $data);

            try {
                $result = $qb->getQuery()->getSingleResult();
            } catch (NoResultException $e) {
                $result = null;
            }
        } else {
            // Defaults to find()
            if ($this->property) {
                $result = $repository->findOneBy(array($this->property => $data));
            } else {
                $result = $repository->find($data);
            }
        }

        if (!$result) {
            throw new TransformationFailedException('Can not find entity');
        }

        return $result;
    }
}

