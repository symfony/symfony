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

use Symfony\Component\Serializer\Exception\UnexpectedValueException;

/**
 * Encodes JSON data.
 *
 * @author Sander Coolen <sander@jibber.nl>
 */
class JsonEncode implements EncoderInterface
{
    private $options;
    private $lastError = JSON_ERROR_NONE;

    public function __construct($bitmask = 0)
    {
        $this->options = $bitmask;
    }

    /**
     * Returns the last encoding error (if any).
     *
     * @return int
     *
     * @deprecated since version 2.5, to be removed in 3.0.
     *             The {@self encode()} throws an exception if error found.
     *
     * @see http://php.net/manual/en/function.json-last-error.php json_last_error
     */
    public function getLastError()
    {
        @trigger_error('The '.__METHOD__.' method is deprecated since version 2.5 and will be removed in 3.0. Catch the exception raised by the encode() method instead to get the last JSON encoding error.', E_USER_DEPRECATED);

        return $this->lastError;
    }

    /**
     * Encodes PHP data to a JSON string.
     *
     * {@inheritdoc}
     */
    public function encode($data, $format, array $context = array())
    {
        $context = $this->resolveContext($context);

        $encodedJson = json_encode($data, $context['json_encode_options']);

        if (JSON_ERROR_NONE !== $this->lastError = json_last_error()) {
            throw new UnexpectedValueException(JsonEncoder::getLastErrorMessage());
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
     * @param array $context
     *
     * @return array
     */
    private function resolveContext(array $context = array())
    {
        return array_merge(array('json_encode_options' => $this->options), $context);
    }
}
