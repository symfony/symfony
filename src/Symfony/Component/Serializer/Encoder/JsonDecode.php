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
    protected $serializer;

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
     * @param string $format
     *
     * @return mixed
     */
    public function decode($data, $format, array $context = array())
    {
        $context = $this->resolveContext($context);

        $associative    = $context['json_decode_associative'];
        $recursionDepth = $context['json_decode_recursion_depth'];
        $options        = $context['json_decode_options'];

        if (version_compare(PHP_VERSION, '5.4.0') >= 0) {
            $decodedData = json_decode($data, $associative, $recursionDepth, $options);
        } else {
            $decodedData = json_decode($data, $associative, $recursionDepth);
        }

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

    /**
     * Merge the default options of the Json Decoder with the passed context.
     *
     * @param array $context
     * @return array
     */
    private function resolveContext(array $context)
    {
        $defaultOptions = array(
            'json_decode_associative' => $this->associative,
            'json_decode_recursion_depth' => $this->recursionDepth,
            'json_decode_options' => 0
        );

        return array_merge($defaultOptions, $context);
    }
}
