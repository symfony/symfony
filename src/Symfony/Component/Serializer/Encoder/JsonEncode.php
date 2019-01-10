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
    private $options;

    public function __construct($bitmask = 0)
    {
        $this->options = $bitmask;
    }

    /**
     * Encodes PHP data to a JSON string.
     *
     * {@inheritdoc}
     */
    public function encode($data, $format, array $context = [])
    {
        $context = $this->resolveContext($context);

        $encodedJson = json_encode($data, $context['json_encode_options']);

        if (JSON_ERROR_NONE !== json_last_error() && (false === $encodedJson || !($context['json_encode_options'] & JSON_PARTIAL_OUTPUT_ON_ERROR))) {
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

    /**
     * Merge default json encode options with context.
     *
     * @return array
     */
    private function resolveContext(array $context = [])
    {
        return array_merge(['json_encode_options' => $this->options], $context);
    }
}
