<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Serializer\Tests\Fixtures\Attributes;

use Symfony\Component\Serializer\Attribute\Context;
use Symfony\Component\Serializer\Normalizer\SanitizeDenormalizer;

/**
 * @author Mohamed Senoussi <lesfootix@gmail.com>
 */
class SanitizeDummyWithInvalidArray
{
    public function __construct(
        /**
         * @var int[]
         */
        #[Context(denormalizationContext: [SanitizeDenormalizer::SANITIZER_KEY => SanitizeDenormalizer::DEFAULT_SANITIZER])]
        public array $foo
    ) {}
}
