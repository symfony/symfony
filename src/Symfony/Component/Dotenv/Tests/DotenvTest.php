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
        $tests = [
            ['FOO=BAR BAZ', "A value containing spaces must be surrounded by quotes in \".env\" at line 1.\n...FOO=BAR BAZ...\n             ^ line 1 offset 11"],
            ['FOO BAR=BAR', "Whitespace characters are not supported after the variable name in \".env\" at line 1.\n...FOO BAR=BAR...\n     ^ line 1 offset 3"],
            ['FOO', "Missing = in the environment variable declaration in \".env\" at line 1.\n...FOO...\n     ^ line 1 offset 3"],
            ['FOO="foo', "Missing quote to end the value in \".env\" at line 1.\n...FOO=\"foo...\n          ^ line 1 offset 8"],
            ['FOO=\'foo', "Missing quote to end the value in \".env\" at line 1.\n...FOO='foo...\n          ^ line 1 offset 8"],
            ["FOO=\"foo\nBAR=\"bar\"", "Missing quote to end the value in \".env\" at line 1.\n...FOO=\"foo\\nBAR=\"bar\"...\n                     ^ line 1 offset 18"],
            ['FOO=\'foo'."\n", "Missing quote to end the value in \".env\" at line 1.\n...FOO='foo\\n...\n            ^ line 1 offset 9"],
            ['export FOO', "Unable to unset an environment variable in \".env\" at line 1.\n...export FOO...\n            ^ line 1 offset 10"],
            ['FOO=${FOO', "Unclosed braces on variable expansion in \".env\" at line 1.\n...FOO=\${FOO...\n           ^ line 1 offset 9"],
        ];

        if ('\\' !== \DIRECTORY_SEPARATOR) {
            $tests[] = ['FOO=$((1dd2))', "Issue expanding a command (%s\n) in \".env\" at line 1.\n...FOO=$((1dd2))...\n               ^ line 1 offset 13"];
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
        $_ENV['LOCAL'] = 'local';
        $_ENV['REMOTE'] = 'remote';

        $tests = [
            // backslashes
            ['FOO=foo\\\\bar', ['FOO' => 'foo\\bar']],
            ["FOO='foo\\\\bar'", ['FOO' => 'foo\\\\bar']],
            ['FOO="foo\\\\bar"', ['FOO' => 'foo\\bar']],

            // escaped backslash in front of variable
            ["BAR=bar\nFOO=foo\\\\\$BAR", ['BAR' => 'bar', 'FOO' => 'foo\\bar']],
            ["BAR=bar\nFOO='foo\\\\\$BAR'", ['BAR' => 'bar', 'FOO' => 'foo\\\\$BAR']],
            ["BAR=bar\nFOO=\"foo\\\\\$BAR\"", ['BAR' => 'bar', 'FOO' => 'foo\\bar']],

            ['FOO=foo\\\\\\$BAR', ['FOO' => 'foo\\$BAR']],
            ['FOO=\'foo\\\\\\$BAR\'', ['FOO' => 'foo\\\\\\$BAR']],
            ['FOO="foo\\\\\\$BAR"', ['FOO' => 'foo\\$BAR']],

            // spaces
            ['FOO=bar', ['FOO' => 'bar']],
            [' FOO=bar ', ['FOO' => 'bar']],
            ['FOO=', ['FOO' => '']],
            ["FOO=\n\n\nBAR=bar", ['FOO' => '', 'BAR' => 'bar']],
            ['FOO=  ', ['FOO' => '']],
            ["FOO=\nBAR=bar", ['FOO' => '', 'BAR' => 'bar']],

            // newlines
            ["\n\nFOO=bar\r\n\n", ['FOO' => 'bar']],
            ["FOO=bar\r\nBAR=foo", ['FOO' => 'bar', 'BAR' => 'foo']],
            ["FOO=bar\rBAR=foo", ['FOO' => 'bar', 'BAR' => 'foo']],
            ["FOO=bar\nBAR=foo", ['FOO' => 'bar', 'BAR' => 'foo']],

            // quotes
            ["FOO=\"bar\"\n", ['FOO' => 'bar']],
            ["FOO=\"bar'foo\"\n", ['FOO' => 'bar\'foo']],
            ["FOO='bar'\n", ['FOO' => 'bar']],
            ["FOO='bar\"foo'\n", ['FOO' => 'bar"foo']],
            ["FOO=\"bar\\\"foo\"\n", ['FOO' => 'bar"foo']],
            ['FOO="bar\nfoo"', ['FOO' => "bar\nfoo"]],
            ['FOO="bar\rfoo"', ['FOO' => "bar\rfoo"]],
            ['FOO=\'bar\nfoo\'', ['FOO' => 'bar\nfoo']],
            ['FOO=\'bar\rfoo\'', ['FOO' => 'bar\rfoo']],
            ["FOO='bar\nfoo'", ['FOO' => "bar\nfoo"]],
            ['FOO=" FOO "', ['FOO' => ' FOO ']],
            ['FOO="  "', ['FOO' => '  ']],
            ['PATH="c:\\\\"', ['PATH' => 'c:\\']],
            ["FOO=\"bar\nfoo\"", ['FOO' => "bar\nfoo"]],
            ['FOO=BAR\\"', ['FOO' => 'BAR"']],
            ["FOO=BAR\\'BAZ", ['FOO' => "BAR'BAZ"]],
            ['FOO=\\"BAR', ['FOO' => '"BAR']],

            // concatenated values
            ["FOO='bar''foo'\n", ['FOO' => 'barfoo']],
            ["FOO='bar '' baz'", ['FOO' => 'bar  baz']],
            ["FOO=bar\nBAR='baz'\"\$FOO\"", ['FOO' => 'bar', 'BAR' => 'bazbar']],
            ["FOO='bar '\\'' baz'", ['FOO' => "bar ' baz"]],

            // comments
            ["#FOO=bar\nBAR=foo", ['BAR' => 'foo']],
            ["#FOO=bar # Comment\nBAR=foo", ['BAR' => 'foo']],
            ["FOO='bar foo' # Comment", ['FOO' => 'bar foo']],
            ["FOO='bar#foo' # Comment", ['FOO' => 'bar#foo']],
            ["# Comment\r\nFOO=bar\n# Comment\nBAR=foo", ['FOO' => 'bar', 'BAR' => 'foo']],
            ["FOO=bar # Another comment\nBAR=foo", ['FOO' => 'bar', 'BAR' => 'foo']],
            ["FOO=\n\n# comment\nBAR=bar", ['FOO' => '', 'BAR' => 'bar']],
            ['FOO=NOT#COMMENT', ['FOO' => 'NOT#COMMENT']],
            ['FOO=  # Comment', ['FOO' => '']],

            // edge cases (no conversions, only strings as values)
            ['FOO=0', ['FOO' => '0']],
            ['FOO=false', ['FOO' => 'false']],
            ['FOO=null', ['FOO' => 'null']],

            // export
            ['export FOO=bar', ['FOO' => 'bar']],
            ['  export   FOO=bar', ['FOO' => 'bar']],

            // variable expansion
            ["FOO=BAR\nBAR=\$FOO", ['FOO' => 'BAR', 'BAR' => 'BAR']],
            ["FOO=BAR\nBAR=\"\$FOO\"", ['FOO' => 'BAR', 'BAR' => 'BAR']],
            ["FOO=BAR\nBAR='\$FOO'", ['FOO' => 'BAR', 'BAR' => '$FOO']],
            ["FOO_BAR9=BAR\nBAR=\$FOO_BAR9", ['FOO_BAR9' => 'BAR', 'BAR' => 'BAR']],
            ["FOO=BAR\nBAR=\${FOO}Z", ['FOO' => 'BAR', 'BAR' => 'BARZ']],
            ["FOO=BAR\nBAR=\$FOO}", ['FOO' => 'BAR', 'BAR' => 'BAR}']],
            ["FOO=BAR\nBAR=\\\$FOO", ['FOO' => 'BAR', 'BAR' => '$FOO']],
            ['FOO=" \\$ "', ['FOO' => ' $ ']],
            ['FOO=" $ "', ['FOO' => ' $ ']],
            ['BAR=$LOCAL', ['BAR' => 'local']],
            ['BAR=$REMOTE', ['BAR' => 'remote']],
            ['FOO=$NOTDEFINED', ['FOO' => '']],
        ];

        if ('\\' !== \DIRECTORY_SEPARATOR) {
            $tests = array_merge($tests, [
                // command expansion
                ['FOO=$(echo foo)', ['FOO' => 'foo']],
                ['FOO=$((1+2))', ['FOO' => '3']],
                ['FOO=FOO$((1+2))BAR', ['FOO' => 'FOO3BAR']],
                ['FOO=$(echo "$(echo "$(echo "$(echo foo)")")")', ['FOO' => 'foo']],
                ["FOO=$(echo \"Quotes won't be a problem\")", ['FOO' => 'Quotes won\'t be a problem']],
                ["FOO=bar\nBAR=$(echo \"FOO is \$FOO\")", ['FOO' => 'bar', 'BAR' => 'FOO is bar']],
            ]);
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

    public function testLoadDirectory()
    {
        $this->expectException('Symfony\Component\Dotenv\Exception\PathException');
        $dotenv = new Dotenv();
        $dotenv->load(__DIR__);
    }

    public function testServerSuperglobalIsNotOverridden()
    {
        $originalValue = $_SERVER['argc'];

        $dotenv = new Dotenv();
        $dotenv->populate(['argc' => 'new_value']);

        $this->assertSame($originalValue, $_SERVER['argc']);
    }

    public function testEnvVarIsNotOverridden()
    {
        putenv('TEST_ENV_VAR=original_value');
        $_SERVER['TEST_ENV_VAR'] = 'original_value';

        $dotenv = new Dotenv();
        $dotenv->populate(['TEST_ENV_VAR' => 'new_value']);

        $this->assertSame('original_value', getenv('TEST_ENV_VAR'));
    }

    public function testHttpVarIsPartiallyOverridden()
    {
        $_SERVER['HTTP_TEST_ENV_VAR'] = 'http_value';

        $dotenv = new Dotenv();
        $dotenv->populate(['HTTP_TEST_ENV_VAR' => 'env_value']);

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
        $dotenv->populate(['APP_DEBUG' => '1', 'DATABASE_URL' => 'mysql://root@localhost/db']);

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
        $dotenv->populate(['APP_DEBUG' => '0', 'DATABASE_URL' => 'mysql://root@localhost/db']);
        $dotenv->populate(['DATABASE_URL' => 'sqlite:///somedb.sqlite']);

        $this->assertSame('APP_ENV,DATABASE_URL', getenv('SYMFONY_DOTENV_VARS'));
    }

    public function testOverridingEnvVarsWithNamesMemorizedInSpecialVar()
    {
        putenv('SYMFONY_DOTENV_VARS='.$_SERVER['SYMFONY_DOTENV_VARS'] = 'FOO,BAR,BAZ');

        putenv('FOO=foo');
        putenv('BAR=bar');
        putenv('BAZ=baz');
        putenv('DOCUMENT_ROOT=/var/www');

        $dotenv = new Dotenv();
        $dotenv->populate(['FOO' => 'foo1', 'BAR' => 'bar1', 'BAZ' => 'baz1', 'DOCUMENT_ROOT' => '/boot']);

        $this->assertSame('foo1', getenv('FOO'));
        $this->assertSame('bar1', getenv('BAR'));
        $this->assertSame('baz1', getenv('BAZ'));
        $this->assertSame('/var/www', getenv('DOCUMENT_ROOT'));
    }

    public function testGetVariablesValueFromEnvFirst()
    {
        $_ENV['APP_ENV'] = 'prod';
        $dotenv = new Dotenv(true);

        $test = "APP_ENV=dev\nTEST1=foo1_\${APP_ENV}";
        $values = $dotenv->parse($test);
        $this->assertSame('foo1_prod', $values['TEST1']);

        if ('\\' !== \DIRECTORY_SEPARATOR) {
            $test = "APP_ENV=dev\nTEST2=foo2_\$(php -r 'echo \$_SERVER[\"APP_ENV\"];')";
            $values = $dotenv->parse($test);
            $this->assertSame('foo2_prod', $values['TEST2']);
        }
    }

    public function testGetVariablesValueFromGetenv()
    {
        putenv('Foo=Bar');

        $dotenv = new Dotenv(true);

        try {
            $values = $dotenv->parse('Foo=${Foo}');
            $this->assertSame('Bar', $values['Foo']);
        } finally {
            putenv('Foo');
        }
    }
}
