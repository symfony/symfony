<?php

namespace Symfony\Bundle\DoctrineMongoDBBundle\Security;

use Symfony\Component\Security\User\UserProviderInterface;
use Symfony\Component\Security\Exception\UsernameNotFoundException;

class DocumentUserProvider implements UserProviderInterface
{
    protected $repository;
    protected $property;
    protected $name;

    public function __construct($em, $name, $class, $property = null)
    {
        $this->repository = $em->getRepository($class);
        $this->property = $property;
        $this->name = $name;
    }

    /**
     * {@inheritDoc}
     */
    public function isAggregate()
    {
        return false;
    }

    /**
     * {@inheritdoc}
     */
    public function loadUserByUsername($username)
    {
        if (null !== $this->property) {
            $user = $this->repository->findOneBy(array($this->property => $username));
        } else {
            if (!$this->repository instanceof UserProviderInterface) {
                throw new \InvalidArgumentException(sprintf('The Doctrine repository "%s" must implement UserProviderInterface.', get_class($this->repository)));
            }

            $user = $this->repository->loadUserByUsername($username);
        }

        if (null === $user) {
            throw new UsernameNotFoundException(sprintf('User "%s" not found.', $username));
        }

        return array($user, $this->name);
    }

    /**
     * {@inheritDoc}
     */
    public function supports($providerName)
    {
        return $this->name === $providerName;
    }
}
