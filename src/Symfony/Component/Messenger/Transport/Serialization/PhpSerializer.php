<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Messenger\Transport\Serialization;

use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Exception\InvalidArgumentException;

/**
 * @author Ryan Weaver<ryan@symfonycasts.com>
 *
 * @experimental in 4.2
 */
class PhpSerializer implements SerializerInterface
{
    /**
     * {@inheritdoc}
     */
    public function decode(array $encodedEnvelope): Envelope
    {
        if (empty($encodedEnvelope['body'])) {
            throw new InvalidArgumentException('Encoded envelope should have at least a "body".');
        }

        return unserialize($encodedEnvelope['body']);
    }

    /**
     * {@inheritdoc}
     */
    public function encode(Envelope $envelope): array
    {
        return [
            'body' => serialize($envelope),
        ];
    }
}
