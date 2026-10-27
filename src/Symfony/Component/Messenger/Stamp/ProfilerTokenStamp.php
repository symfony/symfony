<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Messenger\Stamp;

/**
 * Holds the token of the profile collected while the message was sent to its transports.
 *
 * The profiles of the worker that handles the message link back to that profile.
 */
final class ProfilerTokenStamp implements StampInterface
{
    public function __construct(
        private string $token,
    ) {
    }

    public function getToken(): string
    {
        return $this->token;
    }
}
