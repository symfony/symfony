<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\CallableWrapper\Tests\Fixtures\CallableWrapper;

use Psr\Log\LogLevel;
use Symfony\Component\CallableWrapper\Attribute\CallableWrapperAttribute;

#[\Attribute(\Attribute::TARGET_CLASS | \Attribute::TARGET_METHOD | \Attribute::TARGET_FUNCTION)]
final class Logging extends CallableWrapperAttribute
{
    public function __construct(
        public string $level = LogLevel::DEBUG,
    ) {
    }
}
