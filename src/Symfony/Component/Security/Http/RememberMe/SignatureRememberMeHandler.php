<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Security\Http\RememberMe;

use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Core\Signature\Exception\ExpiredSignatureException;
use Symfony\Component\Security\Core\Signature\Exception\InvalidSignatureException;
use Symfony\Component\Security\Core\Signature\SignatureHasher;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Core\User\UserProviderInterface;

/**
 * Implements safe remember-me cookies using the {@see SignatureHasher}.
 *
 * This handler doesn't require a database for the remember-me tokens.
 * However, it cannot invalidate a specific user session, all sessions for
 * that user will be invalidated instead. Use {@see PersistentRememberMeHandler}
 * if you need this.
 *
 * @author Wouter de Jong <wouter@wouterj.nl>
 */
final class SignatureRememberMeHandler extends AbstractRememberMeHandler
{
    private SignatureHasher $signatureHasher;

    public function __construct(SignatureHasher $signatureHasher, UserProviderInterface $userProvider, RequestStack $requestStack, array $options, ?LoggerInterface $logger = null)
    {
        parent::__construct($userProvider, $requestStack, $options, $logger);

        $this->signatureHasher = $signatureHasher;
    }

    public function createRememberMeCookie(UserInterface $user): void
    {
        $expires = time() + $this->options['lifetime'];
        $value = $this->signatureHasher->computeSignatureHash($user, $expires);

        $details = new RememberMeDetails($user::class, $user->getUserIdentifier(), $expires, $value);
        $this->createCookie($details);
    }

    public function consumeRememberMeCookie(RememberMeDetails $rememberMeDetails): UserInterface
    {
        try {
            $this->signatureHasher->acceptSignatureHash($rememberMeDetails->getUserIdentifier(), $rememberMeDetails->getExpires(), $rememberMeDetails->getValue());
        } catch (InvalidSignatureException $e) {
            throw new AuthenticationException('The cookie\'s hash is invalid.', 0, $e);
        } catch (ExpiredSignatureException $e) {
            throw new AuthenticationException('The cookie has expired.', 0, $e);
        }

        return parent::consumeRememberMeCookie($rememberMeDetails);
    }

    public function processRememberMe(RememberMeDetails $rememberMeDetails, UserInterface $user): void
    {
        try {
            $this->signatureHasher->verifySignatureHash($user, $rememberMeDetails->getExpires(), $rememberMeDetails->getValue());
        } catch (InvalidSignatureException $e) {
            throw new AuthenticationException('The cookie\'s hash is invalid.', 0, $e);
        } catch (ExpiredSignatureException $e) {
            throw new AuthenticationException('The cookie has expired.', 0, $e);
        }

        $this->createRememberMeCookie($user);
    }
}
