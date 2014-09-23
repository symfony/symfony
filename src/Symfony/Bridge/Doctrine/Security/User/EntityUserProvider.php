<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bridge\Doctrine\Security\User;

use Doctrine\Common\Persistence\ManagerRegistry;
use Symfony\Component\Security\Core\Exception\UnsupportedUserException;
use Symfony\Component\Security\Core\Exception\UsernameNotFoundException;
use Symfony\Component\Security\Core\User\UserProviderInterface;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * Wrapper around a Doctrine ObjectManager.
 *
 * Provides easy to use provisioning for Doctrine entity users.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 * @author Johannes M. Schmitt <schmittjoh@gmail.com>
 */
class EntityUserProvider implements UserProviderInterface
{
    private $class;
    private $repository;
    private $property;
    private $metadata;

    public function __construct(ManagerRegistry $registry, $class, $property = null, $managerName = null)
    {
        $em = $registry->getManager($managerName);
        $this->class = $class;
        $this->metadata = $em->getClassMetadata($class);

        if (false !== strpos($this->class, ':')) {
            $this->class = $this->metadata->getName();
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
     * {@inheritdoc}
     */
    public function refreshUser(UserInterface $user)
    {
        if (!$user instanceof $this->class) {
            throw new UnsupportedUserException(sprintf('Instances of "%s" are not supported.', get_class($user)));
        }

        if ($this->repository instanceof UserProviderInterface) {
            $refreshedUser = $this->repository->refreshUser($user);
        } else {
            // The user must be reloaded via the primary key as all other data
            // might have changed without proper persistence in the database.
            // That's the case when the user has been changed by a form with
            // validation errors.
            if (!$id = $this->metadata->getIdentifierValues($user)) {
                throw new \InvalidArgumentException("You cannot refresh a user ".
                    "from the EntityUserProvider that does not contain an identifier. ".
                    "The user object has to be serialized with its own identifier ".
                    "mapped by Doctrine."
                );
            }

            $refreshedUser = $this->repository->find($id);
            if (null === $refreshedUser) {
                throw new UsernameNotFoundException(sprintf('User with id %s not found', json_encode($id)));
            }
        }

        return $refreshedUser;
    }

    /**
     * {@inheritdoc}
     */
    public function supportsClass($class)
    {
        return $class === $this->class || is_subclass_of($class, $this->class);
    }
}
