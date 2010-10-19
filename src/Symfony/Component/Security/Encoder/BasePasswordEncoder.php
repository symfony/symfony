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
 * BasePasswordEncoder is the base class for all password encoders.
 *
 * @author Fabien Potencier <fabien.potencier@symfony-project.com>
 */
abstract class BasePasswordEncoder implements PasswordEncoderInterface
{
    /**
     * Demerges a merge password and salt string.
     *
     * @param string $mergedPasswordSalt The merged password and salt string
     *
     * @return array An array where the first element is the password and the second the salt
     */
    protected function demergePasswordAndSalt($mergedPasswordSalt)
    {
        if (empty($mergedPasswordSalt)) {
            return array('', '');
        }

        $password = $mergedPasswordSalt;
        $salt = '';
        $saltBegins = strrpos($mergedPasswordSalt, '{');

        if (false !== $saltBegins && $saltBegins + 1 < strlen($mergedPasswordSalt)) {
            $salt = substr($mergedPasswordSalt, $saltBegins + 1, strlen($mergedPasswordSalt) - 1);
            $password = substr($mergedPasswordSalt, 0, $saltBegins);
        }

        return array($password, $salt);
    }

    /**
     * Merges a password and a salt.
     *
     * @param password the password to be used (can be <code>null</code>)
     * @param salt the salt to be used (can be <code>null</code>)
     *
     * @return a merged password and salt <code>String</code>
     */
    protected function mergePasswordAndSalt($password, $salt) {
        if (empty($salt)) {
            return $password;
        }

        if (false !== strrpos($salt, '{') || false !== strrpos($salt, '}')) {
            throw new \InvalidArgumentException('Cannot use { or } in salt.');
        }

        return $password.'{'.$salt.'}';
    }
}
