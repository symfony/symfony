<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Security\Http\LoginLink;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Routing\RequestContext;
use Symfony\Component\Security\Core\Exception\UserNotFoundException;
use Symfony\Component\Security\Core\Signature\Exception\ExpiredSignatureException;
use Symfony\Component\Security\Core\Signature\Exception\InvalidSignatureException;
use Symfony\Component\Security\Core\Signature\SignatureHasher;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Core\User\UserProviderInterface;
use Symfony\Component\Security\Http\LoginLink\Exception\ExpiredLoginLinkException;
use Symfony\Component\Security\Http\LoginLink\Exception\InvalidLoginLinkException;
use Symfony\Component\Security\Http\ParameterBagUtils;

/**
 * @author Ryan Weaver <ryan@symfonycasts.com>
 */
final class LoginLinkHandler implements LoginLinkHandlerInterface
{
    private const RESERVED_PARAMETERS = ['user', 'expires', 'hash', 'hash_parameters'];

    private array $options;

    public function __construct(
        private UrlGeneratorInterface $urlGenerator,
        private UserProviderInterface $userProvider,
        private SignatureHasher $signatureHasher,
        array $options,
    ) {
        $this->options = array_merge([
            'route_name' => null,
            'lifetime' => 600,
        ], $options);
    }

    /**
     * @param array<string, \Stringable|scalar> $parameters Extra query parameters to add to the link and cover with its signature
     */
    public function createLoginLink(UserInterface $user, ?Request $request = null, ?int $lifetime = null, array $parameters = []): LoginLinkDetails
    {
        foreach ($parameters as $name => $value) {
            if (!\is_string($name) || !preg_match('/^[A-Za-z0-9_.-]++$/', $name)) {
                throw new \InvalidArgumentException(\sprintf('Login link parameter names must match "[A-Za-z0-9_.-]+", "%s" given.', $name));
            }

            if (\in_array($name, self::RESERVED_PARAMETERS, true)) {
                throw new \InvalidArgumentException(\sprintf('Login link parameter name "%s" is reserved.', $name));
            }
        }

        $expires = time() + ($lifetime ?: $this->options['lifetime']);
        $expiresAt = new \DateTimeImmutable('@'.$expires);

        if ($parameters) {
            ksort($parameters);
            $parameters['hash_parameters'] = implode(',', array_keys($parameters));
        }

        $parameters = [
            ...$parameters,
            'user' => $user->getUserIdentifier(),
            'expires' => $expires,
            'hash' => $this->signatureHasher->computeSignatureHash($user, $expires, $parameters),
        ];

        if ($request) {
            $currentRequestContext = $this->urlGenerator->getContext();
            $this->urlGenerator->setContext(
                (new RequestContext())
                    ->fromRequest($request)
                    ->setParameter('_locale', $request->getLocale())
            );
        }

        try {
            $url = $this->urlGenerator->generate(
                $this->options['route_name'],
                $parameters,
                UrlGeneratorInterface::ABSOLUTE_URL
            );
        } finally {
            if ($request) {
                $this->urlGenerator->setContext($currentRequestContext);
            }
        }

        return new LoginLinkDetails($url, $expiresAt);
    }

    public function consumeLoginLink(Request $request): UserInterface
    {
        $userIdentifier = ParameterBagUtils::getRequestParameterValue($request, 'user');
        if (null === $userIdentifier || '' === $userIdentifier) {
            throw new InvalidLoginLinkException('Missing "user" parameter.');
        }
        if (!\is_string($userIdentifier)) {
            throw new InvalidLoginLinkException('Invalid "user" parameter.');
        }

        if (!$hash = ParameterBagUtils::getRequestParameterValue($request, 'hash')) {
            throw new InvalidLoginLinkException('Missing "hash" parameter.');
        }
        if (!\is_string($hash)) {
            throw new InvalidLoginLinkException('Invalid "hash" parameter.');
        }

        if (!$expires = ParameterBagUtils::getRequestParameterValue($request, 'expires')) {
            throw new InvalidLoginLinkException('Missing "expires" parameter.');
        }
        if (!\is_string($expires) || !preg_match('/^\d+$/', $expires)) {
            throw new InvalidLoginLinkException('Invalid "expires" parameter.');
        }

        $parameters = [];
        if (null !== $names = ParameterBagUtils::getRequestParameterValue($request, 'hash_parameters')) {
            if (!\is_string($names)) {
                throw new InvalidLoginLinkException('Invalid "hash_parameters" parameter.');
            }

            foreach (explode(',', $names) as $name) {
                if (!preg_match('/^[A-Za-z0-9_.-]++$/', $name)) {
                    throw new InvalidLoginLinkException(\sprintf('Invalid "%s" parameter.', $name));
                }

                if (null === $value = ParameterBagUtils::getRequestParameterValue($request, $name)) {
                    throw new InvalidLoginLinkException(\sprintf('Missing "%s" parameter.', $name));
                }
                if (!\is_string($value)) {
                    throw new InvalidLoginLinkException(\sprintf('Invalid "%s" parameter.', $name));
                }

                $parameters[$name] = $value;
            }

            $parameters['hash_parameters'] = $names;
        }

        try {
            $this->signatureHasher->acceptSignatureHash($userIdentifier, $expires, $hash, $parameters);

            $user = $this->userProvider->loadUserByIdentifier($userIdentifier);

            $this->signatureHasher->verifySignatureHash($user, $expires, $hash, $parameters);
        } catch (UserNotFoundException $e) {
            throw new InvalidLoginLinkException('User not found.', 0, $e);
        } catch (ExpiredSignatureException $e) {
            throw new ExpiredLoginLinkException(ucfirst(str_ireplace('signature', 'login link', $e->getMessage())), 0, $e);
        } catch (InvalidSignatureException $e) {
            throw new InvalidLoginLinkException(ucfirst(str_ireplace('signature', 'login link', $e->getMessage())), 0, $e);
        }

        unset($parameters['hash_parameters']);
        $request->attributes->set('_login_link_parameters', $parameters);

        return $user;
    }
}
