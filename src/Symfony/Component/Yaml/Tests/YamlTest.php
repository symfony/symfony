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

    public function testParseWithMultilineQuotes()
    {
        $yaml = <<<YAML
foo:
  bar: 'baz
biz

'
  baz: 'Lorem

       ipsum'
  error: Une erreur s'est produite.
  trialMode: 'période d''essai'
  double_line: 'Les utilisateurs sélectionnés
n''ont pas d''email.

'
  a: 'b''
c'
  empty: ''
  foo:bar: 'foobar'
YAML;

        $this->assertSame(['foo' => [
            'bar' => "baz biz\n",
            'baz' => "Lorem\nipsum",
            'error' => "Une erreur s'est produite.",
            'trialMode' => "période d'essai",
            'double_line' => "Les utilisateurs sélectionnés n'ont pas d'email.\n",
            'a' => "b' c",
            'empty' => '',
            'foo:bar' => 'foobar',
        ]], Yaml::parse($yaml));
    }

    public function testParseWithMultilineQuotesExpectException()
    {
        $yaml = <<<YAML
foo:
  bar: 'baz

'
'
YAML;

        $this->expectException(ParseException::class);
        $this->expectExceptionMessage('Unable to parse at line 5 (near "\'").');
        Yaml::parse($yaml);
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
}
