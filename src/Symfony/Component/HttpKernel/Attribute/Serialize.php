<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HttpKernel\Attribute;

/**
 * Controller tag to serialize response.
 *
 * @author Konstantin Myakshin <molodchick@gmail.com>
 */
#[\Attribute(\Attribute::TARGET_CLASS | \Attribute::TARGET_METHOD | \Attribute::TARGET_FUNCTION)]
final class Serialize
{
    /**
     * @param int                  $code    The HTTP status code (200 "OK" by default)
     * @param array<string, mixed> $headers Extra headers to set on the response
     * @param array<string, mixed> $context The serialization context passed to the serializer, or the options passed to the stream writer when streaming
     * @param class-string|null    $map     The class that the object mapper maps the returned value to before serialization, item by item for iterables
     * @param bool                 $stream  Whether to stream the returned value as JSON with the stream writer instead of serializing it
     *
     * @stable-parameter-names
     */
    public function __construct(
        public readonly int $code = 200,
        public readonly array $headers = [],
        public readonly array $context = [],
        public readonly ?string $map = null,
        public readonly bool $stream = false,
    ) {
    }
}
