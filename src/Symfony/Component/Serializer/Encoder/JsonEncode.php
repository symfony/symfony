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
    public const OPTIONS = 'json_encode_options';

    private $defaultContext = [
        self::OPTIONS => 0,
    ];

    /**
     * @param array $defaultContext
     */
    public function __construct($defaultContext = [])
    {
        if (!\is_array($defaultContext)) {
            @trigger_error(sprintf('Passing an integer as first parameter of the "%s()" method is deprecated since Symfony 4.2, use the "json_encode_options" key of the context instead.', __METHOD__), \E_USER_DEPRECATED);

            $this->defaultContext[self::OPTIONS] = (int) $defaultContext;
        } else {
            $this->defaultContext = array_merge($this->defaultContext, $defaultContext);
        }
    }

    /**
     * Encodes PHP data to a JSON string.
     *
     * {@inheritdoc}
     */
    public function encode($data, $format, array $context = [])
    {
        $options = $context[self::OPTIONS] ?? $this->defaultContext[self::OPTIONS];

        try {
            $encodedJson = json_encode($data, $options);
        } catch (\JsonException $e) {
            throw new NotEncodableValueException($e->getMessage(), 0, $e);
        }

        if (\PHP_VERSION_ID >= 70300 && (\JSON_THROW_ON_ERROR & $options)) {
            return $encodedJson;
        }

        if (\JSON_ERROR_NONE !== json_last_error() && (false === $encodedJson || !($options & \JSON_PARTIAL_OUTPUT_ON_ERROR))) {
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
