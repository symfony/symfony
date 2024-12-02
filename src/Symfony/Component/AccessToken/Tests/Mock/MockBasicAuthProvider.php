<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\AccessToken\Tests\Mock;

use Symfony\Component\AccessToken\AccessToken;
use Symfony\Component\AccessToken\AccessTokenInterface;
use Symfony\Component\AccessToken\CredentialsInterface;
use Symfony\Component\AccessToken\ProviderInterface;
use Symfony\Component\AccessToken\Credentials\BasicAuthCredentials;

/**
 * @author Pierre Rineau <pierre.rineau@processus.org>
 */
class MockBasicAuthProvider implements ProviderInterface
{
    public function supports(CredentialsInterface $credentials): bool
    {
        return $credentials instanceof BasicAuthCredentials;
    }

    public function getAccessToken(CredentialsInterface $credentials): AccessTokenInterface
    {
        \assert($credentials instanceof BasicAuthCredentials);

        return new AccessToken('get' . $credentials->getUsername());
    }

    public function refreshAccessToken(CredentialsInterface $credentials): AccessTokenInterface
    {
        \assert($credentials instanceof BasicAuthCredentials);

        return new AccessToken('refresh' . $credentials->getUsername());
    }
}
