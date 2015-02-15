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
 * Encodes JSON data.
 *
 * @author Jordi Boggiano <j.boggiano@seld.be>
 */
class JsonEncoder implements EncoderInterface, DecoderInterface
{
    const FORMAT = 'json';

    /**
     * @var JsonEncode
     */
    protected $encodingImpl;

    /**
     * @var JsonDecode
     */
    protected $decodingImpl;

    public function __construct(JsonEncode $encodingImpl = null, JsonDecode $decodingImpl = null)
    {
        $this->encodingImpl = $encodingImpl ?: new JsonEncode();
        $this->decodingImpl = $decodingImpl ?: new JsonDecode(true);
    }

    /**
<<<<<<< HEAD
     * Returns the last encoding error (if any).
     *
     * @return int
     *
     * @deprecated since version 2.5, to be removed in 3.0. JsonEncode throws exception if an error is found.
     */
    public function getLastEncodingError()
    {
        trigger_error('The '.__METHOD__.' method is deprecated since version 2.5 and will be removed in 3.0. Catch the exception raised by the Symfony\Component\Serializer\Encoder\JsonEncode::encode() method instead to get the last JSON encoding error.', E_USER_DEPRECATED);

        return $this->encodingImpl->getLastError();
    }

    /**
     * Returns the last decoding error (if any).
     *
     * @return int
     *
     * @deprecated since version 2.5, to be removed in 3.0. JsonDecode throws exception if an error is found.
     */
    public function getLastDecodingError()
    {
        trigger_error('The '.__METHOD__.' method is deprecated since version 2.5 and will be removed in 3.0. Catch the exception raised by the Symfony\Component\Serializer\Encoder\JsonDecode::decode() method instead to get the last JSON decoding error.', E_USER_DEPRECATED);

        return $this->decodingImpl->getLastError();
    }

    /**
=======
>>>>>>> 22cd78c4a87e94b59ad313d11b99acb50aa17b8d
     * {@inheritdoc}
     */
    public function encode($data, $format, array $context = array())
    {
        return $this->encodingImpl->encode($data, self::FORMAT, $context);
    }

    /**
     * {@inheritdoc}
     */
    public function decode($data, $format, array $context = array())
    {
        return $this->decodingImpl->decode($data, self::FORMAT, $context);
    }

    /**
     * {@inheritdoc}
     */
    public function supportsEncoding($format)
    {
        return self::FORMAT === $format;
    }

    /**
     * {@inheritdoc}
     */
    public function supportsDecoding($format)
    {
        return self::FORMAT === $format;
    }

    /**
     * Resolves json_last_error message.
     *
     * @return string
     */
    public static function getLastErrorMessage()
    {
<<<<<<< HEAD
        if (function_exists('json_last_error_msg')) {
            return json_last_error_msg();
        }

        switch (json_last_error()) {
            case JSON_ERROR_DEPTH:
                return 'Maximum stack depth exceeded';
            case JSON_ERROR_STATE_MISMATCH:
                return 'Underflow or the modes mismatch';
            case JSON_ERROR_CTRL_CHAR:
                return 'Unexpected control character found';
            case JSON_ERROR_SYNTAX:
                return 'Syntax error, malformed JSON';
            case JSON_ERROR_UTF8:
                return 'Malformed UTF-8 characters, possibly incorrectly encoded';
            default:
                return 'Unknown error';
        }
=======
        return json_last_error_msg();
>>>>>>> 22cd78c4a87e94b59ad313d11b99acb50aa17b8d
    }
}
