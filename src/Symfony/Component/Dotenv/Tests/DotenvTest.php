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

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Dotenv\Dotenv;
use Symfony\Component\Dotenv\Exception\FormatException;
use Symfony\Component\Dotenv\Exception\PathException;
use Symfony\Component\Dotenv\Exception\VariableCircularReferenceException;

class DotenvTest extends TestCase
{
    #[DataProvider('getEnvDataWithFormatErrors')]
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

    public static function getEnvDataWithFormatErrors()
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
            ['FOO= BAR', "Whitespace are not supported before the value in \".env\" at line 1.\n...FOO= BAR...\n      ^ line 1 offset 4"],
            ['Стасян', "Invalid character in variable name in \".env\" at line 1.\n...Стасян...\n  ^ line 1 offset 0"],
            ['FOO!', "Missing = in the environment variable declaration in \".env\" at line 1.\n...FOO!...\n     ^ line 1 offset 3"],
            ['FOO=$(echo foo', "Missing closing parenthesis. in \".env\" at line 1.\n...FOO=$(echo foo...\n                ^ line 1 offset 14"],
            ['FOO=$(echo foo'."\n", "Missing closing parenthesis. in \".env\" at line 1.\n...FOO=$(echo foo\\n...\n                ^ line 1 offset 14"],
            ["FOO=\nBAR=\${FOO:-\'a{a}a}", "Unsupported character \"'\" found in the default value of variable \"\$FOO\". in \".env\" at line 2.\n...\\nBAR=\${FOO:-\'a{a}a}...\n                       ^ line 2 offset 24"],
            ["FOO=\nBAR=\${FOO:-a\$a}", "Unsupported character \"\$\" found in the default value of variable \"\$FOO\". in \".env\" at line 2.\n...FOO=\\nBAR=\${FOO:-a\$a}...\n                       ^ line 2 offset 20"],
            ["FOO=\nBAR=\${FOO:-a\"a}", "Missing quote to end the value in \".env\" at line 2.\n...FOO=\\nBAR=\${FOO:-a\"a}...\n                       ^ line 2 offset 20"],
            ['_=FOO', "Invalid character in variable name in \".env\" at line 1.\n..._=FOO...\n  ^ line 1 offset 0"],
        ];

        if ('\\' !== \DIRECTORY_SEPARATOR) {
            $tests[] = ['FOO=$((1dd2))', "Issue expanding a command (%s\n) in \".env\" at line 1.\n...FOO=$((1dd2))...\n               ^ line 1 offset 13"];
        }

        return $tests;
    }

    #[DataProvider('getEnvData')]
    public function testParse($data, $expected)
    {
        $dotenv = new Dotenv();
        $this->assertSame($expected, $dotenv->parse($data));
    }

    public static function getEnvData()
    {
        putenv('LOCAL=local');
        $_ENV['LOCAL'] = 'local';
        $_ENV['REMOTE'] = 'remote';
        $_SERVER['SERVERVAR'] = 'servervar';

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
            ['BAR=$SERVERVAR', ['BAR' => 'servervar']],
            ['FOO=$NOTDEFINED', ['FOO' => '']],
            ["FOO=BAR\nBAR=\${FOO:-TEST}", ['FOO' => 'BAR', 'BAR' => 'BAR']],
            ["FOO=BAR\nBAR=\${NOTDEFINED:-TEST}", ['FOO' => 'BAR', 'BAR' => 'TEST']],
            ["FOO=\nBAR=\${FOO:-TEST}", ['FOO' => '', 'BAR' => 'TEST']],
            ["FOO=\nBAR=\$FOO:-TEST}", ['FOO' => '', 'BAR' => 'TEST}']],
            ["FOO=BAR\nBAR=\${FOO:=TEST}", ['FOO' => 'BAR', 'BAR' => 'BAR']],
            ["FOO=BAR\nBAR=\${NOTDEFINED:=TEST}", ['FOO' => 'BAR', 'NOTDEFINED' => 'TEST', 'BAR' => 'TEST']],
            ["FOO=\nBAR=\${FOO:=TEST}", ['FOO' => 'TEST', 'BAR' => 'TEST']],
            ["FOO=\nBAR=\$FOO:=TEST}", ['FOO' => 'TEST', 'BAR' => 'TEST}']],
            ["FOO=BAR\nBAR=\${FOO:-}", ['FOO' => 'BAR', 'BAR' => 'BAR']],
            ["FOO=BAR\nBAR=\${NOTDEFINED:-}", ['FOO' => 'BAR', 'BAR' => '']],
            ["FOO=\nBAR=\${FOO:-}", ['FOO' => '', 'BAR' => '']],
            ["FOO=\nBAR=\$FOO:-}", ['FOO' => '', 'BAR' => '}']],
            ["FOO=BAR\nBAR=\${FOO:=}", ['FOO' => 'BAR', 'BAR' => 'BAR']],
            ["FOO=BAR\nBAR=\${NOTDEFINED:=}", ['FOO' => 'BAR', 'NOTDEFINED' => '', 'BAR' => '']],
            ["FOO=\nBAR=\${FOO:=}", ['FOO' => '', 'BAR' => '']],
            ["FOO=\nBAR=\$FOO:=}", ['FOO' => '', 'BAR' => '}']],
            ["FOO=foo\nFOOBAR=\${FOO}\${BAR}", ['FOO' => 'foo', 'FOOBAR' => 'foo']],

            // underscores
            ['_FOO=BAR', ['_FOO' => 'BAR']],
            ['_FOO_BAR=FOOBAR', ['_FOO_BAR' => 'FOOBAR']],
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

        (new Dotenv())->usePutenv()->load($path1, $path2);

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

    public function testLoadDoesNotReResolveAlreadyLoadedVars()
    {
        unset($_ENV['FOO'], $_ENV['BAR'], $_ENV['SYMFONY_DOTENV_VARS']);
        unset($_SERVER['FOO'], $_SERVER['BAR'], $_SERVER['SYMFONY_DOTENV_VARS']);
        putenv('FOO');
        putenv('BAR');
        putenv('SYMFONY_DOTENV_VARS');

        @mkdir($tmpdir = sys_get_temp_dir().'/dotenv');

        $path1 = tempnam($tmpdir, 'sf-');
        $path2 = tempnam($tmpdir, 'sf-');

        file_put_contents($path1, "FOO='This\$isokay'");
        file_put_contents($path2, "BAR='hello'");

        try {
            (new Dotenv())->load($path1);
            $this->assertSame('This$isokay', $_ENV['FOO']);

            (new Dotenv())->load($path2);
            $this->assertSame('This$isokay', $_ENV['FOO']);
            $this->assertSame('hello', $_ENV['BAR']);
        } finally {
            unset($_ENV['FOO'], $_ENV['BAR'], $_ENV['SYMFONY_DOTENV_VARS']);
            unset($_SERVER['FOO'], $_SERVER['BAR'], $_SERVER['SYMFONY_DOTENV_VARS']);
            putenv('FOO');
            putenv('BAR');
            putenv('SYMFONY_DOTENV_VARS');
            unlink($path1);
            unlink($path2);
            rmdir($tmpdir);
        }
    }

    public function testLoadEnv()
    {
        $resetContext = static function (): void {
            unset($_ENV['SYMFONY_DOTENV_VARS']);
            unset($_ENV['FOO']);
            unset($_ENV['TEST_APP_ENV']);
            unset($_SERVER['SYMFONY_DOTENV_VARS']);
            unset($_SERVER['FOO']);
            unset($_SERVER['TEST_APP_ENV']);
            putenv('SYMFONY_DOTENV_VARS');
            putenv('FOO');
            putenv('TEST_APP_ENV');

            $_ENV['EXISTING_KEY'] = $_SERVER['EXISTING_KEY'] = 'EXISTING_VALUE';
            putenv('EXISTING_KEY=EXISTING_VALUE');
        };

        @mkdir($tmpdir = sys_get_temp_dir().'/dotenv');

        $path = tempnam($tmpdir, 'sf-');

        // .env
        file_put_contents($path, "FOO=BAR\nEXISTING_KEY=NEW_VALUE");

        $resetContext();
        (new Dotenv())->usePutenv()->loadEnv($path, 'TEST_APP_ENV');
        $this->assertSame('BAR', getenv('FOO'));
        $this->assertSame('dev', getenv('TEST_APP_ENV'));
        $this->assertSame('EXISTING_VALUE', getenv('EXISTING_KEY'));
        $this->assertSame('EXISTING_VALUE', $_ENV['EXISTING_KEY']);

        $resetContext();
        (new Dotenv())->usePutenv()->loadEnv($path, 'TEST_APP_ENV', 'dev', ['test'], true);
        $this->assertSame('BAR', getenv('FOO'));
        $this->assertSame('dev', getenv('TEST_APP_ENV'));
        $this->assertSame('NEW_VALUE', getenv('EXISTING_KEY'));
        $this->assertSame('NEW_VALUE', $_ENV['EXISTING_KEY']);

        // .env.local
        file_put_contents("$path.local", "FOO=localBAR\nEXISTING_KEY=localNEW_VALUE");

        $resetContext();
        $_SERVER['TEST_APP_ENV'] = 'local';
        (new Dotenv())->usePutenv()->loadEnv($path, 'TEST_APP_ENV');
        $this->assertSame('localBAR', getenv('FOO'));
        $this->assertSame('EXISTING_VALUE', getenv('EXISTING_KEY'));
        $this->assertSame('EXISTING_VALUE', $_ENV['EXISTING_KEY']);

        $resetContext();
        $_SERVER['TEST_APP_ENV'] = 'local';
        (new Dotenv())->usePutenv()->loadEnv($path, 'TEST_APP_ENV', 'dev', ['test'], true);
        $this->assertSame('localBAR', getenv('FOO'));
        $this->assertSame('localNEW_VALUE', getenv('EXISTING_KEY'));
        $this->assertSame('localNEW_VALUE', $_ENV['EXISTING_KEY']);

        // special case for test
        $resetContext();
        $_SERVER['TEST_APP_ENV'] = 'test';
        (new Dotenv())->usePutenv()->loadEnv($path, 'TEST_APP_ENV');
        $this->assertSame('BAR', getenv('FOO'));
        $this->assertSame('EXISTING_VALUE', getenv('EXISTING_KEY'));
        $this->assertSame('EXISTING_VALUE', $_ENV['EXISTING_KEY']);

        $resetContext();
        $_SERVER['TEST_APP_ENV'] = 'test';
        (new Dotenv())->usePutenv()->loadEnv($path, 'TEST_APP_ENV', 'dev', ['test'], true);
        $this->assertSame('BAR', getenv('FOO'));
        $this->assertSame('NEW_VALUE', getenv('EXISTING_KEY'));
        $this->assertSame('NEW_VALUE', $_ENV['EXISTING_KEY']);

        // .env.dev
        file_put_contents("$path.dev", "FOO=devBAR\nEXISTING_KEY=devNEW_VALUE");

        $resetContext();
        (new Dotenv())->usePutenv()->loadEnv($path, 'TEST_APP_ENV');
        $this->assertSame('devBAR', getenv('FOO'));
        $this->assertSame('EXISTING_VALUE', getenv('EXISTING_KEY'));
        $this->assertSame('EXISTING_VALUE', $_ENV['EXISTING_KEY']);

        $resetContext();
        (new Dotenv())->usePutenv()->loadEnv($path, 'TEST_APP_ENV', 'dev', ['test'], true);
        $this->assertSame('devBAR', getenv('FOO'));
        $this->assertSame('devNEW_VALUE', getenv('EXISTING_KEY'));
        $this->assertSame('devNEW_VALUE', $_ENV['EXISTING_KEY']);

        // .env.dev.local
        file_put_contents("$path.dev.local", "FOO=devlocalBAR\nEXISTING_KEY=devlocalNEW_VALUE");

        $resetContext();
        (new Dotenv())->usePutenv()->loadEnv($path, 'TEST_APP_ENV');
        $this->assertSame('devlocalBAR', getenv('FOO'));
        $this->assertSame('EXISTING_VALUE', getenv('EXISTING_KEY'));
        $this->assertSame('EXISTING_VALUE', $_ENV['EXISTING_KEY']);

        $resetContext();
        (new Dotenv())->usePutenv()->loadEnv($path, 'TEST_APP_ENV', 'dev', ['test'], true);
        $this->assertSame('devlocalBAR', getenv('FOO'));
        $this->assertSame('devlocalNEW_VALUE', getenv('EXISTING_KEY'));
        $this->assertSame('devlocalNEW_VALUE', $_ENV['EXISTING_KEY']);
        unlink("$path.local");
        unlink("$path.dev");
        unlink("$path.dev.local");

        // .env.dist
        file_put_contents("$path.dist", "FOO=distBAR\nEXISTING_KEY=distNEW_VALUE");

        $resetContext();
        unlink($path);
        (new Dotenv())->usePutenv()->loadEnv($path, 'TEST_APP_ENV');
        $this->assertSame('distBAR', getenv('FOO'));
        $this->assertSame('EXISTING_VALUE', getenv('EXISTING_KEY'));
        $this->assertSame('EXISTING_VALUE', $_ENV['EXISTING_KEY']);

        $resetContext();
        (new Dotenv())->usePutenv()->loadEnv($path, 'TEST_APP_ENV', 'dev', ['test'], true);
        $this->assertSame('distBAR', getenv('FOO'));
        $this->assertSame('distNEW_VALUE', getenv('EXISTING_KEY'));
        $this->assertSame('distNEW_VALUE', $_ENV['EXISTING_KEY']);
        unlink("$path.dist");

        $resetContext();
        unset($_ENV['EXISTING_KEY'], $_SERVER['EXISTING_KEY']);
        putenv('EXISTING_KEY');
        rmdir($tmpdir);
    }

    public function testLoadEnvResolvesVariablesFromOverriddenFiles()
    {
        $resetContext = static function (): void {
            unset($_ENV['SYMFONY_DOTENV_VARS'], $_ENV['REDIS_HOST'], $_ENV['LOCK_DSN'], $_ENV['HOST'], $_ENV['DSN'], $_ENV['FOO'], $_ENV['BAR'], $_ENV['DATABASE_URL'], $_ENV['TEST_APP_ENV']);
            unset($_SERVER['SYMFONY_DOTENV_VARS'], $_SERVER['REDIS_HOST'], $_SERVER['LOCK_DSN'], $_SERVER['HOST'], $_SERVER['DSN'], $_SERVER['FOO'], $_SERVER['BAR'], $_SERVER['DATABASE_URL'], $_SERVER['TEST_APP_ENV']);
            putenv('SYMFONY_DOTENV_VARS');
            putenv('REDIS_HOST');
            putenv('LOCK_DSN');
            putenv('HOST');
            putenv('DSN');
            putenv('FOO');
            putenv('BAR');
            putenv('DATABASE_URL');
            putenv('TEST_APP_ENV');
        };

        @mkdir($tmpdir = sys_get_temp_dir().'/dotenv');
        $path = tempnam($tmpdir, 'sf-');

        // .env defines REDIS_HOST and LOCK_DSN referencing it
        file_put_contents($path, "REDIS_HOST=localhost\nLOCK_DSN=redis://\${REDIS_HOST}");
        // .env.local overrides REDIS_HOST
        file_put_contents("$path.local", 'REDIS_HOST=aaa');

        $resetContext();
        (new Dotenv())->usePutenv()->loadEnv($path, 'TEST_APP_ENV');

        $this->assertSame('aaa', getenv('REDIS_HOST'));
        $this->assertSame('redis://aaa', getenv('LOCK_DSN'));

        // backslash + variable in double-quoted value must resolve correctly
        file_put_contents($path, "HOST=localhost\nDSN=\"path\\\\\${HOST}\"");
        file_put_contents("$path.local", 'HOST=override');

        $resetContext();
        (new Dotenv())->usePutenv()->loadEnv($path, 'TEST_APP_ENV');

        $this->assertSame('override', getenv('HOST'));
        $this->assertSame('path\\override', getenv('DSN'));

        // single-quoted $ must stay literal and not be resolved
        file_put_contents($path, "BAR=hello\nFOO='\$BAR'");
        file_put_contents("$path.local", 'BAR=world');

        $resetContext();
        (new Dotenv())->usePutenv()->loadEnv($path, 'TEST_APP_ENV');

        $this->assertSame('$BAR', getenv('FOO'));
        $this->assertSame('world', getenv('BAR'));

        // escaped $ in double-quoted value must stay literal during deferred resolution
        file_put_contents($path, 'FOO="\$2y\$10\$AAAAAAAAAAAAAAAAAAAAAAAAAA.BBBBBBBBBBBBBBBBBBBBBB"');
        file_put_contents("$path.local", 'BAR=dummy');

        $resetContext();
        (new Dotenv())->usePutenv()->loadEnv($path, 'TEST_APP_ENV');

        $this->assertSame('$2y$10$AAAAAAAAAAAAAAAAAAAAAAAAAA.BBBBBBBBBBBBBBBBBBBBBB', getenv('FOO'));

        // escaped $ in unquoted value must stay literal during deferred resolution
        file_put_contents($path, 'FOO=\$2y\$10\$AAAAAAAAAAAAAAAAAAAAAAAAAA.BBBBBBBBBBBBBBBBBBBBBB');
        file_put_contents("$path.local", 'BAR=dummy');

        $resetContext();
        (new Dotenv())->usePutenv()->loadEnv($path, 'TEST_APP_ENV');

        $this->assertSame('$2y$10$AAAAAAAAAAAAAAAAAAAAAAAAAA.BBBBBBBBBBBBBBBBBBBBBB', getenv('FOO'));

        // double backslash in unquoted value without $ must be unescaped during deferred resolution
        file_put_contents($path, 'DATABASE_URL=sqlsrv://user:pass@localhost\\\\SQLEXPRESS/db');
        file_put_contents("$path.local", 'BAR=dummy');

        $resetContext();
        (new Dotenv())->usePutenv()->loadEnv($path, 'TEST_APP_ENV');

        $this->assertSame('sqlsrv://user:pass@localhost\\SQLEXPRESS/db', getenv('DATABASE_URL'));

        // double backslash + variable in cross-file resolution must not double-unescape
        file_put_contents($path, "HOST=localhost\nDSN=\"path\\\\\\\\:\${HOST}\"");
        file_put_contents("$path.local", 'HOST=override');

        $resetContext();
        (new Dotenv())->usePutenv()->loadEnv($path, 'TEST_APP_ENV');

        $this->assertSame('path\\\\:override', getenv('DSN'));

        $resetContext();
        unlink("$path.local");
        unlink($path);
        rmdir($tmpdir);
    }

    public function testLoadMultiplePathsResolvesVariables()
    {
        unset($_ENV['SYMFONY_DOTENV_VARS'], $_ENV['HOST'], $_ENV['URL']);
        unset($_SERVER['SYMFONY_DOTENV_VARS'], $_SERVER['HOST'], $_SERVER['URL']);
        putenv('SYMFONY_DOTENV_VARS');
        putenv('HOST');
        putenv('URL');

        @mkdir($tmpdir = sys_get_temp_dir().'/dotenv');
        $path1 = tempnam($tmpdir, 'sf-');
        $path2 = tempnam($tmpdir, 'sf-');

        file_put_contents($path1, "HOST=localhost\nURL=http://\${HOST}");
        file_put_contents($path2, 'HOST=production');

        (new Dotenv())->usePutenv()->load($path1, $path2);

        $this->assertSame('production', getenv('HOST'));
        $this->assertSame('http://production', getenv('URL'));

        putenv('SYMFONY_DOTENV_VARS');
        putenv('HOST');
        putenv('URL');
        unlink($path1);
        unlink($path2);
        rmdir($tmpdir);
    }

    public function testLoadEnvResolvesCommandsWithOverriddenVars()
    {
        if ('\\' === \DIRECTORY_SEPARATOR) {
            $this->markTestSkipped('This test cannot be run on Windows.');
        }

        $resetContext = static function (): void {
            unset($_ENV['SYMFONY_DOTENV_VARS'], $_ENV['HOST'], $_ENV['RESOLVED'], $_ENV['TEST_APP_ENV']);
            unset($_SERVER['SYMFONY_DOTENV_VARS'], $_SERVER['HOST'], $_SERVER['RESOLVED'], $_SERVER['TEST_APP_ENV']);
            putenv('SYMFONY_DOTENV_VARS');
            putenv('HOST');
            putenv('RESOLVED');
            putenv('TEST_APP_ENV');
        };

        @mkdir($tmpdir = sys_get_temp_dir().'/dotenv');
        $path = tempnam($tmpdir, 'sf-');

        file_put_contents($path, "HOST=original\nRESOLVED=\"\$(echo \${HOST})\"");
        file_put_contents("$path.local", 'HOST=overridden');

        $resetContext();
        (new Dotenv())->usePutenv()->loadEnv($path, 'TEST_APP_ENV');

        $this->assertSame('overridden', getenv('HOST'));
        $this->assertSame('overridden', getenv('RESOLVED'));

        $resetContext();
        unlink("$path.local");
        unlink($path);
        rmdir($tmpdir);
    }

    public function testLoadEnvResolvesUnquotedCommandsWithOverriddenVars()
    {
        if ('\\' === \DIRECTORY_SEPARATOR) {
            $this->markTestSkipped('This test cannot be run on Windows.');
        }

        $resetContext = static function (): void {
            unset($_ENV['SYMFONY_DOTENV_VARS'], $_ENV['HOST'], $_ENV['RESOLVED'], $_ENV['TEST_APP_ENV']);
            unset($_SERVER['SYMFONY_DOTENV_VARS'], $_SERVER['HOST'], $_SERVER['RESOLVED'], $_SERVER['TEST_APP_ENV']);
        };

        @mkdir($tmpdir = sys_get_temp_dir().'/dotenv');
        $path = tempnam($tmpdir, 'sf-');

        file_put_contents($path, "HOST=original\nRESOLVED=\$(echo \${HOST})");
        file_put_contents("$path.local", 'HOST=overridden');

        $resetContext();
        (new Dotenv())->loadEnv($path, 'TEST_APP_ENV');

        $this->assertSame('overridden', $_ENV['HOST']);
        $this->assertSame('overridden', $_ENV['RESOLVED']);

        $resetContext();
        unlink("$path.local");
        unlink($path);
        rmdir($tmpdir);
    }

    public function testLoadEnvSelfReferencingVariableWithDefault()
    {
        $resetContext = static function (): void {
            unset($_ENV['SYMFONY_DOTENV_VARS'], $_ENV['MY_VAR'], $_ENV['TEST_APP_ENV']);
            unset($_SERVER['SYMFONY_DOTENV_VARS'], $_SERVER['MY_VAR'], $_SERVER['TEST_APP_ENV']);
            putenv('SYMFONY_DOTENV_VARS');
            putenv('MY_VAR');
            putenv('TEST_APP_ENV');
        };

        @mkdir($tmpdir = sys_get_temp_dir().'/dotenv');
        $path = tempnam($tmpdir, 'sf-');

        // Self-referencing variable with default value
        file_put_contents($path, 'MY_VAR="${MY_VAR:-default_value}"');

        $resetContext();
        (new Dotenv())->usePutenv()->loadEnv($path, 'TEST_APP_ENV');

        $this->assertSame('default_value', getenv('MY_VAR'));

        // When host env is set, it should take precedence
        $resetContext();
        putenv('MY_VAR=host_value');
        $_ENV['MY_VAR'] = 'host_value';
        (new Dotenv())->usePutenv()->loadEnv($path, 'TEST_APP_ENV');

        $this->assertSame('host_value', getenv('MY_VAR'));

        // Self-referencing variable with := (assign default)
        file_put_contents($path, 'MY_VAR="${MY_VAR:=fallback}"');

        $resetContext();
        (new Dotenv())->usePutenv()->loadEnv($path, 'TEST_APP_ENV');

        $this->assertSame('fallback', getenv('MY_VAR'));

        $resetContext();
        putenv('MY_VAR');
        unlink($path);
        @rmdir($tmpdir);
    }

    public function testLoadSelfReferencingVariableWithSuffix()
    {
        $resetContext = static function (): void {
            unset($_ENV['SYMFONY_DOTENV_VARS'], $_ENV['MY_VAR']);
            unset($_SERVER['SYMFONY_DOTENV_VARS'], $_SERVER['MY_VAR']);
            putenv('SYMFONY_DOTENV_VARS');
            putenv('MY_VAR');
        };

        @mkdir($tmpdir = sys_get_temp_dir().'/dotenv');
        $basePath = tempnam($tmpdir, 'sf-');
        $overridePath = tempnam($tmpdir, 'sf-');

        // Base file sets original value, override file appends suffix
        file_put_contents($basePath, 'MY_VAR=original');
        file_put_contents($overridePath, 'MY_VAR="${MY_VAR}_suffix"');

        $resetContext();
        $dotenv = (new Dotenv())->usePutenv();
        $dotenv->load($basePath);
        $dotenv->load($overridePath);

        $this->assertSame('original_suffix', getenv('MY_VAR'));

        // Test with prefix instead of suffix
        file_put_contents($overridePath, 'MY_VAR="prefix_${MY_VAR}"');

        $resetContext();
        $dotenv = (new Dotenv())->usePutenv();
        $dotenv->load($basePath);
        $dotenv->load($overridePath);

        $this->assertSame('prefix_original', getenv('MY_VAR'));

        // Test chained loads (three files)
        $thirdPath = tempnam($tmpdir, 'sf-');
        file_put_contents($overridePath, 'MY_VAR="${MY_VAR}_middle"');
        file_put_contents($thirdPath, 'MY_VAR="${MY_VAR}_end"');

        $resetContext();
        $dotenv = (new Dotenv())->usePutenv();
        $dotenv->load($basePath);
        $dotenv->load($overridePath);
        $dotenv->load($thirdPath);

        $this->assertSame('original_middle_end', getenv('MY_VAR'));

        $resetContext();
        unlink($basePath);
        unlink($overridePath);
        unlink($thirdPath);
        @rmdir($tmpdir);
    }

    public function testLoadEnvSelfReferencingEnvKeyControlsFileLoading()
    {
        $resetContext = static function (): void {
            unset($_ENV['SYMFONY_DOTENV_VARS'], $_ENV['TEST_APP_ENV'], $_ENV['FOO']);
            unset($_SERVER['SYMFONY_DOTENV_VARS'], $_SERVER['TEST_APP_ENV'], $_SERVER['FOO']);
            putenv('SYMFONY_DOTENV_VARS');
            putenv('TEST_APP_ENV');
            putenv('FOO');
        };

        @mkdir($tmpdir = sys_get_temp_dir().'/dotenv');
        $path = tempnam($tmpdir, 'sf-');

        // APP_ENV with self-referencing default must control which .env files are loaded
        file_put_contents($path, 'TEST_APP_ENV="${TEST_APP_ENV:-dev}"'."\nFOO=bar");
        file_put_contents("$path.dev", 'FOO=devbar');

        $resetContext();
        (new Dotenv())->usePutenv()->loadEnv($path, 'TEST_APP_ENV');

        $this->assertSame('dev', getenv('TEST_APP_ENV'));
        $this->assertSame('devbar', getenv('FOO'));

        // Host env should override the default and control file loading
        $resetContext();
        file_put_contents("$path.prod", 'FOO=prodbar');
        putenv('TEST_APP_ENV=prod');
        $_ENV['TEST_APP_ENV'] = 'prod';
        (new Dotenv())->usePutenv()->loadEnv($path, 'TEST_APP_ENV');

        $this->assertSame('prod', getenv('TEST_APP_ENV'));
        $this->assertSame('prodbar', getenv('FOO'));

        $resetContext();
        putenv('TEST_APP_ENV');
        @unlink("$path.dev");
        @unlink("$path.prod");
        unlink($path);
        @rmdir($tmpdir);
    }

    public function testLoadEnvThrowsOnCircularVariableReferences()
    {
        $resetContext = static function (): void {
            unset($_ENV['SYMFONY_DOTENV_VARS'], $_ENV['A'], $_ENV['B'], $_ENV['TEST_APP_ENV']);
            unset($_SERVER['SYMFONY_DOTENV_VARS'], $_SERVER['A'], $_SERVER['B'], $_SERVER['TEST_APP_ENV']);
        };

        @mkdir($tmpdir = sys_get_temp_dir().'/dotenv');
        $path1 = tempnam($tmpdir, 'sf-');
        $path2 = tempnam($tmpdir, 'sf-');

        // Mutual references that grow each pass — never stabilize
        file_put_contents($path1, 'A=${B}x');
        file_put_contents($path2, 'B=${A}y');

        $resetContext();
        try {
            $this->expectException(VariableCircularReferenceException::class);
            $this->expectExceptionMessage('Too many levels of variable indirection');
            (new Dotenv())->load($path1, $path2);
        } finally {
            $resetContext();
            unlink($path1);
            unlink($path2);
            rmdir($tmpdir);
        }
    }

    public function testLoadEnvUnquotedSpaceWithVariableDoesNotThrow()
    {
        $resetContext = static function (): void {
            unset($_ENV['SYMFONY_DOTENV_VARS'], $_ENV['PREFIX'], $_ENV['LABEL'], $_ENV['TEST_APP_ENV']);
            unset($_SERVER['SYMFONY_DOTENV_VARS'], $_SERVER['PREFIX'], $_SERVER['LABEL'], $_SERVER['TEST_APP_ENV']);
            putenv('SYMFONY_DOTENV_VARS');
            putenv('PREFIX');
            putenv('LABEL');
            putenv('TEST_APP_ENV');
        };

        @mkdir($tmpdir = sys_get_temp_dir().'/dotenv');
        $path = tempnam($tmpdir, 'sf-');

        // Unquoted value with a space and a variable reference
        file_put_contents($path, "PREFIX=hello\nLABEL=\${PREFIX} world");
        file_put_contents("$path.local", 'PREFIX=overridden');

        $resetContext();
        (new Dotenv())->usePutenv()->loadEnv($path, 'TEST_APP_ENV');

        $this->assertSame('overridden', getenv('PREFIX'));
        $this->assertSame('overridden world', getenv('LABEL'));

        $resetContext();
        unlink("$path.local");
        unlink($path);
        rmdir($tmpdir);
    }

    public function testOverload()
    {
        unset($_ENV['FOO']);
        unset($_ENV['BAR']);
        unset($_SERVER['FOO']);
        unset($_SERVER['BAR']);

        putenv('FOO=initial_foo_value');
        putenv('BAR=initial_bar_value');
        $_ENV['FOO'] = 'initial_foo_value';
        $_ENV['BAR'] = 'initial_bar_value';

        @mkdir($tmpdir = sys_get_temp_dir().'/dotenv');

        $path1 = tempnam($tmpdir, 'sf-');
        $path2 = tempnam($tmpdir, 'sf-');

        file_put_contents($path1, 'FOO=BAR');
        file_put_contents($path2, 'BAR=BAZ');

        (new Dotenv())->usePutenv()->overload($path1, $path2);

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
        $this->expectException(PathException::class);
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

        $dotenv = (new Dotenv())->usePutenv();
        $dotenv->populate(['TEST_ENV_VAR' => 'new_value']);

        $this->assertSame('original_value', getenv('TEST_ENV_VAR'));
    }

    public function testHttpVarIsPartiallyOverridden()
    {
        $_SERVER['HTTP_TEST_ENV_VAR'] = 'http_value';

        $dotenv = (new Dotenv())->usePutenv();
        $dotenv->populate(['HTTP_TEST_ENV_VAR' => 'env_value']);

        $this->assertSame('env_value', getenv('HTTP_TEST_ENV_VAR'));
        $this->assertSame('env_value', $_ENV['HTTP_TEST_ENV_VAR']);
        $this->assertSame('http_value', $_SERVER['HTTP_TEST_ENV_VAR']);
    }

    public function testEnvVarIsOverridden()
    {
        putenv('TEST_ENV_VAR_OVERRIDDEN=original_value');

        $dotenv = (new Dotenv())->usePutenv();
        $dotenv->populate(['TEST_ENV_VAR_OVERRIDDEN' => 'new_value'], true);

        $this->assertSame('new_value', getenv('TEST_ENV_VAR_OVERRIDDEN'));
        $this->assertSame('new_value', $_ENV['TEST_ENV_VAR_OVERRIDDEN']);
        $this->assertSame('new_value', $_SERVER['TEST_ENV_VAR_OVERRIDDEN']);
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

        $dotenv = (new Dotenv())->usePutenv();
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

        $dotenv = (new Dotenv())->usePutenv();
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

        $dotenv = (new Dotenv())->usePutenv();
        $dotenv->populate(['FOO' => 'foo1', 'BAR' => 'bar1', 'BAZ' => 'baz1', 'DOCUMENT_ROOT' => '/boot']);

        $this->assertSame('foo1', getenv('FOO'));
        $this->assertSame('bar1', getenv('BAR'));
        $this->assertSame('baz1', getenv('BAZ'));
        $this->assertSame('/var/www', getenv('DOCUMENT_ROOT'));
    }

    public function testGetVariablesValueFromEnvFirst()
    {
        $_ENV['APP_ENV'] = 'prod';
        $dotenv = new Dotenv();

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

        $dotenv = new Dotenv();

        try {
            $values = $dotenv->parse('Foo=${Foo}');
            $this->assertSame('Bar', $values['Foo']);
        } finally {
            putenv('Foo');
        }
    }

    public function testNoDeprecationWarning()
    {
        $dotenv = new Dotenv();
        $this->assertInstanceOf(Dotenv::class, $dotenv);
    }

    public function testDoNotUsePutenv()
    {
        $dotenv = new Dotenv();
        $dotenv->populate(['TEST_USE_PUTENV' => 'no']);

        $this->assertSame('no', $_SERVER['TEST_USE_PUTENV']);
        $this->assertSame('no', $_ENV['TEST_USE_PUTENV']);
        $this->assertFalse(getenv('TEST_USE_PUTENV'));
    }

    public function testSERVERVarsDuplicationInENV()
    {
        unset($_ENV['SYMFONY_DOTENV_VARS'], $_SERVER['SYMFONY_DOTENV_VARS'], $_ENV['FOO']);
        $_SERVER['FOO'] = 'CCC';

        (new Dotenv())->populate(['FOO' => 'BAR']);

        $this->assertSame('CCC', $_ENV['FOO']);
    }

    public function testBootEnv()
    {
        $resetContext = static function (): void {
            unset($_SERVER['SYMFONY_DOTENV_VARS'], $_ENV['SYMFONY_DOTENV_VARS']);
            unset($_SERVER['TEST_APP_ENV'], $_ENV['TEST_APP_ENV']);
            unset($_SERVER['TEST_APP_DEBUG'], $_ENV['TEST_APP_DEBUG']);
            unset($_SERVER['FOO'], $_ENV['FOO']);

            $_ENV['EXISTING_KEY'] = $_SERVER['EXISTING_KEY'] = 'EXISTING_VALUE';
        };

        @mkdir($tmpdir = sys_get_temp_dir().'/dotenv');
        $path = tempnam($tmpdir, 'sf-');

        file_put_contents($path, "FOO=BAR\nEXISTING_KEY=NEW_VALUE");
        $resetContext();
        (new Dotenv('TEST_APP_ENV', 'TEST_APP_DEBUG'))->bootEnv($path);
        $this->assertSame('BAR', $_SERVER['FOO']);
        $this->assertSame('EXISTING_VALUE', $_SERVER['EXISTING_KEY']);

        $resetContext();
        (new Dotenv('TEST_APP_ENV', 'TEST_APP_DEBUG'))->bootEnv($path, 'dev', ['test'], true);
        $this->assertSame('BAR', $_SERVER['FOO']);
        $this->assertSame('NEW_VALUE', $_SERVER['EXISTING_KEY']);
        unlink($path);

        file_put_contents($path.'.local.php', '<?php return ["TEST_APP_ENV" => "dev", "FOO" => "BAR", "EXISTING_KEY" => "localphpNEW_VALUE"];');
        $resetContext();
        (new Dotenv('TEST_APP_ENV', 'TEST_APP_DEBUG'))->bootEnv($path);
        $this->assertSame('BAR', $_SERVER['FOO']);
        $this->assertSame('1', $_SERVER['TEST_APP_DEBUG']);
        $this->assertSame('EXISTING_VALUE', $_SERVER['EXISTING_KEY']);

        $resetContext();
        (new Dotenv('TEST_APP_ENV', 'TEST_APP_DEBUG'))->bootEnv($path, 'dev', ['test'], true);
        $this->assertSame('BAR', $_SERVER['FOO']);
        $this->assertSame('1', $_SERVER['TEST_APP_DEBUG']);
        $this->assertSame('localphpNEW_VALUE', $_SERVER['EXISTING_KEY']);

        $resetContext();
        $_SERVER['TEST_APP_ENV'] = 'ccc';
        (new Dotenv('TEST_APP_ENV', 'TEST_APP_DEBUG'))->bootEnv($path, 'dev', ['test'], true);
        $this->assertSame('BAR', $_SERVER['FOO']);
        $this->assertSame('1', $_SERVER['TEST_APP_DEBUG']);
        $this->assertSame('localphpNEW_VALUE', $_SERVER['EXISTING_KEY']);
        unlink($path.'.local.php');

        $resetContext();
        rmdir($tmpdir);
    }

    public function testExceptionWithBom()
    {
        $dotenv = new Dotenv();

        $this->expectException(FormatException::class);
        $this->expectExceptionMessage('Loading files starting with a byte-order-mark (BOM) is not supported.');

        $dotenv->load(__DIR__.'/fixtures/file_with_bom');
    }
}
