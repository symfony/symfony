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

        if ('\\' !== \DIRECTORY_SEPARATOR) {
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
        $_ENV['REMOTE'] = 'remote';

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
            array('FOO="bar\nfoo"', array('FOO' => "bar\nfoo")),
            array('FOO="bar\rfoo"', array('FOO' => "bar\rfoo")),
            array('FOO=\'bar\nfoo\'', array('FOO' => 'bar\nfoo')),
            array('FOO=\'bar\rfoo\'', array('FOO' => 'bar\rfoo')),
            array('FOO=" FOO "', array('FOO' => ' FOO ')),
            array('FOO="  "', array('FOO' => '  ')),
            array('PATH="c:\\\\"', array('PATH' => 'c:\\')),
            array("FOO=\"bar\nfoo\"", array('FOO' => "bar\nfoo")),
            array('FOO=BAR\\"', array('FOO' => 'BAR"')),
            array("FOO=BAR\\'BAZ", array('FOO' => "BAR'BAZ")),
            array('FOO=\\"BAR', array('FOO' => '"BAR')),

            // concatenated values
            array("FOO='bar''foo'\n", array('FOO' => 'barfoo')),
            array("FOO='bar '' baz'", array('FOO' => 'bar  baz')),
            array("FOO=bar\nBAR='baz'\"\$FOO\"", array('FOO' => 'bar', 'BAR' => 'bazbar')),
            array("FOO='bar '\\'' baz'", array('FOO' => "bar ' baz")),

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
            array('BAR=$REMOTE', array('BAR' => 'remote')),
            array('FOO=$NOTDEFINED', array('FOO' => '')),
        );

        if ('\\' !== \DIRECTORY_SEPARATOR) {
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

    public function testLoad()
    {
        unset($_ENV['FOO']);
        unset($_ENV['BAR']);
        unset($_SERVER['FOO']);
        unset($_SERVER['BAR']);
        putenv('FOO');
        putenv('BAR');

        @mkdir($tmpdir = sys_get_temp_dir().'/dotenv');

        $path1 = tempnam($tmpdir, 'sf-');
        $path2 = tempnam($tmpdir, 'sf-');

        file_put_contents($path1, 'FOO=BAR');
        file_put_contents($path2, 'BAR=BAZ');

        (new Dotenv())->load($path1, $path2);

        $foo = getenv('FOO');
        $bar = getenv('BAR');

        putenv('FOO');
        putenv('BAR');
        unlink($path1);
        unlink($path2);
        rmdir($tmpdir);

        $this->assertSame('BAR', $foo);
        $this->assertSame('BAZ', $bar);
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

        $dotenv = new Dotenv();
        $dotenv->populate(array('argc' => 'new_value'));

        $this->assertSame($originalValue, $_SERVER['argc']);
    }

    public function testEnvVarIsNotOverriden()
    {
        putenv('TEST_ENV_VAR=original_value');
        $_SERVER['TEST_ENV_VAR'] = 'original_value';

        $dotenv = new Dotenv();
        $dotenv->populate(array('TEST_ENV_VAR' => 'new_value'));

        $this->assertSame('original_value', getenv('TEST_ENV_VAR'));
    }

    public function testHttpVarIsPartiallyOverriden()
    {
        $_SERVER['HTTP_TEST_ENV_VAR'] = 'http_value';

        $dotenv = new Dotenv();
        $dotenv->populate(array('HTTP_TEST_ENV_VAR' => 'env_value'));

        $this->assertSame('env_value', getenv('HTTP_TEST_ENV_VAR'));
        $this->assertSame('env_value', $_ENV['HTTP_TEST_ENV_VAR']);
        $this->assertSame('http_value', $_SERVER['HTTP_TEST_ENV_VAR']);
    }

    public function testMemorizingLoadedVarsNamesInSpecialVar()
    {
        // Special variable not exists
        unset($_ENV['SYMFONY_DOTENV_VARS']);
        unset($_SERVER['SYMFONY_DOTENV_VARS']);
        putenv('SYMFONY_DOTENV_VARS');

        unset($_ENV['APP_DEBUG']);
        unset($_SERVER['APP_DEBUG']);
        putenv('APP_DEBUG');
        unset($_ENV['DATABASE_URL']);
        unset($_SERVER['DATABASE_URL']);
        putenv('DATABASE_URL');

        $dotenv = new Dotenv();
        $dotenv->populate(array('APP_DEBUG' => '1', 'DATABASE_URL' => 'mysql://root@localhost/db'));

        $this->assertSame('APP_DEBUG,DATABASE_URL', getenv('SYMFONY_DOTENV_VARS'));

        // Special variable has a value
        $_ENV['SYMFONY_DOTENV_VARS'] = 'APP_ENV';
        $_SERVER['SYMFONY_DOTENV_VARS'] = 'APP_ENV';
        putenv('SYMFONY_DOTENV_VARS=APP_ENV');

        $_ENV['APP_DEBUG'] = '1';
        $_SERVER['APP_DEBUG'] = '1';
        putenv('APP_DEBUG=1');
        unset($_ENV['DATABASE_URL']);
        unset($_SERVER['DATABASE_URL']);
        putenv('DATABASE_URL');

        $dotenv = new Dotenv();
        $dotenv->populate(array('APP_DEBUG' => '0', 'DATABASE_URL' => 'mysql://root@localhost/db'));
        $dotenv->populate(array('DATABASE_URL' => 'sqlite:///somedb.sqlite'));

        $this->assertSame('APP_ENV,DATABASE_URL', getenv('SYMFONY_DOTENV_VARS'));
    }

    public function testOverridingEnvVarsWithNamesMemorizedInSpecialVar()
    {
        putenv('SYMFONY_DOTENV_VARS=FOO,BAR,BAZ');

        putenv('FOO=foo');
        putenv('BAR=bar');
        putenv('BAZ=baz');
        putenv('DOCUMENT_ROOT=/var/www');

        $dotenv = new Dotenv();
        $dotenv->populate(array('FOO' => 'foo1', 'BAR' => 'bar1', 'BAZ' => 'baz1', 'DOCUMENT_ROOT' => '/boot'));

        $this->assertSame('foo1', getenv('FOO'));
        $this->assertSame('bar1', getenv('BAR'));
        $this->assertSame('baz1', getenv('BAZ'));
        $this->assertSame('/var/www', getenv('DOCUMENT_ROOT'));
    }
}
