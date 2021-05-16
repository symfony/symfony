<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

// Please update when phpunit needs to be reinstalled with fresh deps:
// Cache-Id: 2021-02-04 11:00 UTC

error_reporting(-1);

global $argv, $argc;
$argv = $_SERVER['argv'] ?? [];
$argc = $_SERVER['argc'] ?? 0;
$getEnvVar = function ($name, $default = false) use ($argv) {
    if (false !== $value = getenv($name)) {
        return $value;
    }

    static $phpunitConfig = null;
    if (null === $phpunitConfig) {
        $phpunitConfigFilename = null;
        $getPhpUnitConfig = function ($probableConfig) use (&$getPhpUnitConfig) {
            if (!$probableConfig) {
                return null;
            }
            if (is_dir($probableConfig)) {
                return $getPhpUnitConfig($probableConfig.\DIRECTORY_SEPARATOR.'phpunit.xml');
            }

            if (file_exists($probableConfig)) {
                return $probableConfig;
            }
            if (file_exists($probableConfig.'.dist')) {
                return $probableConfig.'.dist';
            }

            return null;
        };

        foreach ($argv as $cliArgumentIndex => $cliArgument) {
            if ('--' === $cliArgument) {
                break;
            }
            // long option
            if ('--configuration' === $cliArgument && array_key_exists($cliArgumentIndex + 1, $argv)) {
                $phpunitConfigFilename = $getPhpUnitConfig($argv[$cliArgumentIndex + 1]);
                break;
            }
            // short option
            if (0 === strpos($cliArgument, '-c')) {
                if ('-c' === $cliArgument && array_key_exists($cliArgumentIndex + 1, $argv)) {
                    $phpunitConfigFilename = $getPhpUnitConfig($argv[$cliArgumentIndex + 1]);
                } else {
                    $phpunitConfigFilename = $getPhpUnitConfig(substr($cliArgument, 2));
                }
                break;
            }
        }

        $phpunitConfigFilename = $phpunitConfigFilename ?: $getPhpUnitConfig('phpunit.xml');

        if ($phpunitConfigFilename) {
            $phpunitConfig = new DomDocument();
            $phpunitConfig->load($phpunitConfigFilename);
        } else {
            $phpunitConfig = false;
        }
    }
    if (false !== $phpunitConfig) {
        $var = new DOMXpath($phpunitConfig);
        foreach ($var->query('//php/server[@name="'.$name.'"]') as $var) {
            return $var->getAttribute('value');
        }
        foreach ($var->query('//php/env[@name="'.$name.'"]') as $var) {
            return $var->getAttribute('value');
        }
    }

    return $default;
};

$passthruOrFail = function ($command) {
    passthru($command, $status);

    if ($status) {
        exit($status);
    }
};

if (\PHP_VERSION_ID >= 80000) {
    // PHP 8 requires PHPUnit 9.3+
    $PHPUNIT_VERSION = $getEnvVar('SYMFONY_PHPUNIT_VERSION', '9.4') ?: '9.4';
} elseif (\PHP_VERSION_ID >= 70200) {
    // PHPUnit 8 requires PHP 7.2+
    $PHPUNIT_VERSION = $getEnvVar('SYMFONY_PHPUNIT_VERSION', '8.5') ?: '8.5';
} else {
    $PHPUNIT_VERSION = $getEnvVar('SYMFONY_PHPUNIT_VERSION', '7.5') ?: '7.5';
}

$MAX_PHPUNIT_VERSION = $getEnvVar('SYMFONY_MAX_PHPUNIT_VERSION', false);

if ($MAX_PHPUNIT_VERSION && version_compare($MAX_PHPUNIT_VERSION, $PHPUNIT_VERSION, '<')) {
    $PHPUNIT_VERSION = $MAX_PHPUNIT_VERSION;
}

$PHPUNIT_REMOVE_RETURN_TYPEHINT = filter_var($getEnvVar('SYMFONY_PHPUNIT_REMOVE_RETURN_TYPEHINT', '0'), \FILTER_VALIDATE_BOOLEAN);

$COMPOSER_JSON = getenv('COMPOSER') ?: 'composer.json';

$root = __DIR__;
while (!file_exists($root.'/'.$COMPOSER_JSON) || file_exists($root.'/DeprecationErrorHandler.php')) {
    if ($root === dirname($root)) {
        break;
    }
    $root = dirname($root);
}

$oldPwd = getcwd();
$PHPUNIT_DIR = $getEnvVar('SYMFONY_PHPUNIT_DIR', $root.'/vendor/bin/.phpunit');
$PHP = defined('PHP_BINARY') ? \PHP_BINARY : 'php';
$PHP = escapeshellarg($PHP);
if ('phpdbg' === \PHP_SAPI) {
    $PHP .= ' -qrr';
}

$defaultEnvs = [
    'COMPOSER' => 'composer.json',
    'COMPOSER_VENDOR_DIR' => 'vendor',
    'COMPOSER_BIN_DIR' => 'bin',
    'SYMFONY_SIMPLE_PHPUNIT_BIN_DIR' => __DIR__,
];

foreach ($defaultEnvs as $envName => $envValue) {
    if ($envValue !== getenv($envName)) {
        putenv("$envName=$envValue");
        $_SERVER[$envName] = $_ENV[$envName] = $envValue;
    }
}

if ('disabled' === $getEnvVar('SYMFONY_DEPRECATIONS_HELPER')) {
    putenv('SYMFONY_DEPRECATIONS_HELPER=disabled');
}

$COMPOSER = file_exists($COMPOSER = $oldPwd.'/composer.phar')
    || ($COMPOSER = rtrim((string) ('\\' === \DIRECTORY_SEPARATOR ? preg_replace('/[\r\n].*/', '', `where.exe composer.phar 2> NUL`) : `which composer.phar 2> /dev/null`)))
    || ($COMPOSER = rtrim((string) ('\\' === \DIRECTORY_SEPARATOR ? preg_replace('/[\r\n].*/', '', `where.exe composer 2> NUL`) : `which composer 2> /dev/null`)))
    || file_exists($COMPOSER = rtrim((string) ('\\' === \DIRECTORY_SEPARATOR ? `git rev-parse --show-toplevel 2> NUL` : `git rev-parse --show-toplevel 2> /dev/null`)).\DIRECTORY_SEPARATOR.'composer.phar')
    ? ('#!/usr/bin/env php' === file_get_contents($COMPOSER, false, null, 0, 18) ? $PHP : '').' '.escapeshellarg($COMPOSER) // detect shell wrappers by looking at the shebang
    : 'composer';

$prevCacheDir = getenv('COMPOSER_CACHE_DIR');
if ($prevCacheDir) {
    if (false === $absoluteCacheDir = realpath($prevCacheDir)) {
        @mkdir($prevCacheDir, 0777, true);
        $absoluteCacheDir = realpath($prevCacheDir);
    }
    if ($absoluteCacheDir) {
        putenv("COMPOSER_CACHE_DIR=$absoluteCacheDir");
    } else {
        $prevCacheDir = false;
    }
}
$SYMFONY_PHPUNIT_REMOVE = $getEnvVar('SYMFONY_PHPUNIT_REMOVE', 'phpspec/prophecy'.($PHPUNIT_VERSION < 6.0 ? ' symfony/yaml' : ''));
$SYMFONY_PHPUNIT_REQUIRE = $getEnvVar('SYMFONY_PHPUNIT_REQUIRE', '');
$configurationHash = md5(implode(\PHP_EOL, [md5_file(__FILE__), $SYMFONY_PHPUNIT_REMOVE, $SYMFONY_PHPUNIT_REQUIRE, (int) $PHPUNIT_REMOVE_RETURN_TYPEHINT]));
$PHPUNIT_VERSION_DIR = sprintf('phpunit-%s-%d', $PHPUNIT_VERSION, $PHPUNIT_REMOVE_RETURN_TYPEHINT);
if (!file_exists("$PHPUNIT_DIR/$PHPUNIT_VERSION_DIR/phpunit") || $configurationHash !== @file_get_contents("$PHPUNIT_DIR/.$PHPUNIT_VERSION_DIR.md5")) {
    // Build a standalone phpunit without symfony/yaml nor prophecy by default

    @mkdir($PHPUNIT_DIR, 0777, true);
    chdir($PHPUNIT_DIR);
    if (file_exists("$PHPUNIT_VERSION_DIR")) {
        passthru(sprintf('\\' === \DIRECTORY_SEPARATOR ? 'rmdir /S /Q %s 2> NUL' : 'rm -rf %s', escapeshellarg("$PHPUNIT_VERSION_DIR.old")));
        rename("$PHPUNIT_VERSION_DIR", "$PHPUNIT_VERSION_DIR.old");
        passthru(sprintf('\\' === \DIRECTORY_SEPARATOR ? 'rmdir /S /Q %s' : 'rm -rf %s', escapeshellarg("$PHPUNIT_VERSION_DIR.old")));
    }

    $info = [];
    foreach (explode("\n", `$COMPOSER info --no-ansi -a -n phpunit/phpunit "$PHPUNIT_VERSION.*"`) as $line) {
        $line = rtrim($line);

        if (!$info && preg_match('/^versions +: /', $line)) {
            $info['versions'] = explode(', ', ltrim(substr($line, 9), ': '));
        } elseif (isset($info['requires'])) {
            if ('' === $line) {
                break;
            }

            $line = explode(' ', $line, 2);
            $info['requires'][$line[0]] = $line[1];
        } elseif ($info && 'requires' === $line) {
            $info['requires'] = [];
        }
    }

    if (in_array('--colors=never', $argv, true) || (isset($argv[$i = array_search('never', $argv, true) - 1]) && '--colors' === $argv[$i])) {
        $COMPOSER .= ' --no-ansi';
    } else {
        $COMPOSER .= ' --ansi';
    }

    $info += [
        'versions' => [],
        'requires' => ['php' => '*'],
    ];

    $stableVersions = array_filter($info['versions'], function ($v) {
        return !preg_match('/-dev$|^dev-/', $v);
    });

    if (!$stableVersions) {
        $passthruOrFail("$COMPOSER create-project --ignore-platform-reqs --no-install --prefer-dist --no-scripts --no-plugins --no-progress -s dev phpunit/phpunit $PHPUNIT_VERSION_DIR \"$PHPUNIT_VERSION.*\"");
    } else {
        $passthruOrFail("$COMPOSER create-project --ignore-platform-reqs --no-install --prefer-dist --no-scripts --no-plugins --no-progress phpunit/phpunit $PHPUNIT_VERSION_DIR \"$PHPUNIT_VERSION.*\"");
    }

    @copy("$PHPUNIT_VERSION_DIR/phpunit.xsd", 'phpunit.xsd');
    chdir("$PHPUNIT_VERSION_DIR");
    if ($SYMFONY_PHPUNIT_REMOVE) {
        $passthruOrFail("$COMPOSER remove --no-update ".$SYMFONY_PHPUNIT_REMOVE);
    }
    if ($SYMFONY_PHPUNIT_REQUIRE) {
        $passthruOrFail("$COMPOSER require --no-update ".$SYMFONY_PHPUNIT_REQUIRE);
    }
    if (5.1 <= $PHPUNIT_VERSION && $PHPUNIT_VERSION < 5.4) {
        $passthruOrFail("$COMPOSER require --no-update phpunit/phpunit-mock-objects \"~3.1.0\"");
    }

    if (preg_match('{\^((\d++\.)\d++)[\d\.]*$}', $info['requires']['php'], $phpVersion) && version_compare($phpVersion[2].'99', \PHP_VERSION, '<')) {
        $passthruOrFail("$COMPOSER config platform.php \"$phpVersion[1].99\"");
    } else {
        $passthruOrFail("$COMPOSER config --unset platform.php");
    }
    if (file_exists($path = $root.'/vendor/symfony/phpunit-bridge')) {
        $passthruOrFail("$COMPOSER require --no-update symfony/phpunit-bridge \"*@dev\"");
        $passthruOrFail("$COMPOSER config repositories.phpunit-bridge path ".escapeshellarg(str_replace('/', \DIRECTORY_SEPARATOR, $path)));
        if ('\\' === \DIRECTORY_SEPARATOR) {
            file_put_contents('composer.json', preg_replace('/^( {8})"phpunit-bridge": \{$/m', "$0\n$1    ".'"options": {"symlink": false},', file_get_contents('composer.json')));
        }
    } else {
        $passthruOrFail("$COMPOSER require --no-update symfony/phpunit-bridge \"*\"");
    }
    $prevRoot = getenv('COMPOSER_ROOT_VERSION');
    putenv("COMPOSER_ROOT_VERSION=$PHPUNIT_VERSION.99");
    $q = '\\' === \DIRECTORY_SEPARATOR && \PHP_VERSION_ID < 80000 ? '"' : '';
    // --no-suggest is not in the list to keep compat with composer 1.0, which is shipped with Ubuntu 16.04LTS
    $exit = proc_close(proc_open("$q$COMPOSER install --no-dev --prefer-dist --no-progress $q", [], $p, getcwd()));
    putenv('COMPOSER_ROOT_VERSION'.(false !== $prevRoot ? '='.$prevRoot : ''));
    if ($prevCacheDir) {
        putenv("COMPOSER_CACHE_DIR=$prevCacheDir");
    }
    if ($exit) {
        exit($exit);
    }

    // Mutate TestCase code
    $alteredCode = file_get_contents($alteredFile = './src/Framework/TestCase.php');
    if ($PHPUNIT_REMOVE_RETURN_TYPEHINT) {
        $alteredCode = preg_replace('/^    ((?:protected|public)(?: static)? function \w+\(\)): void/m', '    $1', $alteredCode);
    }
    $alteredCode = preg_replace('/abstract class TestCase[^\{]+\{/', '$0 '.\PHP_EOL."    use \Symfony\Bridge\PhpUnit\Legacy\PolyfillTestCaseTrait;", $alteredCode, 1);
    file_put_contents($alteredFile, $alteredCode);

    // Mutate Assert code
    $alteredCode = file_get_contents($alteredFile = './src/Framework/Assert.php');
    $alteredCode = preg_replace('/abstract class Assert[^\{]+\{/', '$0 '.\PHP_EOL."    use \Symfony\Bridge\PhpUnit\Legacy\PolyfillAssertTrait;", $alteredCode, 1);
    file_put_contents($alteredFile, $alteredCode);

    file_put_contents('phpunit', <<<'EOPHP'
<?php

define('PHPUNIT_COMPOSER_INSTALL', __DIR__.'/vendor/autoload.php');
require PHPUNIT_COMPOSER_INSTALL;

if (!class_exists(\SymfonyExcludeListPhpunit::class, false)) {
    class SymfonyExcludeListPhpunit {}
}
if (method_exists(\PHPUnit\Util\ExcludeList::class, 'addDirectory')) {
    (new PHPUnit\Util\Excludelist())->getExcludedDirectories();
    PHPUnit\Util\ExcludeList::addDirectory(\dirname((new \ReflectionClass(\SymfonyExcludeListPhpunit::class))->getFileName()));
    class_exists(\SymfonyExcludeListSimplePhpunit::class, false) && PHPUnit\Util\ExcludeList::addDirectory(\dirname((new \ReflectionClass(\SymfonyExcludeListSimplePhpunit::class))->getFileName()));
} elseif (method_exists(\PHPUnit\Util\Blacklist::class, 'addDirectory')) {
    (new PHPUnit\Util\BlackList())->getBlacklistedDirectories();
    PHPUnit\Util\Blacklist::addDirectory(\dirname((new \ReflectionClass(\SymfonyExcludeListPhpunit::class))->getFileName()));
    class_exists(\SymfonyExcludeListSimplePhpunit::class, false) && PHPUnit\Util\Blacklist::addDirectory(\dirname((new \ReflectionClass(\SymfonyExcludeListSimplePhpunit::class))->getFileName()));
} else {
    PHPUnit\Util\Blacklist::$blacklistedClassNames['SymfonyExcludeListPhpunit'] = 1;
    PHPUnit\Util\Blacklist::$blacklistedClassNames['SymfonyExcludeListSimplePhpunit'] = 1;
}

Symfony\Bridge\PhpUnit\TextUI\Command::main();

EOPHP
    );
    chdir('..');
    file_put_contents(".$PHPUNIT_VERSION_DIR.md5", $configurationHash);
    chdir($oldPwd);
}

// Create a symlink with a predictable path pointing to the currently used version.
// This is useful for static analytics tools such as PHPStan having to load PHPUnit's classes
// and for other testing libraries such as Behat using PHPUnit's assertions.
chdir($PHPUNIT_DIR);
if ('\\' === \DIRECTORY_SEPARATOR) {
    passthru('rmdir /S /Q phpunit 2> NUL');
    passthru(sprintf('mklink /j phpunit %s > NUL 2>&1', escapeshellarg($PHPUNIT_VERSION_DIR)));
} else {
    if (file_exists('phpunit')) {
        @unlink('phpunit');
    }
    @symlink($PHPUNIT_VERSION_DIR, 'phpunit');
}
chdir($oldPwd);

if ($PHPUNIT_VERSION < 8.0) {
    $argv = array_filter($argv, function ($v) use (&$argc) {
        if ('--do-not-cache-result' !== $v) {
            return true;
        }
        --$argc;

        return false;
    });
} elseif (filter_var(getenv('SYMFONY_PHPUNIT_DISABLE_RESULT_CACHE'), \FILTER_VALIDATE_BOOLEAN)) {
    $argv[] = '--do-not-cache-result';
    ++$argc;
}

$components = [];
$cmd = array_map('escapeshellarg', $argv);
$exit = 0;

if (isset($argv[1]) && 'symfony' === $argv[1] && !file_exists('symfony') && file_exists('src/Symfony')) {
    $argv[1] = 'src/Symfony';
}
if (isset($argv[1]) && is_dir($argv[1]) && !file_exists($argv[1].'/phpunit.xml.dist')) {
    // Find Symfony components in plain php for Windows portability

    $finder = new RecursiveDirectoryIterator($argv[1], FilesystemIterator::KEY_AS_FILENAME | FilesystemIterator::UNIX_PATHS);
    $finder = new RecursiveIteratorIterator($finder);
    $finder->setMaxDepth(getenv('SYMFONY_PHPUNIT_MAX_DEPTH') ?: 3);

    foreach ($finder as $file => $fileInfo) {
        if ('phpunit.xml.dist' === $file) {
            $components[] = dirname($fileInfo->getPathname());
        }
    }
    if ($components) {
        array_shift($cmd);
    }
}

$cmd[0] = sprintf('%s %s --colors=always', $PHP, escapeshellarg("$PHPUNIT_DIR/$PHPUNIT_VERSION_DIR/phpunit"));
$cmd = str_replace('%', '%%', implode(' ', $cmd)).' %1$s';

if ('\\' === \DIRECTORY_SEPARATOR) {
    $cmd = 'cmd /v:on /d /c "('.$cmd.')%2$s"';
} else {
    $cmd .= '%2$s';
}

if ($components) {
    $skippedTests = $_SERVER['SYMFONY_PHPUNIT_SKIPPED_TESTS'] ?? false;
    $runningProcs = [];

    foreach ($components as $component) {
        // Run phpunit tests in parallel

        if ($skippedTests) {
            putenv("SYMFONY_PHPUNIT_SKIPPED_TESTS=$component/$skippedTests");
        }

        $c = escapeshellarg($component);

        if ($proc = proc_open(sprintf($cmd, $c, " > $c/phpunit.stdout 2> $c/phpunit.stderr"), [], $pipes)) {
            $runningProcs[$component] = $proc;
        } else {
            $exit = 1;
            echo "\033[41mKO\033[0m $component\n\n";
        }
    }

    while ($runningProcs) {
        usleep(300000);
        $terminatedProcs = [];
        foreach ($runningProcs as $component => $proc) {
            $procStatus = proc_get_status($proc);
            if (!$procStatus['running']) {
                $terminatedProcs[$component] = $procStatus['exitcode'];
                unset($runningProcs[$component]);
                proc_close($proc);
            }
        }

        foreach ($terminatedProcs as $component => $procStatus) {
            foreach (['out', 'err'] as $file) {
                $file = "$component/phpunit.std$file";
                readfile($file);
                unlink($file);
            }

            // Fail on any individual component failures but ignore some error codes on Windows when APCu is enabled:
            // STATUS_STACK_BUFFER_OVERRUN (-1073740791/0xC0000409)
            // STATUS_ACCESS_VIOLATION (-1073741819/0xC0000005)
            // STATUS_HEAP_CORRUPTION (-1073740940/0xC0000374)
            if ($procStatus && ('\\' !== \DIRECTORY_SEPARATOR || !extension_loaded('apcu') || !filter_var(ini_get('apc.enable_cli'), \FILTER_VALIDATE_BOOLEAN) || !in_array($procStatus, [-1073740791, -1073741819, -1073740940]))) {
                $exit = $procStatus;
                echo "\033[41mKO\033[0m $component\n\n";
            } else {
                echo "\033[32mOK\033[0m $component\n\n";
            }
        }
    }
} elseif (!isset($argv[1]) || 'install' !== $argv[1] || file_exists('install')) {
    if (!class_exists(\SymfonyExcludeListSimplePhpunit::class, false)) {
        class SymfonyExcludeListSimplePhpunit
        {
        }
    }
    array_splice($argv, 1, 0, ['--colors=always']);
    $_SERVER['argv'] = $argv;
    $_SERVER['argc'] = ++$argc;
    include "$PHPUNIT_DIR/$PHPUNIT_VERSION_DIR/phpunit";
}

exit($exit);
