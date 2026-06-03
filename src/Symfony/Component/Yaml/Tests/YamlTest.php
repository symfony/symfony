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
use Symfony\Component\Yaml\Exception\ParseException;
use Symfony\Component\Yaml\Parser;
use Symfony\Component\Yaml\Yaml;

class YamlTest extends TestCase
{
    public function testParseAndDump()
    {
        $data = ['lorem' => 'ipsum', 'dolor' => 'sit'];
        $yml = Yaml::dump($data);
        $parsed = Yaml::parse($yml);
        $this->assertEquals($data, $parsed);
    }

    public function testZeroIndentationThrowsException()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('The indentation must be greater than zero');
        Yaml::dump(['lorem' => 'ipsum', 'dolor' => 'sit'], 2, 0);
    }

    public function testNegativeIndentationThrowsException()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('The indentation must be greater than zero');
        Yaml::dump(['lorem' => 'ipsum', 'dolor' => 'sit'], 2, -4);
    }

    public function testParseAllowsConfiguringTheMaximumNestingLevel()
    {
        $yaml = "root:\n  child:\n    grandchild:\n      greatgrandchild: value\n";

        $this->expectException(ParseException::class);
        $this->expectExceptionMessage('Maximum nesting depth of 2 exceeded');

        Yaml::parse($yaml, 0, 2);
    }

    public function testParseFileAllowsConfiguringTheMaximumCollectionAliasCount()
    {
        $file = tempnam(sys_get_temp_dir(), 'yaml_');

        file_put_contents($file, <<<YAML
            defaults: &defaults [foo, bar]
            copy: *defaults
            YAML
        );

        $this->expectException(ParseException::class);
        $this->expectExceptionMessage('Maximum number of collection aliases (0) exceeded.');

        try {
            Yaml::parseFile($file, 0, Parser::DEFAULT_MAX_NESTING_LEVEL, 0);
        } finally {
            unlink($file);
        }
    }
}
