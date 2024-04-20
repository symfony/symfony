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

use Seld\JsonLint\JsonParser;
use Symfony\Component\Serializer\Exception\NotEncodableValueException;
use Symfony\Component\Serializer\Exception\UnsupportedException;

/**
 * Decodes JSON data.
 *
 * @author Sander Coolen <sander@jibber.nl>
 */
class JsonDecode implements DecoderInterface
{
    /**
     * True to return the result as an associative array, false for a nested stdClass hierarchy.
     */
    public const ASSOCIATIVE = 'json_decode_associative';

    /**
     * True to enable seld/jsonlint as a source for more specific error messages when json_decode fails.
     */
    public const DETAILED_ERROR_MESSAGES = 'json_decode_detailed_errors';

    public const OPTIONS = 'json_decode_options';

    /**
     * Specifies the recursion depth.
     */
    public const RECURSION_DEPTH = 'json_decode_recursion_depth';

    private array $defaultContext = [
        self::ASSOCIATIVE => false,
        self::DETAILED_ERROR_MESSAGES => false,
        self::OPTIONS => 0,
        self::RECURSION_DEPTH => 512,
    ];

    public function __construct(array $defaultContext = [])
    {
        $this->defaultContext = array_merge($this->defaultContext, $defaultContext);
    }

    /**
     * Decodes data.
     *
     * @param string $data    The encoded JSON string to decode
     * @param string $format  Must be set to JsonEncoder::FORMAT
     * @param array  $context An optional set of options for the JSON decoder; see below
     *
     * The $context array is a simple key=>value array, with the following supported keys:
     *
     * json_decode_associative: boolean
     *      If true, returns the object as an associative array.
     *      If false, returns the object as nested stdClass
     *      If not specified, this method will use the default set in JsonDecode::__construct
     *
     * json_decode_recursion_depth: integer
     *      Specifies the maximum recursion depth
     *      If not specified, this method will use the default set in JsonDecode::__construct
     *
     * json_decode_options: integer
     *      Specifies additional options as per documentation for json_decode
     *
     * json_decode_detailed_errors: bool
     *      If true, enables seld/jsonlint as a source for more specific error messages when json_decode fails.
     *      If false or not specified, this method will use default error messages from PHP's json_decode
     *
     * @throws NotEncodableValueException
     *
     * @see https://php.net/json_decode
     */
    public function decode(string $data, string $format, array $context = []): mixed
    {
        $associative = $context[self::ASSOCIATIVE] ?? $this->defaultContext[self::ASSOCIATIVE];
        $recursionDepth = $context[self::RECURSION_DEPTH] ?? $this->defaultContext[self::RECURSION_DEPTH];
        $options = $context[self::OPTIONS] ?? $this->defaultContext[self::OPTIONS];

        try {
            $decodedData = json_decode($data, $associative, $recursionDepth, $options);
        } catch (\JsonException $e) {
            throw new NotEncodableValueException($e->getMessage(), 0, $e);
        }

        if (\JSON_THROW_ON_ERROR & $options) {
            return $decodedData;
        }

        if (\JSON_ERROR_NONE === json_last_error()) {
            return $decodedData;
        }
        $errorMessage = json_last_error_msg();

        if (!($context[self::DETAILED_ERROR_MESSAGES] ?? $this->defaultContext[self::DETAILED_ERROR_MESSAGES])) {
            throw new NotEncodableValueException($errorMessage);
        }

        if (!class_exists(JsonParser::class)) {
            throw new UnsupportedException(sprintf('Enabling "%s" serializer option requires seld/jsonlint. Try running "composer require seld/jsonlint".', self::DETAILED_ERROR_MESSAGES));
        }

        throw new NotEncodableValueException((new JsonParser())->lint($data)?->getMessage() ?: $errorMessage);
    }

    public function supportsDecoding(string $format): bool
    {
        return JsonEncoder::FORMAT === $format;
    }
}
