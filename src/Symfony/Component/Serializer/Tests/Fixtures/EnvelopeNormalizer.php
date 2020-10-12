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
class EnvelopeNormalizer implements NormalizerInterface
{
    private $serializer;

    public function normalize($envelope, $format = null, array $context = [])
    {
        $xmlContent = $this->serializer->serialize($envelope->message, 'xml');

        $encodedContent = base64_encode($xmlContent);

        return [
            'message' => $encodedContent,
        ];
    }

    public function supportsNormalization($data, $format = null)
    {
        return $data instanceof EnvelopeObject;
    }

    public function setSerializer($serializer)
    {
        $this->serializer = $serializer;
    }
}
