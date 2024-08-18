<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Webhook\Server;

final class NativeJsonPayloadSerializer implements PayloadSerializerInterface
{
    public function serialize(array $payload): string
    {
        return json_encode($payload, \JSON_THROW_ON_ERROR);
    }
}
