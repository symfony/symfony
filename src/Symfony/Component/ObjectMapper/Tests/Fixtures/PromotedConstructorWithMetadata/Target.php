<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\ObjectMapper\Tests\Fixtures\PromotedConstructorWithMetadata;

class Target
{
    public function __construct(
        /**
         * This promoted property is required but should not lead to an exception on the object mapping as instantiation
         * happened earlier already.
         */
        public string $notOnSourceButRequired,
        public int    $number,
        public string $name,
    ) {
    }
}
