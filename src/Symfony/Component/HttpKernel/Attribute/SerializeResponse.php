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

use Symfony\Component\HttpFoundation\Response;

/**
 * Automatically serialize the controller return value to JSON.
 *
 * When applied to a controller method, this attribute triggers automatic
 * JSON serialization of the returned object via the kernel.view event.
 */
#[\Attribute(\Attribute::TARGET_METHOD | \Attribute::TARGET_CLASS)]
final class SerializeResponse
{
    /**
     * @param int                  $status               The HTTP status code to use for the response
     * @param array<string, mixed> $serializationContext Serialization context
     * @param string               $format               The serialization format to use
     * @param array<string, mixed> $headers              HTTP headers to add to the response
     */
    public function __construct(
        public readonly int $status = Response::HTTP_OK,
        public readonly array $serializationContext = [],
        public readonly string $format = 'json',
        public readonly array $headers = [],
    ) {
    }
}
