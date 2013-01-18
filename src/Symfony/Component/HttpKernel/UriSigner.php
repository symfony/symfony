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
        if (!preg_match('/(\?|&)_hash=(.+?)$/', $uri, $matches, PREG_OFFSET_CAPTURE)) {
            return false;
        }

        // the naked URI is the URI without the _hash parameter (we need to keep the ? if there is some other parameters after)
        $nakedUri = substr($uri, 0, $matches[0][1]).substr($uri, $matches[0][1] + strlen($matches[0][0]));

        return $this->computeHash($nakedUri) === $matches[2][0];
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
