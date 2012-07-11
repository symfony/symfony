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
class JsonEncode implements EncoderInterface
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
     * @param mixed $data
     *
     * @return string
     */
    public function encode($data, $format)
    {
        $encodedJson = json_encode($data, $this->options);
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
}
