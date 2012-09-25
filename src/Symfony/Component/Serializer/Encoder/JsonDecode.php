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
 * Decodes JSON data
 *
 * @author Sander Coolen <sander@jibber.nl>
 */
class JsonDecode implements DecoderInterface
{
    private $associative;
    private $recursionDepth;
    private $lastError = JSON_ERROR_NONE;

    public function __construct($associative = false, $depth = 512)
    {
        $this->associative = $associative;
        $this->recursionDepth = $depth;
    }

    /**
     * Returns the last decoding error (if any)
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
     * Decodes a JSON string into PHP data
     *
     * @param string $data JSON
     *
     * @return mixed
     */
    public function decode($data, $format)
    {
        $decodedData = json_decode($data, $this->associative, $this->recursionDepth);
        $this->lastError = json_last_error();

        return $decodedData;
    }

    /**
     * {@inheritdoc}
     */
    public function supportsDecoding($format)
    {
        return JsonEncoder::FORMAT === $format;
    }
}
