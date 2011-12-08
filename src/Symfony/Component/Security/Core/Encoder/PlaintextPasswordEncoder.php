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
 */
class PlaintextPasswordEncoder implements PasswordEncoderInterface
{
    private $ignorePasswordCase;

    public function __construct($ignorePasswordCase = false)
    {
        $this->ignorePasswordCase = $ignorePasswordCase;
    }

    /**
     * {@inheritdoc}
     */
    public function encodePassword($plain)
    {
        return $plain;
    }

    /**
     * {@inheritdoc}
     */
    public function isPasswordValid($encoded, $plain)
    {
        return 0 == ($this->ignorePasswordCase
			? strcasecmp($encoded, $plain)
			: strcmp($encoded, $plain));
    }
}
