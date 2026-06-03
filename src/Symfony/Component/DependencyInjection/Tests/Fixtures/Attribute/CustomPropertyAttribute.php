<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\DependencyInjection\Tests\Fixtures\Attribute;

#[\Attribute(\Attribute::TARGET_PROPERTY)]
final class CustomPropertyAttribute
{
    public function __construct(
        public string $someAttribute,
        public int $priority = 0,
    ) {
    }
}
