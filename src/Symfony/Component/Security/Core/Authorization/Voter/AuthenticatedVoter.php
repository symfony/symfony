<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Security\Core\Authorization\Voter;

use Symfony\Component\Security\Core\Authentication\AuthenticationTrustResolverInterface;
use Symfony\Component\Security\Core\Authentication\Token\OfflineTokenInterface;
use Symfony\Component\Security\Core\Authentication\Token\SwitchUserToken;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\InvalidArgumentException;

/**
 * AuthenticatedVoter votes if an attribute like IS_AUTHENTICATED_VERY_RECENTLY, IS_AUTHENTICATED_RECENTLY,
 * IS_AUTHENTICATED_FULLY, IS_AUTHENTICATED_REMEMBERED, IS_AUTHENTICATED is present.
 *
 * This list is most restrictive to least restrictive checking.
 *
 * It also votes on "IS_AUTHENTICATED_IN_CONTEXT:" followed by one or more authentication context
 * classes, granted to a fully-fledged token that authenticated in exactly one of them.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 * @author Johannes M. Schmitt <schmittjoh@gmail.com>
 */
class AuthenticatedVoter implements CacheableVoterInterface
{
    public const IS_AUTHENTICATED_VERY_RECENTLY = 'IS_AUTHENTICATED_VERY_RECENTLY';
    public const IS_AUTHENTICATED_RECENTLY = 'IS_AUTHENTICATED_RECENTLY';
    public const IS_AUTHENTICATED_FULLY = 'IS_AUTHENTICATED_FULLY';
    public const IS_AUTHENTICATED_REMEMBERED = 'IS_AUTHENTICATED_REMEMBERED';
    public const IS_AUTHENTICATED = 'IS_AUTHENTICATED';
    public const IS_IMPERSONATOR = 'IS_IMPERSONATOR';
    public const IS_REMEMBERED = 'IS_REMEMBERED';
    public const PUBLIC_ACCESS = 'PUBLIC_ACCESS';

    /**
     * Prefix of the attribute requiring an authentication context class, e.g. "IS_AUTHENTICATED_IN_CONTEXT:phr".
     *
     * A class is a name the identity provider and the application agreed on, the "acr" claim of
     * OpenID Connect Core 1.0, Section 2: "phr" and "phrh" for a phishing-resistant authentication
     * (OpenID Connect EAP ACR Values 1.0), a level such as "2", or any URI. Several classes are
     * separated by a space, the delimiter of "acr_values" itself, and any one of them grants,
     * e.g. "IS_AUTHENTICATED_IN_CONTEXT:phr phrh": the default grants a token whose class is
     * exactly one of those required, as the classes carry no ordering the application could rely
     * on, and a provider naming the strongest class it can assert would otherwise be denied by a
     * route asking for a weaker one. Which classes answer which is the trust resolver's to decide,
     * so an application facing several providers maps their vocabularies onto its own in that one
     * place rather than naming every provider's class on every route. A re-authentication entry
     * point asks the provider for the classes that are missing, in the order they are written.
     *
     * @see AuthenticationTrustResolverInterface::isAuthenticatedInContext()
     */
    public const IS_AUTHENTICATED_IN_CONTEXT = 'IS_AUTHENTICATED_IN_CONTEXT:';

    /**
     * Most restrictive first: only the reason of the strictest attribute that failed is reported.
     */
    private const DENIAL_REASONS = [
        self::IS_AUTHENTICATED_VERY_RECENTLY => 'The user did not authenticate very recently.',
        self::IS_AUTHENTICATED_RECENTLY => 'The user is not authenticated recently enough.',
        self::IS_AUTHENTICATED_FULLY => 'The user is not fully authenticated.',
        self::IS_AUTHENTICATED_REMEMBERED => 'The user is neither fully authenticated nor remembered.',
        self::IS_AUTHENTICATED => 'The user is not authenticated.',
        self::IS_IMPERSONATOR => 'The user is not impersonating another user.',
        self::IS_REMEMBERED => 'The user is not remembered.',
    ];

    public function __construct(
        private AuthenticationTrustResolverInterface $authenticationTrustResolver,
    ) {
    }

    public function vote(TokenInterface $token, mixed $subject, array $attributes, ?Vote $vote = null): int
    {
        if ($attributes === [self::PUBLIC_ACCESS]) {
            $vote?->addReason('Access is public.');

            return VoterInterface::ACCESS_GRANTED;
        }

        $result = VoterInterface::ACCESS_ABSTAIN;
        foreach ($attributes as $attribute) {
            $contextClasses = self::getRequiredContextClasses($attribute);

            if (null === $attribute || (null === $contextClasses
                    && self::IS_AUTHENTICATED_VERY_RECENTLY !== $attribute
                    && self::IS_AUTHENTICATED_RECENTLY !== $attribute
                    && self::IS_AUTHENTICATED_FULLY !== $attribute
                    && self::IS_AUTHENTICATED_REMEMBERED !== $attribute
                    && self::IS_AUTHENTICATED !== $attribute
                    && self::IS_IMPERSONATOR !== $attribute
                    && self::IS_REMEMBERED !== $attribute)) {
                continue;
            }

            if ($token instanceof OfflineTokenInterface) {
                throw new InvalidArgumentException('Cannot decide on authentication attributes when an offline token is used.');
            }

            $result = VoterInterface::ACCESS_DENIED;

            // being full fledged is an invariant here too: a remember-me cookie proves nothing
            // about the class the user authenticated in when the session was opened
            if (null !== $contextClasses) {
                if ($this->authenticationTrustResolver->isFullFledged($token)
                    && $this->isAuthenticatedInContext($token, $contextClasses, $attribute)
                ) {
                    $vote?->addReason(\sprintf('The user authenticated in the %s context.', self::listContextClasses($contextClasses)));

                    return VoterInterface::ACCESS_GRANTED;
                }

                continue;
            }

            // being full fledged is an invariant of the attribute, not part of the strategy:
            // a remember-me cookie is precisely not proof that the user still holds the
            // credentials, so no custom trust resolver gets to grant on one
            if ((self::IS_AUTHENTICATED_RECENTLY === $attribute || self::IS_AUTHENTICATED_VERY_RECENTLY === $attribute)
                && $this->authenticationTrustResolver->isFullFledged($token)
                && $this->isAuthenticatedRecently($token, $attribute)
            ) {
                $vote?->addReason(self::IS_AUTHENTICATED_RECENTLY === $attribute ? 'The user authenticated recently.' : 'The user authenticated very recently.');

                return VoterInterface::ACCESS_GRANTED;
            }

            if ((self::IS_AUTHENTICATED_FULLY === $attribute || self::IS_AUTHENTICATED_REMEMBERED === $attribute)
                && $this->authenticationTrustResolver->isFullFledged($token)
            ) {
                $vote?->addReason('The user is fully authenticated.');

                return VoterInterface::ACCESS_GRANTED;
            }

            if (self::IS_AUTHENTICATED_REMEMBERED === $attribute
                && $this->authenticationTrustResolver->isRememberMe($token)
            ) {
                $vote?->addReason('The user is remembered.');

                return VoterInterface::ACCESS_GRANTED;
            }

            if (self::IS_AUTHENTICATED === $attribute && $this->authenticationTrustResolver->isAuthenticated($token)) {
                $vote?->addReason('The user is authenticated.');

                return VoterInterface::ACCESS_GRANTED;
            }

            if (self::IS_REMEMBERED === $attribute && $this->authenticationTrustResolver->isRememberMe($token)) {
                $vote?->addReason('The user is remembered.');

                return VoterInterface::ACCESS_GRANTED;
            }

            if (self::IS_IMPERSONATOR === $attribute && $token instanceof SwitchUserToken) {
                $vote?->addReason('The user is impersonating another user.');

                return VoterInterface::ACCESS_GRANTED;
            }
        }

        if (VoterInterface::ACCESS_DENIED === $result) {
            foreach ($attributes as $attribute) {
                if (null !== $contextClasses = self::getRequiredContextClasses($attribute)) {
                    $vote?->addReason(\sprintf('The user did not authenticate in the %s context.', self::listContextClasses($contextClasses)));

                    return $result;
                }
            }

            foreach (self::DENIAL_REASONS as $deniedAttribute => $reason) {
                if (\in_array($deniedAttribute, $attributes, true)) {
                    $vote?->addReason($reason);

                    break;
                }
            }
        }

        return $result;
    }

    private function isAuthenticatedRecently(TokenInterface $token, string $attribute): bool
    {
        $method = self::IS_AUTHENTICATED_RECENTLY === $attribute ? 'isAuthenticatedRecently' : 'isAuthenticatedVeryRecently';

        if (!method_exists($this->authenticationTrustResolver, $method)) {
            trigger_deprecation('symfony/security-core', '8.2', 'Not implementing "%s::%s()" is deprecated, the method will be added to the interface in 9.0; "%s" is denied until then.', get_debug_type($this->authenticationTrustResolver), $method, $attribute);

            return false;
        }

        return $this->authenticationTrustResolver->$method($token);
    }

    /**
     * Returns the classes an "IS_AUTHENTICATED_IN_CONTEXT:" attribute accepts, any one of which grants,
     * or null for any other attribute.
     *
     * @return non-empty-list<string>|null
     */
    public static function getRequiredContextClasses(mixed $attribute): ?array
    {
        if (!\is_string($attribute) || !str_starts_with($attribute, self::IS_AUTHENTICATED_IN_CONTEXT)) {
            return null;
        }

        $classes = array_values(array_filter(explode(' ', substr($attribute, \strlen(self::IS_AUTHENTICATED_IN_CONTEXT))), static fn (string $class): bool => '' !== $class));

        if (!$classes) {
            throw new InvalidArgumentException(\sprintf('The "%s" attribute must be followed by the name of at least one authentication context class.', self::IS_AUTHENTICATED_IN_CONTEXT));
        }

        return $classes;
    }

    public function supportsAttribute(string $attribute): bool
    {
        if (str_starts_with($attribute, self::IS_AUTHENTICATED_IN_CONTEXT)) {
            return true;
        }

        return \in_array($attribute, [
            self::IS_AUTHENTICATED_VERY_RECENTLY,
            self::IS_AUTHENTICATED_RECENTLY,
            self::IS_AUTHENTICATED_FULLY,
            self::IS_AUTHENTICATED_REMEMBERED,
            self::IS_AUTHENTICATED,
            self::IS_IMPERSONATOR,
            self::IS_REMEMBERED,
            self::PUBLIC_ACCESS,
        ], true);
    }

    public function supportsType(string $subjectType): bool
    {
        return true;
    }

    /**
     * @param non-empty-list<string> $contextClasses
     */
    private function isAuthenticatedInContext(TokenInterface $token, array $contextClasses, string $attribute): bool
    {
        if (!method_exists($this->authenticationTrustResolver, 'isAuthenticatedInContext')) {
            trigger_deprecation('symfony/security-core', '8.2', 'Not implementing "%s::isAuthenticatedInContext()" is deprecated, the method will be added to the interface in 9.0; "%s" is denied until then.', get_debug_type($this->authenticationTrustResolver), $attribute);

            return false;
        }

        return $this->authenticationTrustResolver->isAuthenticatedInContext($token, $contextClasses);
    }

    /**
     * @param non-empty-list<string> $contextClasses
     */
    private static function listContextClasses(array $contextClasses): string
    {
        return implode(' or ', array_map(static fn (string $class): string => \sprintf('"%s"', $class), $contextClasses));
    }
}
