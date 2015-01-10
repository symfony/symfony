<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bridge\Twig\Extension;

use Symfony\Component\Security\Acl\Voter\FieldVote;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

/**
 * SecurityExtension exposes security context features.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
class SecurityExtension extends \Twig_Extension
{
    private $securityChecker;
    private $tokenStorage;

    public function __construct(AuthorizationCheckerInterface $securityChecker, TokenStorageInterface $tokenStorage)
    {
        $this->securityChecker = $securityChecker;
        $this->tokenStorage = $tokenStorage;
    }

    /**
     * {@inheritdoc}
     */
    public function getFunctions()
    {
        return array(
            new \Twig_SimpleFunction('is_granted', array($this, 'isGranted')),
            new \Twig_SimpleFunction('user', array($this, 'getUser')),
        );
    }

    public function isGranted($role, $object = null, $field = null)
    {
        if (null !== $field) {
            $object = new FieldVote($object, $field);
        }

        return $this->securityChecker->isGranted($role, $object);
    }

    /**
     * Returns the current user.
     *
     * @return mixed
     *
     * @see TokenInterface::getUser()
     */
    public function getUser()
    {
        if (!$token = $this->tokenStorage->getToken()) {
            return;
        }

        $user = $token->getUser();
        if (is_object($user)) {
            return $user;
        }
    }

    /**
     * Returns the name of the extension.
     *
     * @return string The extension name
     */
    public function getName()
    {
        return 'security';
    }
}
