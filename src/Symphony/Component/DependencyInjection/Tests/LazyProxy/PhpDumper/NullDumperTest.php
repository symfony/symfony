<?php

/*
 * This file is part of the Symphony package.
 *
 * (c) Fabien Potencier <fabien@symphony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symphony\Component\DependencyInjection\Tests\LazyProxy\PhpDumper;

use PHPUnit\Framework\TestCase;
use Symphony\Component\DependencyInjection\Definition;
use Symphony\Component\DependencyInjection\LazyProxy\PhpDumper\NullDumper;

/**
 * Tests for {@see \Symphony\Component\DependencyInjection\PhpDumper\NullDumper}.
 *
 * @author Marco Pivetta <ocramius@gmail.com>
 */
class NullDumperTest extends TestCase
{
    public function testNullDumper()
    {
        $dumper = new NullDumper();
        $definition = new Definition('stdClass');

        $this->assertFalse($dumper->isProxyCandidate($definition));
        $this->assertSame('', $dumper->getProxyFactoryCode($definition, 'foo', '(false)'));
        $this->assertSame('', $dumper->getProxyCode($definition));
    }
}
