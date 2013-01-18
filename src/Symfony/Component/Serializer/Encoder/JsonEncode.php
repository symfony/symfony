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

/**
 * Encodes JSON data
 *
 * @author Sander Coolen <sander@jibber.nl>
 */
class JsonEncode extends SerializerAwareEncoder implements EncoderInterface
{
    private $options ;
    private $lastError = JSON_ERROR_NONE;

    public function __construct($bitmask = 0)
    {
        $this->options = $bitmask;
    }

    /**
     * Returns the last encoding error (if any)
     *
     * @return integer
     *
     * @see http://php.net/manual/en/function.json-last-error.php json_last_error
     */
    public function getLastError()
    {
        return $this->lastError;
    }

    /**
     * Encodes PHP data to a JSON string
     *
     * @param mixed  $data
     * @param string $format
     *
     * @return string
     */
    public function encode($data, $format)
    {
        $options = $this->getContext();

        $encodedJson = json_encode($data, $options);
        $this->lastError = json_last_error();

        return $encodedJson;
    }

    /**
     * {@inheritdoc}
     */
    public function supportsEncoding($format)
    {
        return JsonEncoder::FORMAT === $format;
    }

    private function getContext()
    {
        if (!$this->serializer) {
            return 0;
        }

        $context = $this->serializer->getContext();

        if (empty($context)) {
            $context = array(0);
        }

        if (!is_array($context)) {
            $context = array($context);
        }

        return array_sum($context);
    }
}
