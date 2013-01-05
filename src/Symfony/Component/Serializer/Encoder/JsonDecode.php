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

use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Component\Serializer\SerializerAwareInterface;

/**
 * Decodes JSON data
 *
 * @author Sander Coolen <sander@jibber.nl>
 */
class JsonDecode implements DecoderInterface, SerializerAwareInterface
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
    public function decode($data, $format)
    {
        $context = $this->getContext();

        $associative    = $context['associative'];
        $recursionDepth = $context['recursionDepth'];
        $options        = $context['options'];

        $decodedData = json_decode($data, $associative, $recursionDepth, $options);
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
     * {@inheritdoc}
     */
    public function setSerializer(SerializerInterface $serializer)
    {
        $this->serializer = $serializer;
    }

    private function getContext()
    {
        $options = array(
            'associative' => $this->associative,
            'recursionDepth' => $this->recursionDepth,
            'options' => 0
        );

        if (!$this->serializer) {
            return $options;
        }

        $options = array(
            'associative' => false,
            'recursionDepth' => 512,
            'options' => 0
        );

        $context = $this->serializer->getContext();

        if (isset($context['associative'])) {
            $options['associative'] = $context['associative'];
        }

        if (isset($context['recursionDepth'])) {
            $options['recursionDepth'] = $context['recursionDepth'];
        }

        if (isset($context['options'])) {
            $options['options'] = $context['options'];
        }

        return $options;
    }
}
