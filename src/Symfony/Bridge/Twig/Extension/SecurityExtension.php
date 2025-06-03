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
use Symfony\Component\Security\Core\Authorization\AccessDecision;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Symfony\Component\Security\Core\Authorization\UserAuthorizationCheckerInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationCredentialsNotFoundException;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Http\Impersonate\ImpersonateUrlGenerator;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

/**
 * SecurityExtension exposes security context features.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
final class SecurityExtension extends AbstractExtension
{
    public function __construct(
        private ?AuthorizationCheckerInterface $securityChecker = null,
        private ?ImpersonateUrlGenerator $impersonateUrlGenerator = null,
    ) {
    }

    public function isGranted(mixed $role, mixed $object = null, ?string $field = null, ?AccessDecision $accessDecision = null): bool
    {
        if (null === $this->securityChecker) {
            return false;
        }

        if (null !== $field) {
            if (!class_exists(FieldVote::class)) {
                throw new \LogicException('Passing a $field to the "is_granted()" function requires symfony/acl. Try running "composer require symfony/acl-bundle" if you need field-level access control.');
            }

            $object = new FieldVote($object, $field);
        }

        try {
            return $this->securityChecker->isGranted($role, $object, $accessDecision);
        } catch (AuthenticationCredentialsNotFoundException) {
            return false;
        }
    }

    public function isGrantedForUser(UserInterface $user, mixed $attribute, mixed $subject = null, ?string $field = null, ?AccessDecision $accessDecision = null): bool
    {
        if (null === $this->securityChecker) {
            return false;
        }

        if (!$this->securityChecker instanceof UserAuthorizationCheckerInterface) {
            throw new \LogicException(\sprintf('You cannot use "%s()" if the authorization checker doesn\'t implement "%s".%s', __METHOD__, UserAuthorizationCheckerInterface::class, interface_exists(UserAuthorizationCheckerInterface::class) ? ' Try upgrading the "symfony/security-core" package to v7.3 minimum.' : ''));
        }

        if (null !== $field) {
            if (!class_exists(FieldVote::class)) {
                throw new \LogicException('Passing a $field to the "is_granted_for_user()" function requires symfony/acl. Try running "composer require symfony/acl-bundle" if you need field-level access control.');
            }

            $subject = new FieldVote($subject, $field);
        }

        try {
            return $this->securityChecker->isGrantedForUser($user, $attribute, $subject, $accessDecision);
        } catch (AuthenticationCredentialsNotFoundException) {
            return false;
        }
    }

    public function getImpersonateExitUrl(?string $exitTo = null): string
    {
        if (null === $this->impersonateUrlGenerator) {
            return '';
        }

        return $this->impersonateUrlGenerator->generateExitUrl($exitTo);
    }

    public function getImpersonateExitPath(?string $exitTo = null): string
    {
        if (null === $this->impersonateUrlGenerator) {
            return '';
        }

        return $this->impersonateUrlGenerator->generateExitPath($exitTo);
    }

    public function getImpersonateUrl(string $identifier): string
    {
        if (null === $this->impersonateUrlGenerator) {
            return '';
        }

        return $this->impersonateUrlGenerator->generateImpersonationUrl($identifier);
    }

    public function getImpersonatePath(string $identifier): string
    {
        if (null === $this->impersonateUrlGenerator) {
            return '';
        }

        return $this->impersonateUrlGenerator->generateImpersonationPath($identifier);
    }

    public function getFunctions(): array
    {
        $functions = [
            new TwigFunction('is_granted', $this->isGranted(...)),
            new TwigFunction('impersonation_exit_url', $this->getImpersonateExitUrl(...)),
            new TwigFunction('impersonation_exit_path', $this->getImpersonateExitPath(...)),
            new TwigFunction('impersonation_url', $this->getImpersonateUrl(...)),
            new TwigFunction('impersonation_path', $this->getImpersonatePath(...)),
        ];

        if ($this->securityChecker instanceof UserAuthorizationCheckerInterface) {
            $functions[] = new TwigFunction('is_granted_for_user', $this->isGrantedForUser(...));
        }

        return $functions;
    }
}
