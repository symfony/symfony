<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Mailer\Bridge\Amazon\Credential;

/**
 * @author Karoly Gossler <connor@connor.hu>
 */
final class ApiTokenCredential
{
    private $accessKey;

    private $secretKey;

    private $token;

    private $expiration;

    public function __construct(string $accessKey, string $secretKey, string $token, \DateTime $expiration)
    {
        $this->accessKey = $accessKey;
        $this->secretKey = $secretKey;
        $this->token = $token;
        $this->expiration = $expiration;
    }

    public function getAccessKey(): string
    {
        return $this->accessKey;
    }

    public function getSecretKey(): string
    {
        return $this->secretKey;
    }

    public function getToken(): string
    {
        return $this->token;
    }

    public function getExpiration(): \DateTime
    {
        return $this->expiration;
    }
}
