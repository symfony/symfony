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

/**
 * @author Ryan Weaver <ryan@symfonycasts.com>
 */
final class LoginLinkHandler implements LoginLinkHandlerInterface
{
    private UrlGeneratorInterface $urlGenerator;
    private UserProviderInterface $userProvider;
    private array $options;
    private SignatureHasher $signatureHasher;

    public function __construct(UrlGeneratorInterface $urlGenerator, UserProviderInterface $userProvider, SignatureHasher $signatureHasher, array $options)
    {
        $this->urlGenerator = $urlGenerator;
        $this->userProvider = $userProvider;
        $this->signatureHasher = $signatureHasher;
        $this->options = array_merge([
            'route_name' => null,
            'lifetime' => 600,
        ], $options);
    }

    /**
     * @param array<string, \Stringable|scalar> $parameters A list of additional query string parameters that should be part of the login link
     */
    public function createLoginLink(UserInterface $user, Request $request = null, int $lifetime = null, array $parameters = []): LoginLinkDetails
    {
        $expires = time() + ($lifetime ?: $this->options['lifetime']);
        $expiresAt = new \DateTimeImmutable('@'.$expires);

        ksort($parameters);

        unset($parameters['_hash_parameters']);
        if (!empty($parameters)) {
            $parameters['_hash_parameters'] = implode(',', array_keys($parameters));
        }

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
                [
                    ...$parameters,
                    '_user' => $user->getUserIdentifier(),
                    '_expires' => $expires,
                    '_hash' => $this->signatureHasher->computeSignatureHash($user, $expires, $parameters),
                ],
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
        /** @var array{_user: string, _hash: string, _expires: string|int} $requiredParameters */
        $requiredParameters = ['_user' => 'user', '_hash' => 'hash', '_expires' => 'expires'];

        foreach ($requiredParameters as $parameterName => $deprecatedParameterName) {
            if ($fallback = $request->query->getString($deprecatedParameterName)) {
                trigger_deprecation('symfony/security', '7.1', 'Login link parameters "user", "hash" and "expires" were renamed to include an underscore prefix: "_user", "_hash" and "_expires". Update your login link to reflect this.');
            }

            if (!$requiredParameters[$parameterName] = $request->query->getString($parameterName, $fallback)) {
                throw new InvalidLoginLinkException(sprintf('Missing "%s" parameter.', $parameterName));
            }
        }

        $requiredParameters['_expires'] = (int) $requiredParameters['_expires'];

        /** @var array<string, string> $hashParameters */
        $hashParameters = [];
        $hashParametersList = $request->query->get('_hash_parameters');
        if (!empty($hashParametersList)) {
            $hashParameters = [
                '_hash_parameters' => $hashParametersList
            ];

            foreach(explode(',', $hashParametersList) as $hashParameterName) {
                if (!$request->query->has($hashParameterName)) {
                    throw new InvalidLoginLinkException(sprintf('Missing "%s" parameter.', $hashParameterName));
                }

                $hashParameters[$hashParameterName] = $request->query->get($hashParameterName);
            }
        }

        try {
            $this->signatureHasher->acceptSignatureHash($requiredParameters['_user'], $requiredParameters['_expires'], $requiredParameters['_hash'], $hashParameters);

            $user = $this->userProvider->loadUserByIdentifier($requiredParameters['_user']);

            $this->signatureHasher->verifySignatureHash($user, $requiredParameters['_expires'], $requiredParameters['_hash'], $hashParameters);
        } catch (UserNotFoundException $e) {
            throw new InvalidLoginLinkException('User not found.', 0, $e);
        } catch (ExpiredSignatureException $e) {
            throw new ExpiredLoginLinkException(ucfirst(str_ireplace('signature', 'login link', $e->getMessage())), 0, $e);
        } catch (InvalidSignatureException $e) {
            throw new InvalidLoginLinkException(ucfirst(str_ireplace('signature', 'login link', $e->getMessage())), 0, $e);
        }

        return $user;
    }
}
