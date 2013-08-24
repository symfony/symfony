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
 *
 * @since v2.2.0
 */
class JsonEncode implements EncoderInterface
{
    private $options ;
    private $lastError = JSON_ERROR_NONE;

    /**
     * @since v2.1.0
     */
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
     *
     * @since v2.1.0
     */
    public function getLastError()
    {
        return $this->lastError;
    }

    /**
     * Encodes PHP data to a JSON string
     *
     * {@inheritdoc}
     *
     * @since v2.2.0
     */
    public function encode($data, $format, array $context = array())
    {
        $context = $this->resolveContext($context);

        $encodedJson = json_encode($data, $context['json_encode_options']);
        $this->lastError = json_last_error();

        return $encodedJson;
    }

    /**
     * {@inheritdoc}
     *
     * @since v2.1.0
     */
    public function supportsEncoding($format)
    {
        return JsonEncoder::FORMAT === $format;
    }

    /**
     * Merge default json encode options with context.
     *
     * @param array $context
     * @return array
     *
     * @since v2.2.0
     */
    private function resolveContext(array $context = array())
    {
        return array_merge(array('json_encode_options' => $this->options), $context);
    }
}
