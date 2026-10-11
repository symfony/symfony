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
use Symfony\Component\Security\Core\Authorization\GuestAuthorizationCheckerInterface;
use Symfony\Component\Security\Core\Authorization\UserAuthorizationCheckerInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationCredentialsNotFoundException;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Http\Impersonate\ImpersonateUrlGenerator;
use Twig\Attribute\AsTwigFunction;
use Twig\Environment;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

/**
 * SecurityExtension exposes security context features.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
final class SecurityExtension extends AbstractExtension
{
    /**
     * @param array<string, mixed> $csrfFieldAttr The HTML attributes of the hidden field that contains the CSRF token
     */
    public function __construct(
        private ?AuthorizationCheckerInterface $securityChecker = null,
        private ?ImpersonateUrlGenerator $impersonateUrlGenerator = null,
        private array $csrfFieldAttr = [],
    ) {
    }

    #[AsTwigFunction('is_granted')]
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

    #[AsTwigFunction('access_decision')]
    public function getAccessDecision(mixed $role, mixed $object = null, ?string $field = null): AccessDecision
    {
        if (!class_exists(AccessDecision::class)) {
            throw new \LogicException(\sprintf('Using the "access_decision()" function requires symfony/security-core >= 7.3. Try running "%s".', $this->securityChecker ? 'composer update symfony/security-core' : 'composer require symfony/security-core'));
        }

        $accessDecision = new AccessDecision();
        $this->isGranted($role, $object, $field, $accessDecision);

        return $accessDecision;
    }

    #[AsTwigFunction('is_granted_for_user')]
    public function isGrantedForUser(?UserInterface $user, mixed $attribute, mixed $subject = null, ?string $field = null, ?AccessDecision $accessDecision = null): bool
    {
        if (null === $this->securityChecker) {
            return false;
        }

        if (!$this->securityChecker instanceof UserAuthorizationCheckerInterface) {
            throw new \LogicException(\sprintf('You cannot use "%s()" if the authorization checker doesn\'t implement "%s".', __METHOD__, UserAuthorizationCheckerInterface::class));
        }

        if (null === $user && !$this->securityChecker instanceof GuestAuthorizationCheckerInterface) {
            throw new \LogicException(\sprintf('You cannot use "%s()" with a null user if the authorization checker doesn\'t implement "%s".%s', __METHOD__, GuestAuthorizationCheckerInterface::class, interface_exists(GuestAuthorizationCheckerInterface::class) ? '' : ' Try upgrading the "symfony/security-core" package to version 8.2 or newer.'));
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

    #[AsTwigFunction('access_decision_for_user')]
    public function getAccessDecisionForUser(?UserInterface $user, mixed $attribute, mixed $subject = null, ?string $field = null): AccessDecision
    {
        if (!class_exists(AccessDecision::class)) {
            throw new \LogicException(\sprintf('Using the "access_decision_for_user()" function requires symfony/security-core >= 7.3. Try running "%s".', $this->securityChecker ? 'composer update symfony/security-core' : 'composer require symfony/security-core'));
        }

        $accessDecision = new AccessDecision();
        $this->isGrantedForUser($user, $attribute, $subject, $field, $accessDecision);

        return $accessDecision;
    }

    #[AsTwigFunction('impersonation_exit_url')]
    public function getImpersonateExitUrl(?string $exitTo = null): string
    {
        if (null === $this->impersonateUrlGenerator) {
            return '';
        }

        return $this->impersonateUrlGenerator->generateExitUrl($exitTo);
    }

    #[AsTwigFunction('impersonation_exit_path')]
    public function getImpersonateExitPath(?string $exitTo = null): string
    {
        if (null === $this->impersonateUrlGenerator) {
            return '';
        }

        return $this->impersonateUrlGenerator->generateExitPath($exitTo);
    }

    #[AsTwigFunction('impersonation_url')]
    public function getImpersonateUrl(string $identifier, ?string $targetUri = null): string
    {
        if (null === $this->impersonateUrlGenerator) {
            return '';
        }

        return $this->impersonateUrlGenerator->generateImpersonationUrl($identifier, $targetUri);
    }

    #[AsTwigFunction('impersonation_path')]
    public function getImpersonatePath(string $identifier, ?string $targetUri = null): string
    {
        if (null === $this->impersonateUrlGenerator) {
            return '';
        }

        return $this->impersonateUrlGenerator->generateImpersonationPath($identifier, $targetUri);
    }

    /**
     * Returns the action and the hidden fields of a form triggering the impersonation.
     *
     * @return array{action: string, fields: array<string, string>, csrf_field: string|null}
     */
    #[AsTwigFunction('impersonation_form')]
    public function getImpersonateForm(string $identifier, ?string $targetUri = null): array
    {
        if (null === $this->impersonateUrlGenerator) {
            return ['action' => '', 'fields' => [], 'csrf_field' => null];
        }

        return $this->impersonateUrlGenerator->generateImpersonationForm($identifier, $targetUri);
    }

    /**
     * Returns the action and the hidden fields of a form exiting the impersonation, the action being empty when the user is not impersonating anyone.
     *
     * @return array{action: string, fields: array<string, string>, csrf_field: string|null}
     */
    #[AsTwigFunction('impersonation_exit_form')]
    public function getImpersonateExitForm(?string $targetUri = null): array
    {
        if (null === $this->impersonateUrlGenerator) {
            return ['action' => '', 'fields' => [], 'csrf_field' => null];
        }

        return $this->impersonateUrlGenerator->generateExitForm($targetUri);
    }

    /**
     * Renders a form that triggers the impersonation with a POST, or nothing when no form can be built.
     *
     * @param string|\Stringable   $label    The label of the button, escaped unless it is a Twig Markup
     * @param array<string, mixed> $attr     The HTML attributes of the button
     * @param array<string, mixed> $formAttr The HTML attributes of the form
     */
    #[AsTwigFunction('impersonation_button', isSafe: ['html'])]
    public function renderImpersonateButton(Environment $env, string|\Stringable $label, string $identifier, ?string $targetUri = null, array $attr = [], array $formAttr = []): string
    {
        return PostFormRenderer::render($env, $this->getImpersonateForm($identifier, $targetUri), $label, $attr, $formAttr, $this->csrfFieldAttr);
    }

    /**
     * Renders a form that exits the impersonation with a POST, or nothing when the user is not impersonating anyone.
     *
     * @param string|\Stringable   $label    The label of the button, escaped unless it is a Twig Markup
     * @param array<string, mixed> $attr     The HTML attributes of the button
     * @param array<string, mixed> $formAttr The HTML attributes of the form
     */
    #[AsTwigFunction('impersonation_exit_button', isSafe: ['html'])]
    public function renderImpersonateExitButton(Environment $env, string|\Stringable $label = 'Exit impersonation', ?string $targetUri = null, array $attr = [], array $formAttr = []): string
    {
        return PostFormRenderer::render($env, $this->getImpersonateExitForm($targetUri), $label, $attr, $formAttr, $this->csrfFieldAttr);
    }

    public function getFunctions(): array
    {
        $functions = [
            new TwigFunction('is_granted', $this->isGranted(...)),
            new TwigFunction('access_decision', $this->getAccessDecision(...)),
            new TwigFunction('impersonation_exit_url', $this->getImpersonateExitUrl(...)),
            new TwigFunction('impersonation_exit_path', $this->getImpersonateExitPath(...)),
            new TwigFunction('impersonation_url', $this->getImpersonateUrl(...)),
            new TwigFunction('impersonation_path', $this->getImpersonatePath(...)),
            new TwigFunction('impersonation_form', $this->getImpersonateForm(...)),
            new TwigFunction('impersonation_exit_form', $this->getImpersonateExitForm(...)),
            new TwigFunction('impersonation_button', $this->renderImpersonateButton(...), ['needs_environment' => true, 'is_safe' => ['html']]),
            new TwigFunction('impersonation_exit_button', $this->renderImpersonateExitButton(...), ['needs_environment' => true, 'is_safe' => ['html']]),
        ];

        if ($this->securityChecker instanceof UserAuthorizationCheckerInterface) {
            $functions[] = new TwigFunction('is_granted_for_user', $this->isGrantedForUser(...));
            $functions[] = new TwigFunction('access_decision_for_user', $this->getAccessDecisionForUser(...));
        }

        return $functions;
    }
}
