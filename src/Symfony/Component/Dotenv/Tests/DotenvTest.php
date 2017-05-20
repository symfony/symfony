<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Dotenv\Tests;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Dotenv\Dotenv;
use Symfony\Component\Dotenv\Exception\FormatException;

class DotenvTest extends TestCase
{
    /**
     * @dataProvider getEnvDataWithFormatErrors
     */
    public function testParseWithFormatError($data, $error)
    {
        $dotenv = new Dotenv();

        try {
            $dotenv->parse($data);
            $this->fail('Should throw a FormatException');
        } catch (FormatException $e) {
            $this->assertStringMatchesFormat($error, $e->getMessage());
        }
    }

    public function getEnvDataWithFormatErrors()
    {
        $tests = array(
            array('FOO=BAR BAZ', "A value containing spaces must be surrounded by quotes in \".env\" at line 1.\n...FOO=BAR BAZ...\n             ^ line 1 offset 11"),
            array('FOO BAR=BAR', "Whitespace are not supported after the variable name in \".env\" at line 1.\n...FOO BAR=BAR...\n     ^ line 1 offset 3"),
            array('FOO', "Missing = in the environment variable declaration in \".env\" at line 1.\n...FOO...\n     ^ line 1 offset 3"),
            array('FOO="foo', "Missing quote to end the value in \".env\" at line 1.\n...FOO=\"foo...\n          ^ line 1 offset 8"),
            array('FOO=\'foo', "Missing quote to end the value in \".env\" at line 1.\n...FOO='foo...\n          ^ line 1 offset 8"),
            array('export FOO', "Unable to unset an environment variable in \".env\" at line 1.\n...export FOO...\n            ^ line 1 offset 10"),
            array('FOO=${FOO', "Unclosed braces on variable expansion in \".env\" at line 1.\n...FOO=\${FOO...\n           ^ line 1 offset 9"),
        );

        if ('\\' !== DIRECTORY_SEPARATOR) {
            $tests[] = array('FOO=$((1dd2))', "Issue expanding a command (%s\n) in \".env\" at line 1.\n...FOO=$((1dd2))...\n               ^ line 1 offset 13");
        }

        return $tests;
    }

    /**
     * @dataProvider getEnvData
     */
    public function testParse($data, $expected)
    {
        $dotenv = new Dotenv();
        $this->assertSame($expected, $dotenv->parse($data));
    }

    public function getEnvData()
    {
        putenv('LOCAL=local');

        $tests = array(
            // spaces
            array('FOO=bar', array('FOO' => 'bar')),
            array(' FOO=bar ', array('FOO' => 'bar')),
            array('FOO=', array('FOO' => '')),
            array("FOO=\n\n\nBAR=bar", array('FOO' => '', 'BAR' => 'bar')),
            array('FOO=  ', array('FOO' => '')),
            array("FOO=\nBAR=bar", array('FOO' => '', 'BAR' => 'bar')),

            // newlines
            array("\n\nFOO=bar\r\n\n", array('FOO' => 'bar')),
            array("FOO=bar\r\nBAR=foo", array('FOO' => 'bar', 'BAR' => 'foo')),
            array("FOO=bar\rBAR=foo", array('FOO' => 'bar', 'BAR' => 'foo')),
            array("FOO=bar\nBAR=foo", array('FOO' => 'bar', 'BAR' => 'foo')),

            // quotes
            array("FOO=\"bar\"\n", array('FOO' => 'bar')),
            array("FOO=\"bar'foo\"\n", array('FOO' => 'bar\'foo')),
            array("FOO='bar'\n", array('FOO' => 'bar')),
            array("FOO='bar\"foo'\n", array('FOO' => 'bar"foo')),
            array("FOO=\"bar\\\"foo\"\n", array('FOO' => 'bar"foo')),
            array("FOO='bar''foo'\n", array('FOO' => 'bar\'foo')),
            array('FOO="bar\nfoo"', array('FOO' => "bar\nfoo")),
            array('FOO="bar\rfoo"', array('FOO' => "bar\rfoo")),
            array('FOO=\'bar\nfoo\'', array('FOO' => 'bar\nfoo')),
            array('FOO=\'bar\rfoo\'', array('FOO' => 'bar\rfoo')),
            array('FOO=" FOO "', array('FOO' => ' FOO ')),
            array('FOO="  "', array('FOO' => '  ')),
            array('PATH="c:\\\\"', array('PATH' => 'c:\\')),
            array("FOO=\"bar\nfoo\"", array('FOO' => "bar\nfoo")),

            // concatenated values

            // comments
            array("#FOO=bar\nBAR=foo", array('BAR' => 'foo')),
            array("#FOO=bar # Comment\nBAR=foo", array('BAR' => 'foo')),
            array("FOO='bar foo' # Comment", array('FOO' => 'bar foo')),
            array("FOO='bar#foo' # Comment", array('FOO' => 'bar#foo')),
            array("# Comment\r\nFOO=bar\n# Comment\nBAR=foo", array('FOO' => 'bar', 'BAR' => 'foo')),
            array("FOO=bar # Another comment\nBAR=foo", array('FOO' => 'bar', 'BAR' => 'foo')),
            array("FOO=\n\n# comment\nBAR=bar", array('FOO' => '', 'BAR' => 'bar')),
            array('FOO=NOT#COMMENT', array('FOO' => 'NOT#COMMENT')),
            array('FOO=  # Comment', array('FOO' => '')),

            // edge cases (no conversions, only strings as values)
            array('FOO=0', array('FOO' => '0')),
            array('FOO=false', array('FOO' => 'false')),
            array('FOO=null', array('FOO' => 'null')),

            // export
            array('export FOO=bar', array('FOO' => 'bar')),
            array('  export   FOO=bar', array('FOO' => 'bar')),

            // variable expansion
            array("FOO=BAR\nBAR=\$FOO", array('FOO' => 'BAR', 'BAR' => 'BAR')),
            array("FOO=BAR\nBAR=\"\$FOO\"", array('FOO' => 'BAR', 'BAR' => 'BAR')),
            array("FOO=BAR\nBAR='\$FOO'", array('FOO' => 'BAR', 'BAR' => '$FOO')),
            array("FOO_BAR9=BAR\nBAR=\$FOO_BAR9", array('FOO_BAR9' => 'BAR', 'BAR' => 'BAR')),
            array("FOO=BAR\nBAR=\${FOO}Z", array('FOO' => 'BAR', 'BAR' => 'BARZ')),
            array("FOO=BAR\nBAR=\$FOO}", array('FOO' => 'BAR', 'BAR' => 'BAR}')),
            array("FOO=BAR\nBAR=\\\$FOO", array('FOO' => 'BAR', 'BAR' => '$FOO')),
            array('FOO=" \\$ "', array('FOO' => ' $ ')),
            array('FOO=" $ "', array('FOO' => ' $ ')),
            array('BAR=$LOCAL', array('BAR' => 'local')),
            array('FOO=$NOTDEFINED', array('FOO' => '')),
        );

        if ('\\' !== DIRECTORY_SEPARATOR) {
            $tests = array_merge($tests, array(
                // command expansion
                array('FOO=$(echo foo)', array('FOO' => 'foo')),
                array('FOO=$((1+2))', array('FOO' => '3')),
                array('FOO=FOO$((1+2))BAR', array('FOO' => 'FOO3BAR')),
                array('FOO=$(echo "$(echo "$(echo "$(echo foo)")")")', array('FOO' => 'foo')),
                array("FOO=$(echo \"Quotes won't be a problem\")", array('FOO' => 'Quotes won\'t be a problem')),
                array("FOO=bar\nBAR=$(echo \"FOO is \$FOO\")", array('FOO' => 'bar', 'BAR' => 'FOO is bar')),
            ));
        }

        return $tests;
    }

    /**
     * @expectedException \Symfony\Component\Dotenv\Exception\PathException
     */
    public function testLoadDirectory()
    {
        $dotenv = new Dotenv();
        $dotenv->load(__DIR__);
    }

    public function testServerSuperglobalIsNotOverriden()
    {
        $originalValue = $_SERVER['argc'];

        $dotenv = new DotEnv();
        $dotenv->populate(array('argc' => 'new_value'));

        $this->assertSame($originalValue, $_SERVER['argc']);
    }

    public function testEnvVarIsNotOverriden()
    {
        putenv('TEST_ENV_VAR=original_value');

        $dotenv = new DotEnv();
        $dotenv->populate(array('TEST_ENV_VAR' => 'new_value'));

        $this->assertSame('original_value', getenv('TEST_ENV_VAR'));
    }
}
