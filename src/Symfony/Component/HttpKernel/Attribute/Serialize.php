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
    public function __construct(
        public readonly int $code = 200,
        /**
         * @var array<string, string>
         */
        public readonly array $headers = [],
        /**
         * @var array<string, string>
         */
        public readonly array $serializationContext = [],
    ) {
    }
}
