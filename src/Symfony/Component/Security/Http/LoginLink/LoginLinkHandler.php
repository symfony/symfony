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
use Symfony\Component\PropertyAccess\PropertyAccessorInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Exception\UsernameNotFoundException;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Core\User\UserProviderInterface;
use Symfony\Component\Security\Http\LoginLink\Exception\ExpiredLoginLinkException;
use Symfony\Component\Security\Http\LoginLink\Exception\InvalidLoginLinkException;

/**
 * @author Ryan Weaver <ryan@symfonycasts.com>
 * @experimental in 5.2
 */
final class LoginLinkHandler implements LoginLinkHandlerInterface
{
    private $urlGenerator;
    private $userProvider;
    private $propertyAccessor;
    private $signatureProperties;
    private $secret;
    private $options;
    private $expiredStorage;

    public function __construct(UrlGeneratorInterface $urlGenerator, UserProviderInterface $userProvider, PropertyAccessorInterface $propertyAccessor, array $signatureProperties, string $secret, array $options, ?ExpiredLoginLinkStorage $expiredStorage)
    {
        $this->urlGenerator = $urlGenerator;
        $this->userProvider = $userProvider;
        $this->propertyAccessor = $propertyAccessor;
        $this->signatureProperties = $signatureProperties;
        $this->secret = $secret;
        $this->options = array_merge([
            'route_name' => null,
            'lifetime' => 600,
            'max_uses' => null,
        ], $options);
        $this->expiredStorage = $expiredStorage;
    }

    public function createLoginLink(UserInterface $user): LoginLinkDetails
    {
        $expiresAt = new \DateTimeImmutable(sprintf('+%d seconds', $this->options['lifetime']));

        $expires = $expiresAt->format('U');
        $parameters = [
            'user' => $user->getUsername(),
            'expires' => $expires,
            'hash' => $this->computeSignatureHash($user, $expires),
        ];

        $url = $this->urlGenerator->generate(
            $this->options['route_name'],
            $parameters,
            UrlGeneratorInterface::ABSOLUTE_URL
        );

        return new LoginLinkDetails($url, $expiresAt);
    }

    public function consumeLoginLink(Request $request): UserInterface
    {
        $username = $request->get('user');

        try {
            $user = $this->userProvider->loadUserByUsername($username);
        } catch (UsernameNotFoundException $exception) {
            throw new InvalidLoginLinkException('User not found.', 0, $exception);
        }

        $hash = $request->get('hash');
        $expires = $request->get('expires');
        if (false === hash_equals($hash, $this->computeSignatureHash($user, $expires))) {
            throw new InvalidLoginLinkException('Invalid or expired signature.');
        }

        if ($expires < time()) {
            throw new ExpiredLoginLinkException('Login link has expired.');
        }

        if ($this->expiredStorage && $this->options['max_uses']) {
            $hash = $request->get('hash');
            if ($this->expiredStorage->countUsages($hash) >= $this->options['max_uses']) {
                throw new ExpiredLoginLinkException(sprintf('Login link can only be used "%d" times.', $this->options['max_uses']));
            }

            $this->expiredStorage->incrementUsages($hash);
        }

        return $user;
    }

    private function computeSignatureHash(UserInterface $user, int $expires): string
    {
        $signatureFields = [base64_encode($user->getUsername()), $expires];

        foreach ($this->signatureProperties as $property) {
            $value = $this->propertyAccessor->getValue($user, $property) ?? '';
            if ($value instanceof \DateTimeInterface) {
                $value = $value->format('c');
            }

            if (!is_scalar($value) && !(\is_object($value) && method_exists($value, '__toString'))) {
                throw new \InvalidArgumentException(sprintf('The property path "%s" on the user object "%s" must return a value that can be cast to a string, but "%s" was returned.', $property, \get_class($user), get_debug_type($value)));
            }
            $signatureFields[] = base64_encode($value);
        }

        return base64_encode(hash_hmac('sha256', implode(':', $signatureFields), $this->secret));
    }
}
