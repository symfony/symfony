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
    public function normalize($message, $format = null, array $context = [])
    {
        return [
            'text' => $message->text,
        ];
    }

    public function supportsNormalization($data, $format = null)
    {
        return $data instanceof EnvelopedMessage;
    }
}
