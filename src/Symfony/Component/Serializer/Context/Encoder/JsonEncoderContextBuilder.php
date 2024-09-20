<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Serializer\Context\Encoder;

use Symfony\Component\Serializer\Context\ContextBuilderInterface;
use Symfony\Component\Serializer\Context\ContextBuilderTrait;
use Symfony\Component\Serializer\Encoder\JsonDecode;
use Symfony\Component\Serializer\Encoder\JsonEncode;

/**
 * A helper providing autocompletion for available JsonEncoder options.
 *
 * @author Mathias Arlaud <mathias.arlaud@gmail.com>
 */
final class JsonEncoderContextBuilder implements ContextBuilderInterface
{
    use ContextBuilderTrait;

    /**
     * Configures the json_encode flags bitmask.
     *
     * @see https://www.php.net/manual/en/json.constants.php
     *
     * @param positive-int|null $options
     */
    public function withEncodeOptions(?int $options): static
    {
        return $this->with(JsonEncode::OPTIONS, $options);
    }

    /**
     * Configures the json_decode flags bitmask.
     *
     * @see https://www.php.net/manual/en/json.constants.php
     *
     * @param positive-int|null $options
     */
    public function withDecodeOptions(?int $options): static
    {
        return $this->with(JsonDecode::OPTIONS, $options);
    }

    /**
     * Configures whether decoded objects will be given as
     * associative arrays or as nested stdClass.
     */
    public function withAssociative(?bool $associative): static
    {
        return $this->with(JsonDecode::ASSOCIATIVE, $associative);
    }

    /**
     * Configures the maximum recursion depth.
     *
     * Must be strictly positive.
     *
     * @param positive-int|null $recursionDepth
     */
    public function withRecursionDepth(?int $recursionDepth): static
    {
        return $this->with(JsonDecode::RECURSION_DEPTH, $recursionDepth);
    }
}
