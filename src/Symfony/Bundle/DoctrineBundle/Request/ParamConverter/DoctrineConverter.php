<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\DoctrineBundle\Request\ParamConverter;

use Symfony\Bundle\FrameworkBundle\Request\ParamConverter\ConverterInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpFoundation\Request;
use Doctrine\ORM\NoResultException;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\Mapping\MappingException;

/**
 * DoctrineConverter.
 *
 * @author Fabien Potencier <fabien.potencier@symfony-project.com>
 */
class DoctrineConverter implements ConverterInterface
{
    protected $manager;

    public function __construct(EntityManager $manager)
    {
        $this->manager = $manager;
    }

    /**
     * Convert the \ReflectionParameter to something else.
     *
     * @param Request              $request
     * @param \ReflectionParameter $property
     */
    public function apply(Request $request, \ReflectionParameter $parameter)
    {
        $class = $parameter->getClass()->getName();

        // find by identifier?
        if (false === $object = $this->find($class, $request)) {
            // find by criteria
            if (false === $object = $this->findOneBy($class, $request)) {
                throw new \LogicException('Unable to guess how to get a Doctrine instance from the request information.');
            }
        }

        if (null === $object) {
            throw new NotFoundHttpException(sprintf('%s object not found.', $class));
        }

        $request->attributes->set($parameter->getName(), $object);
    }

    protected function find($class, Request $request)
    {
        if (!$request->attributes->has('id')) {
            return false;
        }

        return $this->manager->getRepository($class)->find($request->attributes->get('id'));
    }

    protected function findOneBy($class, Request $request)
    {
        $criteria = array();
        $metadata = $this->manager->getClassMetadata($class);
        foreach ($request->attributes->all() as $key => $value) {
            if ($metadata->hasField($key)) {
                $criteria[$key] = $value;
            }
        }

        if (!$criteria) {
            return false;
        }

        return $this->manager->getRepository($class)->findOneBy($criteria);
    }

    /**
     * Returns boolean true if the ReflectionClass is supported, false otherwise
     *
     * @param  \ReflectionParameter $parameter
     *
     * @return Boolean
     */
    public function supports(\ReflectionClass $class)
    {
        // Doctrine Entity?
        try {
            $this->manager->getClassMetadata($class->getName());

            return true;
        } catch (MappingException $e) {
            return false;
        }
    }
}
