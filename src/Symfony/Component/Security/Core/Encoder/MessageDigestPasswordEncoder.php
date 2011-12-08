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
 * MessageDigestPasswordEncoder uses a message digest algorithm.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
class MessageDigestPasswordEncoder implements PasswordEncoderInterface
{
    private $algorithm;
	private $saltLength;
	private $iterations;
	private $encodeHashAsBase64;

    /**
     * Constructor.
     *
     * @param string  $algorithm          The digest algorithm to use
	 * @parma integer $saltLength         The Length of the salt (0
     * @param Boolean $encodeHashAsBase64 Whether to base64 encode the password hash
     * @param integer $iterations         The number of iterations to use to stretch the password hash
     */
    public function __construct($algorithm = 'sha512', $saltLength = 4, $encodeHashAsBase64 = true, $iterations = 5000)
    {
		if (!in_array($algorithm, hash_algos(), true)) {
			throw new \LogicException(sprintf('The algorithm "%s" is not supported.', $algorithm));
		}
		
		if ($saltLength < 0) {
			throw new \LogicException(sprintf('The Salt Length must be a positive integer ("%d" given).', $saltLength));
		}
	
		$this->algorithm = $algorithm;
		$this->saltLength = $saltLength;
		$this->encodeHashAsBase64 = $encodeHashAsBase64;
		$this->iterations = $iterations;
    }

    /**
     * {@inheritdoc}
     */
    public function encodePassword($plain)
    {
		$salt = "";
		for ($i = 0; $i < $this->saltLength; $i++) {
			$salt .= pack('c', rand(0x00, 0xff));
		}
		
		$digest = hash($this->algorithm, $plain . $salt, true) . $salt;
        for ($i = 1; $i < $this->iterations; $i++) {
            $digest = hash($this->algorithm, $digest . $salt, true) . $salt;
        }

        return $this->encodeHashAsBase64 ? base64_encode($digest) : bin2hex($digest);
    }

    /**
     * {@inheritdoc}
     */
    public function isPasswordValid($encoded, $plain)
    {	
		$digest1 = $this->encodeHashAsBase64 ? base64_decode($encoded, true) : hex2bin($encoded);	
		$salt = substr($digest1, -$this->saltLength);
		
		$digest2 = hash($this->algorithm, $plain . $salt, true) . $salt;
        for ($i = 1; $i < $this->iterations; $i++) {
            $digest2 = hash($this->algorithm, $digest2 . $salt, true) . $salt;
        }

		return 0 == strcmp($digest1, $digest2);
    }
}
