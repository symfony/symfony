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

/**
 * @author Elnur Abdurrakhimov <elnur@elnur.pro>
 * @author Terje Bråten <terje@braten.be>
 */
class BCryptPasswordEncoder extends BasePasswordEncoder
{
   /**
    * A secure random generator
    * @var SecureRandomInterface
    */
    private $secure_random;

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
      //TODO: add SecureRandomInterface $secure_random as an argument
      // to the consructor.  Service id: security.secure_random
      //$this->secure_random = $secure_random;

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
        if ($this->secure_random) {
          $random = $this->secure_random->nextBytes(16);
        } else {
          $random = $this->get_random_bytes(16);
        }
        $salt = $this->encodeSalt($random)

        return crypt($raw, '$2y$' . $this->cost . '$' . $salt);
    }

    /**
     * {@inheritdoc}
     */
    public function isPasswordValid($encoded, $raw, $salt = null)
    {
        return $this->comparePasswords($encoded, crypt($raw, $encoded));
    }

    /**
     * The blowfish/bcrypt used by PHP crypt uses and expects a different
     * set and order of characters than the usual base64_encode function.
     * Regular b64: ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789+/
     * Bcrypt b64:  ./ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789
     * We care because the last character in our encoded string will
     * only represent 2 bits.  While two known implementations of
     * bcrypt will happily accept and correct a salt string which
     * has the 4 unused bits set to non-zero, we do not want to take
     * chances and we also do not want to waste an additional byte
     * of entropy.
     *
     * @param bytes $random a string of 16 random bytes
     * @return string Properly encoded salt to use with php crypt function
     */
    protected function encodeSalt($random)
    {
        $len = strlen($random);
        if ($len<16) {
            throw new \InvalidArguementException(
                       'The bcrypt salt needs 16 random bytes');
        }
        if ($len>16) {
            $random = substr($random, 0, 16);
        }

        $base64_raw = strtr('+', '.', base64_encode($random));
        $base64_128bit = substr($base64_raw, 0, 21);
        $lastchar = substr($base64_raw, 21, 1);
        $lastchar = str_replace(array('A','Q','g','w'),
                                array('.','O','e','u'),
                                $lastchar);
        $base64_128bit .= $lastchar;

        return $base64_128bit;
    }

    /**
     * Get random bytes
     *
     * @param integer  $count Number if random bytes needed
     * @return string  String of random bytes that is $count bytes long
     */
    protected function get_random_bytes($count)
    {
        $random = '';
        if (@is_readable('/dev/urandom')) {
            $fh = @fopen('/dev/urandom', 'rb');
            if ($fh) {
                stream_set_read_buffer($fh, 0);
                stream_set_chunk_size($fh, 16);
                $random=fread($fh, $count);
                fclose($fh);
            }
        }

        if (strlen($random)<$count) {
            if(function_exists('openssl_random_pseudo_bytes') &&
               (strtoupper(substr(PHP_OS, 0, 3)) !== 'WIN')) {
                $random = openssl_random_pseudo_bytes($count);
            }
            
            $len = strlen($random);
            if ($len<$count) {
                for ($i=$len;$i<$count;++$i) {
                    $random .= chr(mt_rand(0,255));
                }
            }
        }

        return $random;
    }
}
