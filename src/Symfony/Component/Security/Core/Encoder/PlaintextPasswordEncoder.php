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

/**
 * PlaintextPasswordEncoder does not do any encoding.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 *
 * @since v2.0.0
 */
class PlaintextPasswordEncoder extends BasePasswordEncoder
{
    private $ignorePasswordCase;

    /**
     * Constructor.
     *
     * @param Boolean $ignorePasswordCase Compare password case-insensitive
     *
     * @since v2.0.0
     */
    public function __construct($ignorePasswordCase = false)
    {
        $this->ignorePasswordCase = $ignorePasswordCase;
    }

    /**
     * {@inheritdoc}
     *
     * @since v2.0.0
     */
    public function encodePassword($raw, $salt)
    {
        return $this->mergePasswordAndSalt($raw, $salt);
    }

    /**
     * {@inheritdoc}
     *
     * @since v2.0.0
     */
    public function isPasswordValid($encoded, $raw, $salt)
    {
        $pass2 = $this->mergePasswordAndSalt($raw, $salt);

        if (!$this->ignorePasswordCase) {
            return $this->comparePasswords($encoded, $pass2);
        }

        return $this->comparePasswords(strtolower($encoded), strtolower($pass2));
    }
}
