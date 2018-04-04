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

    private static $isRegistered = false;

    /**
     * Registers and configures the deprecation handler.
     *
     * The following reporting modes are supported:
     * - use "weak" to hide the deprecation report but keep a global count;
     * - use "weak_vendors" to act as "weak" but only for vendors;
     * - use "/some-regexp/" to stop the test suite whenever a deprecation
     *   message matches the given regular expression;
     * - use a number to define the upper bound of allowed deprecations,
     *   making the test suite fail whenever more notices are trigerred.
     *
     * @param int|string|false $mode The reporting mode, defaults to not allowing any deprecations
     */
    public static function register($mode = 0)
    {
        if (self::$isRegistered) {
            return;
        }

        $UtilPrefix = class_exists('PHPUnit_Util_ErrorHandler') ? 'PHPUnit_Util_' : 'PHPUnit\Util\\';

        $getMode = function () use ($mode) {
            static $memoizedMode = false;

            if (false !== $memoizedMode) {
                return $memoizedMode;
            }
            if (false === $mode) {
                $mode = getenv('SYMFONY_DEPRECATIONS_HELPER');
            }
            if (DeprecationErrorHandler::MODE_WEAK !== $mode && DeprecationErrorHandler::MODE_WEAK_VENDORS !== $mode && (!isset($mode[0]) || '/' !== $mode[0])) {
                $mode = preg_match('/^[1-9][0-9]*$/', $mode) ? (int) $mode : 0;
            }

            return $memoizedMode = $mode;
        };

        $inVendors = function ($path) {
            /** @var string[] absolute paths to vendor directories */
            static $vendors;
            if (null === $vendors) {
                foreach (get_declared_classes() as $class) {
                    if ('C' === $class[0] && 0 === strpos($class, 'ComposerAutoloaderInit')) {
                        $r = new \ReflectionClass($class);
                        $v = dirname(dirname($r->getFileName()));
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
                if (0 === strpos($realPath, $vendor) && false !== strpbrk(substr($realPath, strlen($vendor), 1), '/'.DIRECTORY_SEPARATOR)) {
                    return true;
                }
            }

            return false;
        };

        $deprecations = array(
            'unsilencedCount' => 0,
            'remainingCount' => 0,
            'legacyCount' => 0,
            'otherCount' => 0,
            'remaining vendorCount' => 0,
            'unsilenced' => array(),
            'remaining' => array(),
            'legacy' => array(),
            'other' => array(),
            'remaining vendor' => array(),
        );
        $deprecationHandler = function ($type, $msg, $file, $line, $context = array()) use (&$deprecations, $getMode, $UtilPrefix, $inVendors) {
            $mode = $getMode();
            if ((E_USER_DEPRECATED !== $type && E_DEPRECATED !== $type) || DeprecationErrorHandler::MODE_DISABLED === $mode) {
                $ErrorHandler = $UtilPrefix.'ErrorHandler';

                return $ErrorHandler::handleError($type, $msg, $file, $line, $context);
            }

            $trace = debug_backtrace(true);
            $group = 'other';
            $isVendor = DeprecationErrorHandler::MODE_WEAK_VENDORS === $mode && $inVendors($file);

            $i = count($trace);
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
                    $isVendor = DeprecationErrorHandler::MODE_WEAK_VENDORS === $mode && isset($parsedMsg['triggering_file']) && $inVendors($parsedMsg['triggering_file']);
                } else {
                    $class = isset($trace[$i]['object']) ? get_class($trace[$i]['object']) : $trace[$i]['class'];
                    $method = $trace[$i]['function'];
                }

                $Test = $UtilPrefix.'Test';

                if (0 !== error_reporting()) {
                    $group = 'unsilenced';
                } elseif (0 === strpos($method, 'testLegacy')
                    || 0 === strpos($method, 'provideLegacy')
                    || 0 === strpos($method, 'getLegacy')
                    || strpos($class, '\Legacy')
                    || in_array('legacy', $Test::getGroups($class, $method), true)
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
                    $r->setValue($e, array_slice($trace, 1, $i));

                    echo "\n".ucfirst($group).' deprecation triggered by '.$class.'::'.$method.':';
                    echo "\n".$msg;
                    echo "\nStack trace:";
                    echo "\n".str_replace(' '.getcwd().DIRECTORY_SEPARATOR, ' ', $e->getTraceAsString());
                    echo "\n";

                    exit(1);
                }
                if ('legacy' !== $group && DeprecationErrorHandler::MODE_WEAK !== $mode) {
                    $ref = &$deprecations[$group][$msg]['count'];
                    ++$ref;
                    $ref = &$deprecations[$group][$msg][$class.'::'.$method];
                    ++$ref;
                }
            } elseif (DeprecationErrorHandler::MODE_WEAK !== $mode) {
                $ref = &$deprecations[$group][$msg]['count'];
                ++$ref;
            }
            ++$deprecations[$group.'Count'];
        };
        $oldErrorHandler = set_error_handler($deprecationHandler);

        if (null !== $oldErrorHandler) {
            restore_error_handler();
            if (array($UtilPrefix.'ErrorHandler', 'handleError') === $oldErrorHandler) {
                restore_error_handler();
                self::register($mode);
            }
        } else {
            self::$isRegistered = true;
            if (self::hasColorSupport()) {
                $colorize = function ($str, $red) {
                    $color = $red ? '41;37' : '43;30';

                    return "\x1B[{$color}m{$str}\x1B[0m";
                };
            } else {
                $colorize = function ($str) { return $str; };
            }
            register_shutdown_function(function () use ($getMode, &$deprecations, $deprecationHandler, $colorize) {
                $mode = $getMode();
                if (isset($mode[0]) && '/' === $mode[0]) {
                    return;
                }
                $currErrorHandler = set_error_handler('var_dump');
                restore_error_handler();

                if (DeprecationErrorHandler::MODE_WEAK === $mode) {
                    $colorize = function ($str) { return $str; };
                }
                if ($currErrorHandler !== $deprecationHandler) {
                    echo "\n", $colorize('THE ERROR HANDLER HAS CHANGED!', true), "\n";
                }

                $cmp = function ($a, $b) {
                    return $b['count'] - $a['count'];
                };

                $groups = array('unsilenced', 'remaining');
                if (DeprecationErrorHandler::MODE_WEAK_VENDORS === $mode) {
                    $groups[] = 'remaining vendor';
                }
                array_push($groups, 'legacy', 'other');

                $displayDeprecations = function ($deprecations) use ($colorize, $cmp, $groups) {
                    foreach ($groups as $group) {
                        if ($deprecations[$group.'Count']) {
                            echo "\n", $colorize(
                                sprintf('%s deprecation notices (%d)', ucfirst($group), $deprecations[$group.'Count']),
                                'legacy' !== $group && 'remaining vendor' !== $group
                            ), "\n";

                            uasort($deprecations[$group], $cmp);

                            foreach ($deprecations[$group] as $msg => $notices) {
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
                };

                $displayDeprecations($deprecations);

                // store failing status
                $isFailing = DeprecationErrorHandler::MODE_WEAK !== $mode && $mode < $deprecations['unsilencedCount'] + $deprecations['remainingCount'] + $deprecations['otherCount'];

                // reset deprecations array
                foreach ($deprecations as $group => $arrayOrInt) {
                    $deprecations[$group] = is_int($arrayOrInt) ? 0 : array();
                }

                register_shutdown_function(function () use (&$deprecations, $isFailing, $displayDeprecations, $mode) {
                    foreach ($deprecations as $group => $arrayOrInt) {
                        if (0 < (is_int($arrayOrInt) ? $arrayOrInt : count($arrayOrInt))) {
                            echo "Shutdown-time deprecations:\n";
                            break;
                        }
                    }
                    $displayDeprecations($deprecations);
                    if ($isFailing || DeprecationErrorHandler::MODE_WEAK !== $mode && $mode < $deprecations['unsilencedCount'] + $deprecations['remainingCount'] + $deprecations['otherCount']) {
                        exit(1);
                    }
                });
            });
        }
    }

    public static function collectDeprecations($outputFile)
    {
        $deprecations = array();
        $previousErrorHandler = set_error_handler(function ($type, $msg, $file, $line, $context = array()) use (&$deprecations, &$previousErrorHandler) {
            if (E_USER_DEPRECATED !== $type && E_DEPRECATED !== $type) {
                if ($previousErrorHandler) {
                    return $previousErrorHandler($type, $msg, $file, $line, $context);
                }
                static $autoload = true;

                $ErrorHandler = class_exists('PHPUnit_Util_ErrorHandler', $autoload) ? 'PHPUnit_Util_ErrorHandler' : 'PHPUnit\Util\ErrorHandler';
                $autoload = false;

                return $ErrorHandler::handleError($type, $msg, $file, $line, $context);
            }
            $deprecations[] = array(error_reporting(), $msg, $file);
        });

        register_shutdown_function(function () use ($outputFile, &$deprecations) {
            file_put_contents($outputFile, serialize($deprecations));
        });
    }

    private static function hasColorSupport()
    {
        if ('\\' === DIRECTORY_SEPARATOR) {
            return
                defined('STDOUT') && function_exists('sapi_windows_vt100_support') && sapi_windows_vt100_support(STDOUT)
                || '10.0.10586' === PHP_WINDOWS_VERSION_MAJOR.'.'.PHP_WINDOWS_VERSION_MINOR.'.'.PHP_WINDOWS_VERSION_BUILD
                || false !== getenv('ANSICON')
                || 'ON' === getenv('ConEmuANSI')
                || 'xterm' === getenv('TERM');
        }

        return defined('STDOUT') && function_exists('posix_isatty') && @posix_isatty(STDOUT);
    }
}
