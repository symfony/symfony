<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Security\Csrf;

use Symfony\Component\Security\Core\Util\SecureRandomInterface;
use Symfony\Component\Security\Core\Util\SecureRandom;
use Symfony\Component\Security\Core\Util\StringUtils;
use Symfony\Component\Security\Csrf\TokenStorage\NativeSessionTokenStorage;
use Symfony\Component\Security\Csrf\TokenStorage\TokenStorageInterface;

/**
 * Generates and validates CSRF tokens.
 *
 * @since  2.4
 * @author Bernhard Schussek <bernhard.schussek@symfony.com>
 */
class CsrfTokenGenerator implements CsrfTokenGeneratorInterface
{
    /**
     * The entropy of the token in bits.
     * @var integer
     */
    const TOKEN_ENTROPY = 256;

    /**
     * @var TokenStorageInterface
     */
    private $storage;

    /**
     * The generator for random values.
     * @var SecureRandomInterface
     */
    private $random;

    /**
     * Creates a new CSRF provider using PHP's native session storage.
     *
     * @param TokenStorageInterface $storage The storage for storing generated
     *                                       CSRF tokens
     * @param SecureRandomInterface $random  The used random value generator
     * @param integer               $entropy The amount of entropy collected for
     *                                       newly generated tokens (in bits)
     *
     */
    public function __construct(TokenStorageInterface $storage = null, SecureRandomInterface $random = null, $entropy = self::TOKEN_ENTROPY)
    {
        if (null === $storage) {
            $storage = new NativeSessionTokenStorage();
        }

        if (null === $random) {
            $random = new SecureRandom();
        }

        $this->storage = $storage;
        $this->random = $random;
        $this->entropy = $entropy;
    }

    /**
     * {@inheritDoc}
     */
    public function generateCsrfToken($tokenId)
    {
        $currentToken = $this->storage->getToken($tokenId, false);

        // Token exists and is still valid
        if (false !== $currentToken) {
            return $currentToken;
        }

        // Token needs to be (re)generated
        // Generate an URI safe base64 encoded string that does not contain "+",
        // "/" or "=" which need to be URL encoded and make URLs unnecessarily
        // longer.
        $bytes = $this->random->nextBytes($this->entropy / 8);
        $token = rtrim(strtr(base64_encode($bytes), '+/', '-_'), '=');

        $this->storage->setToken($tokenId, $token);

        return $token;
    }

    /**
     * {@inheritDoc}
     */
    public function isCsrfTokenValid($tokenId, $token)
    {
        if (!$this->storage->hasToken($tokenId)) {
            return false;
        }

        return StringUtils::equals((string) $this->storage->getToken($tokenId), $token);
    }
}
