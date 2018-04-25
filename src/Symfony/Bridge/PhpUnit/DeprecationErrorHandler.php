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

    private static $isRegistered = false;

    /**
     * Registers and configures the deprecation handler.
     *
     * The following reporting modes are supported:
     * - use "weak" to hide the deprecation report but keep a global count;
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

        $getMode = function () use ($mode) {
            static $memoizedMode = false;

            if (false !== $memoizedMode) {
                return $memoizedMode;
            }
            if (false === $mode) {
                $mode = getenv('SYMFONY_DEPRECATIONS_HELPER');
            }
            if (DeprecationErrorHandler::MODE_WEAK !== $mode && (!isset($mode[0]) || '/' !== $mode[0])) {
                $mode = preg_match('/^[1-9][0-9]*$/', $mode) ? (int) $mode : 0;
            }

            return $memoizedMode = $mode;
        };

        $deprecations = array(
            'unsilencedCount' => 0,
            'remainingCount' => 0,
            'legacyCount' => 0,
            'otherCount' => 0,
            'unsilenced' => array(),
            'remaining' => array(),
            'legacy' => array(),
            'other' => array(),
        );
        $deprecationHandler = function ($type, $msg, $file, $line, $context = array()) use (&$deprecations, $getMode) {
            if (E_USER_DEPRECATED !== $type) {
                return \PHPUnit_Util_ErrorHandler::handleError($type, $msg, $file, $line, $context);
            }

            $mode = $getMode();
            $trace = debug_backtrace(true);
            $group = 'other';

            $i = count($trace);
            while (1 < $i && (!isset($trace[--$i]['class']) || ('ReflectionMethod' === $trace[$i]['class'] || 0 === strpos($trace[$i]['class'], 'PHPUnit_')))) {
                // No-op
            }

            if (isset($trace[$i]['object']) || isset($trace[$i]['class'])) {
                $class = isset($trace[$i]['object']) ? get_class($trace[$i]['object']) : $trace[$i]['class'];
                $method = $trace[$i]['function'];

                if (0 !== error_reporting()) {
                    $group = 'unsilenced';
                } elseif (0 === strpos($method, 'testLegacy')
                    || 0 === strpos($method, 'provideLegacy')
                    || 0 === strpos($method, 'getLegacy')
                    || strpos($class, '\Legacy')
                    || in_array('legacy', \PHPUnit_Util_Test::getGroups($class, $method), true)
                ) {
                    $group = 'legacy';
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
            if (array('PHPUnit_Util_ErrorHandler', 'handleError') === $oldErrorHandler) {
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

                $displayDeprecations = function ($deprecations) use ($colorize, $cmp) {
                    foreach (array('unsilenced', 'remaining', 'legacy', 'other') as $group) {
                        if ($deprecations[$group.'Count']) {
                            echo "\n", $colorize(sprintf('%s deprecation notices (%d)', ucfirst($group), $deprecations[$group.'Count']), 'legacy' !== $group), "\n";

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
        if (!defined('STDOUT')) {
            return false;
        }

        if (DIRECTORY_SEPARATOR === '\\') {
            return (function_exists('sapi_windows_vt100_support')
                && sapi_windows_vt100_support(STDOUT))
                || false !== getenv('ANSICON')
                || 'ON' === getenv('ConEmuANSI')
                || 'xterm' === getenv('TERM');
        }

        if (function_exists('stream_isatty')) {
            return stream_isatty(STDOUT);
        }

        if (function_exists('posix_isatty')) {
            return posix_isatty(STDOUT);
        }

        $stat = fstat(STDOUT);
        // Check if formatted mode is S_IFCHR
        return $stat ? 0020000 === ($stat['mode'] & 0170000) : false;
    }
}
