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

/**
 * @author Elnur Abdurrakhimov <elnur@elnur.pro>
 */
class BCryptPasswordEncoder extends BasePasswordEncoder
{
    /**
     * @var string
     */
    private $cost;

    /**
     * @param int $cost
     * @throws \InvalidArgumentException
     */
    public function __construct($cost)
    {
        $cost = (int)$cost;

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

        return crypt($raw, '$2a$' . $this->cost . '$' . $salt . '$');
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
        return substr(base_convert(sha1(uniqid(mt_rand(), true)), 16, 36), 0, 22);
    }
}
