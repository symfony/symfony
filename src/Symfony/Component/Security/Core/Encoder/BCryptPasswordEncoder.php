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
 * @author Terje Bråten <terje@braten.be>
 */
class BCryptPasswordEncoder extends BasePasswordEncoder
{
    /**
     * @var string
     */
    private $cost;

    /**
     * Constructor.
     *
     * @param integer $cost The algorithmic cost that should be used
     *
     * @throws \InvalidArgumentException if cost is out of range
     */
    public function __construct($cost)
    {
        if (!function_exists('password_hash')) {
            throw new \RuntimeException('To use the BCrypt encoder, you need to upgrade to PHP 5.5 or install the "ircmaxell/password-compat" via Composer.');
        }

        $cost = (int) $cost;
        if ($cost < 4 || $cost > 31) {
            throw new \InvalidArgumentException('Cost must be in the range of 4-31.');
        }

        $this->cost = sprintf('%02d', $cost);
    }

    /**
     * {@inheritdoc}
     */
    public function encodePassword($raw, $salt)
    {
        return password_hash($raw, PASSWORD_BCRYPT, array('cost' => $this->cost));
    }

    /**
     * {@inheritdoc}
     */
    public function isPasswordValid($encoded, $raw, $salt)
    {
        return password_verify($raw, $encoded);
    }
}
