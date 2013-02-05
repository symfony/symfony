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

use Symfony\Component\Security\Core\Encoder\BasePasswordEncoder;
use Symfony\Component\Security\Core\Util\SecureRandomInterface;

define('BCRYPT_PREFIX', '$' . (version_compare(phpversion(), '5.3.7', '>=') ? '2y' : '2a') . '$');

/**
 * @author Elnur Abdurrakhimov <elnur@elnur.pro>
 */
class BCryptPasswordEncoder extends BasePasswordEncoder
{
    /**
     * @var SecureRandomInterface
     */
    private $secureRandom;

    /**
     * @var string
     */
    private $cost;

    /**
     * @param SecureRandomInterface $secureRandom
     * @param int $cost
     * @throws \InvalidArgumentException
     */
    public function __construct(SecureRandomInterface $secureRandom, $cost)
    {
        $this->secureRandom = $secureRandom;
        $cost = (int) $cost;

        if ($cost < 4 || $cost > 31) {
            throw new \InvalidArgumentException('Cost must be in the range of 4-31');
        }

        $this->cost = sprintf("%02d", $cost);
    }

    /**
     * {@inheritdoc}
     */
    public function encodePassword($raw, $salt = null)
    {
        $salt = $this->generateSalt();

        return crypt($raw, BCRYPT_PREFIX . $this->cost . '$' . $salt);
    }

    /**
     * {@inheritdoc}
     */
    public function isPasswordValid($encoded, $raw, $salt = null)
    {
        return $this->comparePasswords($encoded, crypt($raw, $encoded));
    }

    /**
     * @return string
     */
    private function generateSalt()
    {
        $bytes = $this->secureRandom->nextBytes(16);

        return str_replace('+', '.', base64_encode($bytes));
    }
}
