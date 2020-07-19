#!/usr/bin/env php
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
// Cache-Id: 2020-01-31 10:00 UTC

error_reporting(-1);

$passthruOrFail = function ($command) {
    passthru($command, $status);

    if ($status) {
        exit($status);
    }
};

if (PHP_VERSION_ID >= 70200) {
    // PHPUnit 6 is required for PHP 7.2+
    $PHPUNIT_VERSION = getenv('SYMFONY_PHPUNIT_VERSION') ?: '6.5';
} elseif (PHP_VERSION_ID >= 50600) {
    // PHPUnit 4 does not support PHP 7
    $PHPUNIT_VERSION = getenv('SYMFONY_PHPUNIT_VERSION') ?: '5.7';
} else {
    // PHPUnit 5.1 requires PHP 5.6+
    $PHPUNIT_VERSION = '4.8';
}

$COMPOSER_JSON = getenv('COMPOSER') ?: 'composer.json';

$root = __DIR__;
while (!file_exists($root.'/'.$COMPOSER_JSON) || file_exists($root.'/DeprecationErrorHandler.php')) {
    if ($root === dirname($root)) {
        break;
    }
    $root = dirname($root);
}

$oldPwd = getcwd();
$PHPUNIT_DIR = getenv('SYMFONY_PHPUNIT_DIR') ?: ($root.'/vendor/bin/.phpunit');
$PHP = defined('PHP_BINARY') ? PHP_BINARY : 'php';
$PHP = escapeshellarg($PHP);
if ('phpdbg' === PHP_SAPI) {
    $PHP .= ' -qrr';
}

$defaultEnvs = array(
    'COMPOSER' => 'composer.json',
    'COMPOSER_VENDOR_DIR' => 'vendor',
    'COMPOSER_BIN_DIR' => 'bin',
);

foreach ($defaultEnvs as $envName => $envValue) {
    if ($envValue !== getenv($envName)) {
        putenv("$envName=$envValue");
        $_SERVER[$envName] = $_ENV[$envName] = $envValue;
    }
}

if (false === $COMPOSER = \getenv('COMPOSER_BINARY')) {
    $COMPOSER = file_exists($COMPOSER = $oldPwd.'/composer.phar')
        || ($COMPOSER = rtrim('\\' === DIRECTORY_SEPARATOR ? preg_replace('/[\r\n].*/', '', `where.exe composer.phar`) : `which composer.phar 2> /dev/null`))
        || ($COMPOSER = rtrim('\\' === DIRECTORY_SEPARATOR ? preg_replace('/[\r\n].*/', '', `where.exe composer`) : `which composer 2> /dev/null`))
        ? (file_get_contents($COMPOSER, false, null, 0, 18) === '#!/usr/bin/env php' ? $PHP : '').' '.escapeshellarg($COMPOSER) // detect shell wrappers by looking at the shebang
        : 'composer';
}

if (false === $SYMFONY_PHPUNIT_REMOVE = getenv('SYMFONY_PHPUNIT_REMOVE')) {
    $SYMFONY_PHPUNIT_REMOVE = 'phpspec/prophecy symfony/yaml';
}

if (!file_exists("$PHPUNIT_DIR/phpunit-$PHPUNIT_VERSION/phpunit") || md5_file(__FILE__)."\n".$SYMFONY_PHPUNIT_REMOVE !== @file_get_contents("$PHPUNIT_DIR/.$PHPUNIT_VERSION.md5")) {
    // Build a standalone phpunit without symfony/yaml nor prophecy by default

    @mkdir($PHPUNIT_DIR, 0777, true);
    chdir($PHPUNIT_DIR);
    if (file_exists("phpunit-$PHPUNIT_VERSION")) {
        passthru(sprintf('\\' === DIRECTORY_SEPARATOR ? 'rmdir /S /Q %s > NUL': 'rm -rf %s', "phpunit-$PHPUNIT_VERSION.old"));
        rename("phpunit-$PHPUNIT_VERSION", "phpunit-$PHPUNIT_VERSION.old");
        passthru(sprintf('\\' === DIRECTORY_SEPARATOR ? 'rmdir /S /Q %s': 'rm -rf %s', "phpunit-$PHPUNIT_VERSION.old"));
    }

    $info = array();
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
            $info['requires'] = array();
        }
    }

    if (in_array('--colors=never', $argv, true) || (isset($argv[$i = array_search('never', $argv, true) - 1]) && '--colors' === $argv[$i])) {
        $COMPOSER .= ' --no-ansi';
    } else {
        $COMPOSER .= ' --ansi';
    }

    $info += array(
        'versions' => array(),
        'requires' => array('php' => '*'),
    );

    $stableVersions = array_filter($info['versions'], function($v) {
        return !preg_match('/-dev$|^dev-/', $v);
    });

    if (!$stableVersions) {
        $passthruOrFail("$COMPOSER create-project --ignore-platform-reqs --no-install --prefer-dist --no-scripts --no-plugins --no-progress -s dev phpunit/phpunit phpunit-$PHPUNIT_VERSION \"$PHPUNIT_VERSION.*\"");
    } else {
        $passthruOrFail("$COMPOSER create-project --ignore-platform-reqs --no-install --prefer-dist --no-scripts --no-plugins --no-progress phpunit/phpunit phpunit-$PHPUNIT_VERSION \"$PHPUNIT_VERSION.*\"");
    }

    @copy("phpunit-$PHPUNIT_VERSION/phpunit.xsd", 'phpunit.xsd');
    chdir("phpunit-$PHPUNIT_VERSION");
    if ($SYMFONY_PHPUNIT_REMOVE) {
        $passthruOrFail("$COMPOSER remove --no-update ".$SYMFONY_PHPUNIT_REMOVE);
    }
    if (5.1 <= $PHPUNIT_VERSION && $PHPUNIT_VERSION < 5.4) {
        $passthruOrFail("$COMPOSER require --no-update phpunit/phpunit-mock-objects \"~3.1.0\"");
    }

    if (preg_match('{\^((\d++\.)\d++)[\d\.]*$}', $info['requires']['php'], $phpVersion) && version_compare($phpVersion[2].'99', PHP_VERSION, '<')) {
        $passthruOrFail("$COMPOSER config platform.php \"$phpVersion[1].99\"");
    } else {
        $passthruOrFail("$COMPOSER config --unset platform.php");
    }
    if (file_exists($path = $root.'/vendor/symfony/phpunit-bridge')) {
        $passthruOrFail("$COMPOSER require --no-update symfony/phpunit-bridge \"*@dev\"");
        $passthruOrFail("$COMPOSER config repositories.phpunit-bridge path ".escapeshellarg(str_replace('/', DIRECTORY_SEPARATOR, $path)));
        if ('\\' === DIRECTORY_SEPARATOR) {
            file_put_contents('composer.json', preg_replace('/^( {8})"phpunit-bridge": \{$/m', "$0\n$1    ".'"options": {"symlink": false},', file_get_contents('composer.json')));
        }
    } else {
        $passthruOrFail("$COMPOSER require --no-update symfony/phpunit-bridge \"*\"");
    }
    $prevRoot = getenv('COMPOSER_ROOT_VERSION');
    putenv("COMPOSER_ROOT_VERSION=$PHPUNIT_VERSION.99");
    $q = '\\' === DIRECTORY_SEPARATOR ? '"' : '';
    // --no-suggest is not in the list to keep compat with composer 1.0, which is shipped with Ubuntu 16.04LTS
    $exit = proc_close(proc_open("$q$COMPOSER install --no-dev --prefer-dist --no-progress$q", array(), $p, getcwd()));
    putenv('COMPOSER_ROOT_VERSION'.(false !== $prevRoot ? '='.$prevRoot : ''));
    if ($exit) {
        exit($exit);
    }
    file_put_contents('phpunit', <<<'EOPHP'
<?php

define('PHPUNIT_COMPOSER_INSTALL', __DIR__.'/vendor/autoload.php');
require PHPUNIT_COMPOSER_INSTALL;

if (!class_exists('SymfonyBlacklistPhpunit', false)) {
    class SymfonyBlacklistPhpunit {}
}
if (class_exists('PHPUnit_Util_Blacklist')) {
    PHPUnit_Util_Blacklist::$blacklistedClassNames['SymfonyBlacklistPhpunit'] = 1;
    PHPUnit_Util_Blacklist::$blacklistedClassNames['SymfonyBlacklistSimplePhpunit'] = 1;
} elseif (method_exists('PHPUnit\Util\Blacklist', 'addDirectory')) {
    eval(" // PHP 5.3 compat
    (new PHPUnit\Util\BlackList())->getBlacklistedDirectories();
    PHPUnit\Util\Blacklist::addDirectory(dirname((new ReflectionClass('SymfonyBlacklistPhpunit'))->getFileName()));
    PHPUnit\Util\Blacklist::addDirectory(dirname((new ReflectionClass('SymfonyBlacklistSimplePhpunit'))->getFileName()));
    ");
} else {
    PHPUnit\Util\Blacklist::$blacklistedClassNames['SymfonyBlacklistPhpunit'] = 1;
    PHPUnit\Util\Blacklist::$blacklistedClassNames['SymfonyBlacklistSimplePhpunit'] = 1;
}

Symfony\Bridge\PhpUnit\TextUI\Command::main();

EOPHP
    );
    chdir('..');
    file_put_contents(".$PHPUNIT_VERSION.md5", md5_file(__FILE__)."\n".$SYMFONY_PHPUNIT_REMOVE);
    chdir($oldPwd);

}

global $argv, $argc;
$argv = isset($_SERVER['argv']) ? $_SERVER['argv'] : array();
$argc = isset($_SERVER['argc']) ? $_SERVER['argc'] : 0;

if ($PHPUNIT_VERSION < 8.0) {
    $argv = array_filter($argv, function ($v) use (&$argc) { if ('--do-not-cache-result' !== $v) return true; --$argc; return false; });
} elseif (filter_var(getenv('SYMFONY_PHPUNIT_DISABLE_RESULT_CACHE'), FILTER_VALIDATE_BOOLEAN)) {
    $argv[] = '--do-not-cache-result';
    ++$argc;
}

$components = array();
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

$cmd[0] = sprintf('%s %s --colors=always', $PHP, escapeshellarg("$PHPUNIT_DIR/phpunit-$PHPUNIT_VERSION/phpunit"));
$cmd = str_replace('%', '%%', implode(' ', $cmd)).' %1$s';

if ('\\' === DIRECTORY_SEPARATOR) {
    $cmd = 'cmd /v:on /d /c "('.$cmd.')%2$s"';
} else {
    $cmd .= '%2$s';
}

if ($components) {
    $skippedTests = isset($_SERVER['SYMFONY_PHPUNIT_SKIPPED_TESTS']) ? $_SERVER['SYMFONY_PHPUNIT_SKIPPED_TESTS'] : false;
    $runningProcs = array();

    foreach ($components as $component) {
        // Run phpunit tests in parallel

        if ($skippedTests) {
            putenv("SYMFONY_PHPUNIT_SKIPPED_TESTS=$component/$skippedTests");
        }

        $c = escapeshellarg($component);

        if ($proc = proc_open(sprintf($cmd, $c, " > $c/phpunit.stdout 2> $c/phpunit.stderr"), array(), $pipes)) {
            $runningProcs[$component] = $proc;
        } else {
            $exit = 1;
            echo "\033[41mKO\033[0m $component\n\n";
        }
    }

    while ($runningProcs) {
        usleep(300000);
        $terminatedProcs = array();
        foreach ($runningProcs as $component => $proc) {
            $procStatus = proc_get_status($proc);
            if (!$procStatus['running']) {
                $terminatedProcs[$component] = $procStatus['exitcode'];
                unset($runningProcs[$component]);
                proc_close($proc);
            }
        }

        foreach ($terminatedProcs as $component => $procStatus) {
            foreach (array('out', 'err') as $file) {
                $file = "$component/phpunit.std$file";
                readfile($file);
                unlink($file);
            }

            // Fail on any individual component failures but ignore some error codes on Windows when APCu is enabled:
            // STATUS_STACK_BUFFER_OVERRUN (-1073740791/0xC0000409)
            // STATUS_ACCESS_VIOLATION (-1073741819/0xC0000005)
            // STATUS_HEAP_CORRUPTION (-1073740940/0xC0000374)
            if ($procStatus && ('\\' !== DIRECTORY_SEPARATOR || !extension_loaded('apcu') || !filter_var(ini_get('apc.enable_cli'), FILTER_VALIDATE_BOOLEAN) || !in_array($procStatus, array(-1073740791, -1073741819, -1073740940)))) {
                $exit = $procStatus;
                echo "\033[41mKO\033[0m $component\n\n";
            } else {
                echo "\033[32mOK\033[0m $component\n\n";
            }
        }
    }
} elseif (!isset($argv[1]) || 'install' !== $argv[1] || file_exists('install')) {
    if (!class_exists('SymfonyBlacklistSimplePhpunit', false)) {
        class SymfonyBlacklistSimplePhpunit {}
    }
    array_splice($argv, 1, 0, array('--colors=always'));
    $_SERVER['argv'] = $argv;
    $_SERVER['argc'] = ++$argc;
    include "$PHPUNIT_DIR/phpunit-$PHPUNIT_VERSION/phpunit";
}

exit($exit);
