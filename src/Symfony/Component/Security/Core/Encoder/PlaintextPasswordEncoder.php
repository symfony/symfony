<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Security\Core\Encoder;

use Symfony\Component\Security\Core\Exception\BadCredentialsException;

@trigger_error(sprintf('The "%s" class is deprecated since Symfony 5.1, use "%s" instead.', PlaintextPasswordEncoder::class, UserPasswordEncoder::class), E_USER_DEPRECATED);

/**
 * PlaintextPasswordEncoder does not do any encoding.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 *
 * @deprecated since Symfony 5.1, use another included encoder instead. I.e. Symfony\Component\Security\Core\Encoder\UserPasswordEncoder.
 */
class PlaintextPasswordEncoder extends BasePasswordEncoder
{
    private $ignorePasswordCase;

    /**
     * @param bool $ignorePasswordCase Compare password case-insensitive
     */
    public function __construct(bool $ignorePasswordCase = false)
    {
        $this->ignorePasswordCase = $ignorePasswordCase;
    }

    /**
     * {@inheritdoc}
     */
    public function encodePassword(string $raw, ?string $salt)
    {
        if ($this->isPasswordTooLong($raw)) {
            throw new BadCredentialsException('Invalid password.');
        }

        return $this->mergePasswordAndSalt($raw, $salt);
    }

    /**
     * {@inheritdoc}
     */
    public function isPasswordValid(string $encoded, string $raw, ?string $salt)
    {
        if ($this->isPasswordTooLong($raw)) {
            return false;
        }

        $pass2 = $this->mergePasswordAndSalt($raw, $salt);

        if (!$this->ignorePasswordCase) {
            return $this->comparePasswords($encoded, $pass2);
        }

        return $this->comparePasswords(strtolower($encoded), strtolower($pass2));
    }
}
