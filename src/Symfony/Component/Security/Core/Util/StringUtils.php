<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Security\Core\Util;

use Symfony\Component\Security\Core\Exception\InvalidArgumentException;

/**
 * String utility functions.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
class StringUtils
{

    /**
     * A class to provide random data for hmac keys.
     *
     * @var SecureRandomInterface
     */
    protected $random;

    /**
     * Key size used for hash_hmac keys, in bytes.
     *
     * @var int
     */
    protected $keySize = 16;

    /**
     * The algorithm to use with hash_hmac
     *
     * @var string
     */
    protected $algo = "sha512";

    /**
     * This class can be instantiated with a SecureRandomInterface to provide
     * additional security in the form of stronger timing attack resistance
     * when comparing strings by masking length.
     *
     * @param SecureRandomInterface $random
     */
    public function __construct(SecureRandomInterface $random)
    {
        $this->random = $random;
    }

    /**
     * Compares two strings.
     *
     * This method implements a constant-time algorithm to compare strings.
     * Regardless of the used implementation, it will leak length information.
     *
     * @param string $knownString The string of known length to compare against
     * @param string $userInput   The string that the user can control
     *
     * @return bool    true if the two strings are the same, false otherwise
     */
    public static function equals($knownString, $userInput)
    {
        $knownString = (string) $knownString;
        $userInput = (string) $userInput;

        if (function_exists('hash_equals')) {
            return hash_equals($knownString, $userInput);
        }

        $knownLen = strlen($knownString);
        $userLen = strlen($userInput);

        // Extend the known string to avoid uninitialized string offsets
        $knownString .= $userInput;

        // Set the result to the difference between the lengths
        $result = $knownLen - $userLen;

        // Note that we ALWAYS iterate over the user-supplied length
        // This is to mitigate leaking length information
        for ($i = 0; $i < $userLen; $i++) {
            $result |= (ord($knownString[$i]) ^ ord($userInput[$i]));
        }

        // They are only identical strings if $result is exactly 0...
        return 0 === $result;
    }

    /**
     * Compares two strings using a random source and hash_hmac.
     *
     * This method implements a constant-time algorithm to compare strings;
     * this method will mask length information about the known string.
     *
     * @param string $knownString The string of known length to compare against
     * @param string $userInput   The string that the user can control
     *
     * @return bool    true if the two strings are the same, false otherwise
     */
    public function equalsHash($knownString, $userInput)
    {
        $key = $this->random->nextBytes($this->keySize);

        // Here we hash_hmac the input using the same randomly generated key.
        // This also generates a random offset useful for masking length.
        $knownHash = hash_hmac($this->algo, $knownString, $key);
        $userHash = hash_hmac($this->algo, $userInput, $key);

        // length here will always be constant due to hash_hmac
        $length = strlen($userHash);

        // We can safely iterate over the length because the computed
        // length is constant in both cases.
        for ($i = 0, $result = 0; $i < $length; $i++) {
            $result |= (ord($knownHash[$i]) ^ ord($userHash[$i]));
        }

        // They are only identical strings if $result is exactly 0...
        return 0 === $result;
    }

    /**
     * Set a key size to use for comparison.
     *
     * @param int $keySize The length to use for key size, in bytes.
     */
    public function setKeySize($keySize)
    {
        if ( ! is_numeric($keySize) or ((int) $keySize <= 0))
            throw new InvalidArgumentException("Key size should be an integer > 0");

        // We want to avoid casting to int in the event that $keySize
        // is an object or a resource-like handle as it will return
        // an integer unrelated to the object/resource.
        $this->keySize = (int) $keySize;
    }

    /**
     * Get the random key size for this instance.
     *
     * @return int The key size (in bytes) used for hash keys.
     */
    public function getKeySize()
    {
        return $this->keySize;
    }

    /**
     * Set the algorithm used to hash_hmac compare two strings.
     *
     * @param string $algo The algorithm to use for hashing strings.
     */
    public function setAlgo($algo)
    {
        $algo = (string) $algo;

        if ( ! in_array($algo, hash_algos()))
            throw new InvalidArgumentException("$algo is not a supported algorithm");

        $this->algo = $algo;
    }
}
