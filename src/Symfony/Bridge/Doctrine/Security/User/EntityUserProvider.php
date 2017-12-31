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
    private $registry;
    private $managerName;
    private $classOrAlias;
    private $class;
    private $property;

    public function __construct(ManagerRegistry $registry, $classOrAlias, $property = null, $managerName = null)
    {
        $this->registry = $registry;
        $this->managerName = $managerName;
        $this->classOrAlias = $classOrAlias;
        $this->property = $property;
    }

    /**
     * {@inheritdoc}
     */
    public function loadUserByUsername($username)
    {
        $repository = $this->getRepository();
        if (null !== $this->property) {
            $user = $repository->findOneBy(array($this->property => $username));
        } else {
            if (!$repository instanceof UserLoaderInterface) {
                if (!$repository instanceof UserProviderInterface) {
                    throw new \InvalidArgumentException(sprintf('You must either make the "%s" entity Doctrine Repository ("%s") implement "Symfony\Bridge\Doctrine\Security\User\UserLoaderInterface" or set the "property" option in the corresponding entity provider configuration.', $this->classOrAlias, get_class($repository)));
                }

                @trigger_error('Implementing Symfony\Component\Security\Core\User\UserProviderInterface in a Doctrine repository when using the entity provider is deprecated since Symfony 2.8 and will not be supported in 3.0. Make the repository implement Symfony\Bridge\Doctrine\Security\User\UserLoaderInterface instead.', E_USER_DEPRECATED);
            }

            $user = $repository->loadUserByUsername($username);
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
        $class = $this->getClass();
        if (!$user instanceof $class) {
            throw new UnsupportedUserException(sprintf('Instances of "%s" are not supported.', get_class($user)));
        }

        $repository = $this->getRepository();
        if ($repository instanceof UserProviderInterface) {
            $refreshedUser = $repository->refreshUser($user);
        } else {
            // The user must be reloaded via the primary key as all other data
            // might have changed without proper persistence in the database.
            // That's the case when the user has been changed by a form with
            // validation errors.
            if (!$id = $this->getClassMetadata()->getIdentifierValues($user)) {
                throw new \InvalidArgumentException('You cannot refresh a user '.
                    'from the EntityUserProvider that does not contain an identifier. '.
                    'The user object has to be serialized with its own identifier '.
                    'mapped by Doctrine.'
                );
            }

            $refreshedUser = $repository->find($id);
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
        return $class === $this->getClass() || is_subclass_of($class, $this->getClass());
    }

    private function getObjectManager()
    {
        return $this->registry->getManager($this->managerName);
    }

    private function getRepository()
    {
        return $this->getObjectManager()->getRepository($this->classOrAlias);
    }

    private function getClass()
    {
        if (null === $this->class) {
            $class = $this->classOrAlias;

            if (false !== strpos($class, ':')) {
                $class = $this->getClassMetadata()->getName();
            }

            $this->class = $class;
        }

        return $this->class;
    }

    private function getClassMetadata()
    {
        return $this->getObjectManager()->getClassMetadata($this->classOrAlias);
    }
}
