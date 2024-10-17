<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\AccessToken\Bridge;

use Symfony\Component\AccessToken\AccessTokenInterface;
use Symfony\Component\AccessToken\CredentialsInterface;
use Symfony\Component\AccessToken\ProviderInterface;

/**
 * @author Pierre Rineau <pierre.rineau@processus.org>
 */
abstract class AbstractProvider implements ProviderInterface
{
    /**
     * Really fetch token, no cache, nothing, simply fetch it.
     */
    abstract protected function fetchToken(CredentialsInterface $credentials): AccessTokenInterface;

    public function getAccessToken(CredentialsInterface $credentials): AccessTokenInterface
    {
        return $this->fetchToken($credentials);
    }

    public function refreshAccessToken(CredentialsInterface $credentials): AccessTokenInterface
    {
        return $this->fetchToken($credentials);
    }
}
