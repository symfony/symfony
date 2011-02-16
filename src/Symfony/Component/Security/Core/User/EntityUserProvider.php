<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Security\Core\User;

use Doctrine\ORM\EntityManager;
use Symfony\Component\Security\Core\Exception\UnsupportedAccountException;
use Symfony\Component\Security\Core\Exception\UsernameNotFoundException;

/**
 * Wrapper around a Doctrine EntityManager.
 *
 * Provides easy to use provisioning for Doctrine entity users.
 *
 * @author Fabien Potencier <fabien.potencier@symfony-project.com>
 * @author Johannes M. Schmitt <schmittjoh@gmail.com>
 */
class EntityUserProvider implements UserProviderInterface
{
    protected $class;
    protected $repository;
    protected $property;

    public function __construct(EntityManager $em, $class, $property = null)
    {
        $this->class = $class;

        if (false !== strpos($this->class, ':')) {
            $this->class = $em->getClassMetadata($class)->name;
        }

        $this->repository = $em->getRepository($class);
        $this->property = $property;
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

        return $user;
    }

    /**
     * {@inheritDoc}
     */
    public function loadUserByAccount(AccountInterface $account)
    {
        if (!$account instanceof $this->class) {
            throw new UnsupportedAccountException(sprintf('Instances of "%s" are not supported.', get_class($account)));
        }

        return $this->loadUserByUsername($account->getUsername());
    }

    /**
     * {@inheritDoc}
     */
    public function supportsClass($class)
    {
        return $class === $this->class;
    }
}
