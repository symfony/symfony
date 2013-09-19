<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bridge\Propel1\Security\User;

use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Core\User\UserProviderInterface;
use Symfony\Component\Security\Core\Exception\UsernameNotFoundException;
use Symfony\Component\Security\Core\Exception\UnsupportedUserException;

/**
 * Provides easy to use provisioning for Propel model users.
 *
 * @author William DURAND <william.durand1@gmail.com>
 */
class PropelUserProvider implements UserProviderInterface
{
    /**
     * A Model class name.
     *
     * @var string
     */
    protected $class;

    /**
     * A Query class name.
     *
     * @var string
     */
    protected $queryClass;

    /**
     * A property to use to retrieve the user.
     *
     * @var string
     */
    protected $property;

    /**
     * Default constructor
     *
     * @param string      $class        The User model class.
     * @param string|null $property     The property to use to retrieve a user.
     */
    public function __construct($class, $property = null)
    {
        $this->class = $class;
        $this->queryClass = $class . 'Query';
        $this->property = $property;
    }

    /**
     * {@inheritdoc}
     */
    public function loadUserByUsername($username)
    {
        $queryClass = $this->queryClass;
        $query      = $queryClass::create();

        if (null !== $this->property) {
            $filter = 'filterBy' . ucfirst($this->property);
            $query->$filter($username);
        } else {
            $query->filterByUsername($username);
        }

        if (null === $user = $query->findOne()) {
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

        $queryClass = $this->queryClass;

        return $queryClass::create()->findPk($user->getPrimaryKey());
    }

    /**
     * {@inheritdoc}
     */
    public function supportsClass($class)
    {
        return $class === $this->class;
    }
}
