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
use Symfony\Component\Security\Core\Exception\AuthenticationCredentialsNotFoundException;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

/**
 * SecurityExtension exposes security context features.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
class SecurityExtension extends AbstractExtension
{
    private $securityChecker;

    public function __construct(AuthorizationCheckerInterface $securityChecker = null)
    {
        $this->securityChecker = $securityChecker;
    }

    public function isGranted($role, $object = null, $field = null)
    {
        if (null === $this->securityChecker) {
            return false;
        }

        if (null !== $field) {
            $object = new FieldVote($object, $field);
        }

        try {
            return $this->securityChecker->isGranted($role, $object);
        } catch (AuthenticationCredentialsNotFoundException $e) {
            return false;
        }
    }

    /**
     * {@inheritdoc}
     */
    public function getFunctions()
    {
        return array(
            new TwigFunction('is_granted', array($this, 'isGranted')),
        );
    }

    /**
     * {@inheritdoc}
     */
    public function getName()
    {
        return 'security';
    }
}
