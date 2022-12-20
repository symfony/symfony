<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Yaml\Tests;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Yaml\Yaml;

class YamlTest extends TestCase
{
    public function testParseAndDump()
    {
        $data = ['lorem' => 'ipsum', 'dolor' => 'sit'];
        $yml = Yaml::dump($data);
        $parsed = Yaml::parse($yml);
        self::assertEquals($data, $parsed);
    }

    public function testZeroIndentationThrowsException()
    {
        self::expectException(\InvalidArgumentException::class);
        self::expectExceptionMessage('The indentation must be greater than zero');
        Yaml::dump(['lorem' => 'ipsum', 'dolor' => 'sit'], 2, 0);
    }

    public function testNegativeIndentationThrowsException()
    {
        self::expectException(\InvalidArgumentException::class);
        self::expectExceptionMessage('The indentation must be greater than zero');
        Yaml::dump(['lorem' => 'ipsum', 'dolor' => 'sit'], 2, -4);
    }
}
