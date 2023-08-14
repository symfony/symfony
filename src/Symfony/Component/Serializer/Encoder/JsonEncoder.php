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
    public const FORMAT = 'json';

    protected $encodingImpl;
    protected $decodingImpl;

    private array $defaultContext = [
        JsonDecode::ASSOCIATIVE => true,
    ];

    public function __construct(JsonEncode $encodingImpl = null, JsonDecode $decodingImpl = null, array $defaultContext = [])
    {
        $this->defaultContext = array_merge($this->defaultContext, $defaultContext);
        $this->encodingImpl = $encodingImpl ?? new JsonEncode($this->defaultContext);
        $this->decodingImpl = $decodingImpl ?? new JsonDecode($this->defaultContext);
    }

    public function encode(mixed $data, string $format, array $context = []): string
    {
        $context = array_merge($this->defaultContext, $context);

        return $this->encodingImpl->encode($data, self::FORMAT, $context);
    }

    public function decode(string $data, string $format, array $context = []): mixed
    {
        $context = array_merge($this->defaultContext, $context);

        return $this->decodingImpl->decode($data, self::FORMAT, $context);
    }

    public function supportsEncoding(string $format): bool
    {
        return self::FORMAT === $format;
    }

    public function supportsDecoding(string $format): bool
    {
        return self::FORMAT === $format;
    }
}
