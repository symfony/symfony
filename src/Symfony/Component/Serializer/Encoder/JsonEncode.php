<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Serializer\Encoder;

use Symfony\Component\Serializer\Exception\NotEncodableValueException;

/**
 * Encodes JSON data.
 *
 * @author Sander Coolen <sander@jibber.nl>
 */
class JsonEncode implements EncoderInterface
{
    const OPTIONS = 'json_encode_options';

    private $defaultContext = [
        self::OPTIONS => 0,
    ];

    public function __construct(array $defaultContext = [])
    {
        $this->defaultContext = array_merge($this->defaultContext, $defaultContext);
    }

    /**
     * Encodes PHP data to a JSON string.
     *
     * {@inheritdoc}
     */
    public function encode($data, $format, array $context = [])
    {
        $jsonEncodeOptions = $context[self::OPTIONS] ?? $this->defaultContext[self::OPTIONS];
        $encodedJson = json_encode($data, $jsonEncodeOptions);

        if (\PHP_VERSION_ID >= 70300 && (JSON_THROW_ON_ERROR & $jsonEncodeOptions)) {
            return $encodedJson;
        }

        if (JSON_ERROR_NONE !== json_last_error() && (false === $encodedJson || !($jsonEncodeOptions & JSON_PARTIAL_OUTPUT_ON_ERROR))) {
            throw new NotEncodableValueException(json_last_error_msg());
        }

        return $encodedJson;
    }

    /**
     * {@inheritdoc}
     */
    public function supportsEncoding($format)
    {
        return JsonEncoder::FORMAT === $format;
    }
}
