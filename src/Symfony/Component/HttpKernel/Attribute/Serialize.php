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
#[\Attribute(\Attribute::TARGET_METHOD)]
final class Serialize
{
    /**
     * @param int                  $code    The HTTP status code (200 "OK" by default)
     * @param array<string, mixed> $headers Extra headers to set on the response
     * @param array<string, mixed> $context The serialization context passed to the serializer
     */
    public function __construct(
        public readonly int $code = 200,
        public readonly array $headers = [],
        public readonly array $context = [],
    ) {
    }
}
