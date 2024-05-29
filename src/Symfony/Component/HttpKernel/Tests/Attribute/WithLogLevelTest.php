<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HttpKernel\Tests\Attribute;

use PHPUnit\Framework\TestCase;
use Psr\Log\LogLevel;
use Symfony\Component\HttpKernel\Attribute\WithLogLevel;

/**
 * @author Dejan Angelov <angelovdejan@protonmail.com>
 */
class WithLogLevelTest extends TestCase
{
    public function testWithValidLogLevel()
    {
        $logLevel = LogLevel::NOTICE;

        $attribute = new WithLogLevel($logLevel);

        $this->assertSame($logLevel, $attribute->level);
    }

    public function testWithInvalidLogLevel()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid log level "invalid".');

        new WithLogLevel('invalid');
    }
}
