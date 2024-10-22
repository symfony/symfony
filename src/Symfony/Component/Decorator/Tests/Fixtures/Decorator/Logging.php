<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Decorator\Tests\Fixtures\Decorator;

use Psr\Log\LogLevel;
use Symfony\Component\Decorator\Attribute\DecoratorAttribute;

#[\Attribute(\Attribute::TARGET_FUNCTION | \Attribute::TARGET_METHOD)]
final class Logging extends DecoratorAttribute
{
    public function __construct(
        public string $level = LogLevel::DEBUG,
    ) {
    }
}
