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

use Symfony\Component\PropertyAccess\PropertyAccess;
use Symfony\Component\Serializer\Exception\NotEncodableValueException;

/**
 * Encodes JSON data.
 *
 * @author Sander Coolen <sander@jibber.nl>
 */
class JsonEncode implements EncoderInterface
{
    private $options;
    private $propertyAccessor;

    public function __construct(int $bitmask = 0)
    {
        $this->options = $bitmask;
        $this->propertyAccessor = PropertyAccess::createPropertyAccessor();
    }

    /**
     * Encodes PHP data to a JSON string.
     *
     * {@inheritdoc}
     */
    public function encode($data, $format, array $context = array())
    {
        $context = $this->resolveContext($context);

        if ($propertyPath = $context[JsonEncoder::JSON_PROPERTY_PATH]) {
            $data = $this->wrapEncodableData($propertyPath, $data);
        }

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
    private function resolveContext(array $context = array())
    {
        return array_merge(array('json_encode_options' => $this->options, JsonEncoder::JSON_PROPERTY_PATH => null), $context);
    }

    /**
     * Wrap data before encoding.
     *
     * @param string $propertyPath
     * @param mixed  $data
     *
     * @return array
     */
    private function wrapEncodableData($propertyPath, $data)
    {
        $wrappedData = array();

        $this->propertyAccessor->setValue($wrappedData, $propertyPath, $data);

        return $wrappedData;
    }
}
