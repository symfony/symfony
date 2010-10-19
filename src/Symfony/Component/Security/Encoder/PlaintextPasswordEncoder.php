<?php

namespace Symfony\Component\Security\Encoder;

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

/**
 * PlaintextPasswordEncoder does not do any encoding.
 *
 * @author Fabien Potencier <fabien.potencier@symfony-project.com>
 */
class PlaintextPasswordEncoder extends BasePasswordEncoder
{
    protected $ignorePasswordCase;

    public function __construct($ignorePasswordCase = false)
    {
        $this->ignorePasswordCase = $ignorePasswordCase;
    }

    /**
     * {@inheritdoc}
     */
    public function encodePassword($raw, $salt)
    {
        return $this->mergePasswordAndSalt($raw, $salt);
    }

    /**
     * {@inheritdoc}
     */
    public function isPasswordValid($encoded, $raw, $salt)
    {
        $pass2 = $this->mergePasswordAndSalt($raw, $salt);

        if (!$this->ignorePasswordCase) {
            return $encoded === $pass2;
        } else {
            return strtolower($encoded) === strtolower($pass2);
        }
    }
}
