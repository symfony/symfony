<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Security\OAuth2Client\Event;

use Symfony\Component\Security\OAuth2Client\Token\AbstractToken;
use Symfony\Contracts\EventDispatcher\Event;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class RefreshTokenFetchEvent extends Event
{
    private $token;

    public function __construct(AbstractToken $token)
    {
        $this->token = $token;
    }

    public function getToken(): AbstractToken
    {
        return $this->token;
    }
}
