<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Security\OAuth2Client\Authorization;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class AuthorizationCodeResponse
{
    private $code;
    private $state;
    public const TYPE = 'code';

    public function __construct(string $code, string $state)
    {
        $this->code = $code;
        $this->state = $state;
    }

    public function getCode(): string
    {
        return $this->code;
    }

    public function getState(): string
    {
        return $this->state;
    }
}
