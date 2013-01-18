<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HttpKernel;

/**
 * Signs URIs.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
class UriSigner implements UriSignerInterface
{
    protected $secret;

    /**
     * Constructor.
     *
     * @param string $secret A secret
     */
    public function __construct($secret)
    {
        $this->secret = $secret;
    }

    /**
     * {@inheritdoc}
     */
    public function sign($uri)
    {
        return $uri.(false === (strpos($uri, '?')) ? '?' : '&').'_hash='.$this->computeHash($uri);
    }

    /**
     * {@inheritdoc}
     */
    public function check($uri)
    {
        if (!preg_match('/(.*)(?:\?|&)_hash=(.+?)$/', $uri, $matches)) {
            return false;
        }

        return $this->computeHash($matches[1]) === $matches[2];
    }

    /**
     * @param string $uri
     *
     * @return string A signature
     */
    protected function computeHash($uri)
    {
        return urlencode(base64_encode(hash_hmac('sha1', $uri, $this->secret, true)));
    }
}
