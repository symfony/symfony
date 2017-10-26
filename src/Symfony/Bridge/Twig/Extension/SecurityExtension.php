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
        if (null !== $securityChecker) {
            @trigger_error(sprintf('The $securityChecker parameter is deprecated since 3.4 and will be removed in 4.0. Use %s instead.', SecurityRuntime::class), E_USER_DEPRECATED);
        }

        $this->securityChecker = $securityChecker;
    }

    public function isGranted($role, $object = null, $field = null)
    {
        @trigger_error(sprintf('The %s() method is deprecated since version 3.4 and will be removed in 4.0. Use the %s::%s() method instead.', __METHOD__, SecurityRuntime::class, __FUNCTION__), E_USER_DEPRECATED);

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
            new TwigFunction('is_granted', array($this->securityChecker ? $this : SecurityRuntime::class, 'isGranted')),
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
