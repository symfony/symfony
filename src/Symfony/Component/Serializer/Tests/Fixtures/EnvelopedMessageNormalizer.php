<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Serializer\Tests\Fixtures;

use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

/**
 * @author Karoly Gossler <connor@connor.hu>
 */
class EnvelopedMessageNormalizer implements NormalizerInterface
{
    public function normalize($message, string $format = null, array $context = []): array
    {
        return [
            'text' => $message->text,
        ];
    }

    public function getSupportedTypes(?string $format): array
    {
        return [
            EnvelopedMessage::class => true,
        ];
    }

    public function supportsNormalization($data, string $format = null, array $context = []): bool
    {
        return $data instanceof EnvelopedMessage;
    }
}
