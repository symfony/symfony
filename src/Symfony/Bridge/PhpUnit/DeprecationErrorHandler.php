<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bridge\PhpUnit;

/**
 * Catch deprecation notices and print a summary report at the end of the test suite.
 *
 * @author Nicolas Grekas <p@tchwork.com>
 */
class DeprecationErrorHandler
{
    const MODE_WEAK = 'weak';
    const MODE_WEAK_VENDORS = 'weak_vendors';
    const MODE_DISABLED = 'disabled';

    private static $rawMode = false;
    private static $mode = false;
    private static $isRegistered = false;
    private static $deprecations = [
        'unsilencedCount' => 0,
        'remainingCount' => 0,
        'legacyCount' => 0,
        'otherCount' => 0,
        'remaining vendorCount' => 0,
        'unsilenced' => [],
        'remaining' => [],
        'legacy' => [],
        'other' => [],
        'remaining vendor' => [],
    ];
    private static $utilPrefix = '';

    /**
     * Registers and configures the deprecation handler.
     *
     * The following reporting modes are supported:
     * - use "weak" to hide the deprecation report but keep a global count;
     * - use "weak_vendors" to fail only on deprecations triggered in your own code;
     * - use "/some-regexp/" to stop the test suite whenever a deprecation
     *   message matches the given regular expression;
     * - use a number to define the upper bound of allowed deprecations,
     *   making the test suite fail whenever more notices are triggered.
     *
     * @param int|string|false $rawMode The reporting mode, defaults to not allowing any deprecations
     */
    public static function register($rawMode = 0)
    {
        if (self::$isRegistered) {
            return;
        }

        static::$rawMode = $rawMode;

        $oldErrorHandler = set_error_handler([self::class, 'handleError']);

        if (null !== $oldErrorHandler) {
            restore_error_handler();
            if ([self::utilPrefix().'ErrorHandler', 'handleError'] === $oldErrorHandler) {
                restore_error_handler();
                self::register($rawMode);
            }
        } else {
            self::$isRegistered = true;
            register_shutdown_function(function () use ($rawMode) {
                self::computeMode($rawMode);
                $mode = self::$mode;
                if (isset($mode[0]) && '/' === $mode[0]) {
                    return;
                }
                $currErrorHandler = set_error_handler('var_dump');
                restore_error_handler();

                if ($currErrorHandler !== [self::class, 'handleError']) {
                    echo "\n", self::colorize('THE ERROR HANDLER HAS CHANGED!', true, $mode), "\n";
                }

                $groups = ['unsilenced', 'remaining'];
                if (self::MODE_WEAK_VENDORS === $mode) {
                    $groups[] = 'remaining vendor';
                }
                array_push($groups, 'legacy', 'other');

                self::displayDeprecations($groups, $mode);

                // store failing status
                $isFailing = self::MODE_WEAK !== $mode && $mode < self::$deprecations['unsilencedCount'] + self::$deprecations['remainingCount'] + self::$deprecations['otherCount'];

                // reset deprecations array
                foreach (self::$deprecations as $group => $arrayOrInt) {
                    self::$deprecations[$group] = \is_int($arrayOrInt) ? 0 : [];
                }

                register_shutdown_function(function () use ($isFailing, $groups, $mode) {
                    foreach (self::$deprecations as $group => $arrayOrInt) {
                        if (0 < (\is_int($arrayOrInt) ? $arrayOrInt : \count($arrayOrInt))) {
                            echo "Shutdown-time deprecations:\n";
                            break;
                        }
                    }
                    self::displayDeprecations($groups, $mode);
                    if ($isFailing || self::MODE_WEAK !== $mode && $mode < self::$deprecations['unsilencedCount'] + self::$deprecations['remainingCount'] + self::$deprecations['otherCount']) {
                        exit(1);
                    }
                });
            });
        }
    }

    public static function collectDeprecations($outputFile)
    {
        $deprecations = [];
        $previousErrorHandler = set_error_handler(function ($type, $msg, $file, $line, $context = []) use (&$deprecations, &$previousErrorHandler) {
            if (E_USER_DEPRECATED !== $type && E_DEPRECATED !== $type) {
                if ($previousErrorHandler) {
                    return $previousErrorHandler($type, $msg, $file, $line, $context);
                }
                static $autoload = true;

                $ErrorHandler = class_exists('PHPUnit_Util_ErrorHandler', $autoload) ? 'PHPUnit_Util_ErrorHandler' : 'PHPUnit\Util\ErrorHandler';
                $autoload = false;

                return $ErrorHandler::handleError($type, $msg, $file, $line, $context);
            }
            $deprecations[] = [error_reporting(), $msg, $file];
        });

        register_shutdown_function(function () use ($outputFile, &$deprecations) {
            file_put_contents($outputFile, serialize($deprecations));
        });
    }

    /**
     * Returns true if STDOUT is defined and supports colorization.
     *
     * Reference: Composer\XdebugHandler\Process::supportsColor
     * https://github.com/composer/xdebug-handler
     *
     * @return bool
     */
    private static function hasColorSupport()
    {
        if (!\defined('STDOUT')) {
            return false;
        }

        if ('Hyper' === getenv('TERM_PROGRAM')) {
            return true;
        }

        if (\DIRECTORY_SEPARATOR === '\\') {
            return (\function_exists('sapi_windows_vt100_support')
                && sapi_windows_vt100_support(STDOUT))
                || false !== getenv('ANSICON')
                || 'ON' === getenv('ConEmuANSI')
                || 'xterm' === getenv('TERM');
        }

        if (\function_exists('stream_isatty')) {
            return stream_isatty(STDOUT);
        }

        if (\function_exists('posix_isatty')) {
            return posix_isatty(STDOUT);
        }

        $stat = fstat(STDOUT);
        // Check if formatted mode is S_IFCHR
        return $stat ? 0020000 === ($stat['mode'] & 0170000) : false;
    }

    /**
     * @param mixed $mode
     */
    private static function computeMode($mode)
    {
        if (false !== self::$mode) {
            return;
        }
        if (false === $mode) {
            $mode = getenv('SYMFONY_DEPRECATIONS_HELPER');
        }
        if (self::MODE_DISABLED !== $mode
            && self::MODE_WEAK !== $mode
            && self::MODE_WEAK_VENDORS !== $mode
            && (!isset($mode[0]) || '/' !== $mode[0])
        ) {
            $mode = preg_match('/^[1-9][0-9]*$/', $mode) ? (int) $mode : 0;
        }

        self::$mode = $mode;
    }

    /**
     * @param string $path
     *
     * @return bool
     */
    private static function inVendors($path)
    {
        /** @var string[] absolute paths to vendor directories */
        static $vendors;
        if (null === $vendors) {
            foreach (get_declared_classes() as $class) {
                if ('C' === $class[0] && 0 === strpos($class, 'ComposerAutoloaderInit')) {
                    $r = new \ReflectionClass($class);
                    $v = \dirname(\dirname($r->getFileName()));
                    if (file_exists($v.'/composer/installed.json')) {
                        $vendors[] = $v;
                    }
                }
            }
        }
        $realPath = realpath($path);
        if (false === $realPath && '-' !== $path && 'Standard input code' !== $path) {
            return true;
        }
        foreach ($vendors as $vendor) {
            if (0 === strpos($realPath, $vendor) && false !== strpbrk(substr($realPath, \strlen($vendor), 1), '/'.\DIRECTORY_SEPARATOR)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @internal
     */
    public static function handleError($type, $msg, $file, $line, $context = [])
    {
        self::computeMode(self::$rawMode);
        $mode = self::$mode;
        if ((E_USER_DEPRECATED !== $type && E_DEPRECATED !== $type) || self::MODE_DISABLED === $mode) {
            $ErrorHandler = self::utilPrefix().'ErrorHandler';

            return $ErrorHandler::handleError($type, $msg, $file, $line, $context);
        }

        $trace = debug_backtrace();
        $group = 'other';
        $isVendor = self::MODE_WEAK_VENDORS === $mode && self::inVendors($file);

        $i = \count($trace);
        while (1 < $i && (!isset($trace[--$i]['class']) || ('ReflectionMethod' === $trace[$i]['class'] || 0 === strpos($trace[$i]['class'], 'PHPUnit_') || 0 === strpos($trace[$i]['class'], 'PHPUnit\\')))) {
            // No-op
        }

        if (isset($trace[$i]['object']) || isset($trace[$i]['class'])) {
            if (isset($trace[$i]['class']) && 0 === strpos($trace[$i]['class'], 'Symfony\Bridge\PhpUnit\Legacy\SymfonyTestsListenerFor')) {
                $parsedMsg = unserialize($msg);
                $msg = $parsedMsg['deprecation'];
                $class = $parsedMsg['class'];
                $method = $parsedMsg['method'];
                // If the deprecation has been triggered via
                // \Symfony\Bridge\PhpUnit\Legacy\SymfonyTestsListenerTrait::endTest()
                // then we need to use the serialized information to determine
                // if the error has been triggered from vendor code.
                $isVendor = self::MODE_WEAK_VENDORS === $mode && isset($parsedMsg['triggering_file']) && self::inVendors($parsedMsg['triggering_file']);
            } else {
                $class = isset($trace[$i]['object']) ? \get_class($trace[$i]['object']) : $trace[$i]['class'];
                $method = $trace[$i]['function'];
            }

            $Test = self::utilPrefix().'Test';

            if (0 !== error_reporting()) {
                $group = 'unsilenced';
            } elseif (0 === strpos($method, 'testLegacy')
                || 0 === strpos($method, 'provideLegacy')
                || 0 === strpos($method, 'getLegacy')
                || strpos($class, '\Legacy')
                || \in_array('legacy', $Test::getGroups($class, $method), true)
            ) {
                $group = 'legacy';
            } elseif ($isVendor) {
                $group = 'remaining vendor';
            } else {
                $group = 'remaining';
            }

            if (isset($mode[0]) && '/' === $mode[0] && preg_match($mode, $msg)) {
                $e = new \Exception($msg);
                $r = new \ReflectionProperty($e, 'trace');
                $r->setAccessible(true);
                $r->setValue($e, \array_slice($trace, 1, $i));

                echo "\n".ucfirst($group).' deprecation triggered by '.$class.'::'.$method.':';
                echo "\n".$msg;
                echo "\nStack trace:";
                echo "\n".str_replace(' '.getcwd().\DIRECTORY_SEPARATOR, ' ', $e->getTraceAsString());
                echo "\n";

                exit(1);
            }
            if ('legacy' !== $group && self::MODE_WEAK !== $mode) {
                $ref = &self::$deprecations[$group][$msg]['count'];
                ++$ref;
                $ref = &self::$deprecations[$group][$msg][$class.'::'.$method];
                ++$ref;
            }
        } elseif (self::MODE_WEAK !== $mode) {
            $ref = &self::$deprecations[$group][$msg]['count'];
            ++$ref;
        }
        ++self::$deprecations[$group.'Count'];
    }

    /**
     * @param string $str
     * @param bool   $red
     * @param mixed  $mode
     *
     * @return string
     */
    private static function colorize($str, $red, $mode)
    {
        if (!self::hasColorSupport() || self::MODE_WEAK === $mode) {
            return $str;
        }

        $color = $red ? '41;37' : '43;30';

        return "\x1B[{$color}m{$str}\x1B[0m";
    }

    /**
     * @param string[] $groups
     * @param mixed    $mode
     */
    private static function displayDeprecations($groups, $mode)
    {
        $cmp = function ($a, $b) {
            return $b['count'] - $a['count'];
        };

        foreach ($groups as $group) {
            if (self::$deprecations[$group.'Count']) {
                echo "\n", self::colorize(
                    sprintf('%s deprecation notices (%d)', ucfirst($group), self::$deprecations[$group.'Count']),
                    'legacy' !== $group && 'remaining vendor' !== $group,
                    $mode
                ), "\n";

                uasort(self::$deprecations[$group], $cmp);

                foreach (self::$deprecations[$group] as $msg => $notices) {
                    echo "\n  ", $notices['count'], 'x: ', $msg, "\n";

                    arsort($notices);

                    foreach ($notices as $method => $count) {
                        if ('count' !== $method) {
                            echo '    ', $count, 'x in ', preg_replace('/(.*)\\\\(.*?::.*?)$/', '$2 from $1', $method), "\n";
                        }
                    }
                }
            }
        }
        if (!empty($notices)) {
            echo "\n";
        }
    }

    /**
     * @return string
     */
    private static function utilPrefix()
    {
        if ('' !== self::$utilPrefix) {
            return self::$utilPrefix;
        }

        return self::$utilPrefix = class_exists('PHPUnit_Util_ErrorHandler') ? 'PHPUnit_Util_' : 'PHPUnit\Util\\';
    }
}
