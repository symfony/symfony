<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\FrameworkBundle\Routing;

/**
 * Signs and verifies signed urls.
 *
 * @author Alexander <iam.asm89@gmail.com>
 */
class UrlSigner
{
    private $secret;

    /**
     * @param string $secret
     */
    public function __construct($secret)
    {
        $this->secret = $secret;
    }

    /**
     * @param string $url 
     *
     * @return string Signed url.
     */
    public function sign($url)
    {
        // todo: actually sign the url
        return $url;
    }

    /**
     * @param string $url
     *
     * @return boolean
     */
    public function verify($url)
    {
        // todo: actually verify the signed url
        return true;
    }
}
