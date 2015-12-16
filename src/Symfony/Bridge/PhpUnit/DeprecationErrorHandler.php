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
     * @param int|string|false $mode The reporting mode. Defaults to not allowing any deprecations.
     */
    public static function register($mode = 0)
    {
        if (self::$isRegistered) {
            return;
        }
        if (self::MODE_WEAK !== $mode && (!isset($mode[0]) || '/' !== $mode[0])) {
            $mode = preg_match('/^[1-9][0-9]*$/', $mode) ? (int) $mode : 0;
        }
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
        $deprecationHandler = function ($type, $msg, $file, $line, $context) use (&$deprecations, $mode) {
            if (E_USER_DEPRECATED !== $type) {
                return \PHPUnit_Util_ErrorHandler::handleError($type, $msg, $file, $line, $context);
            }

            $trace = debug_backtrace(PHP_VERSION_ID >= 50400 ? DEBUG_BACKTRACE_IGNORE_ARGS | DEBUG_BACKTRACE_PROVIDE_OBJECT : true);

            $i = count($trace);
            while (isset($trace[--$i]['class']) && ('ReflectionMethod' === $trace[$i]['class'] || 0 === strpos($trace[$i]['class'], 'PHPUnit_'))) {
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
            } else {
                $group = 'other';
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
        } elseif (!isset($mode[0]) || '/' !== $mode[0]) {
            self::$isRegistered = true;
            if (self::hasColorSupport()) {
                $colorize = function ($str, $red) {
                    $color = $red ? '41;37' : '43;30';

                    return "\x1B[{$color}m{$str}\x1B[0m";
                };
            } else {
                $colorize = function ($str) {return $str;};
            }
            register_shutdown_function(function () use ($mode, &$deprecations, $deprecationHandler, $colorize) {
                $currErrorHandler = set_error_handler('var_dump');
                restore_error_handler();

                if ($currErrorHandler !== $deprecationHandler) {
                    echo "\n", $colorize('THE ERROR HANDLER HAS CHANGED!', true), "\n";
                }

                $cmp = function ($a, $b) {
                    return $b['count'] - $a['count'];
                };

                foreach (array('unsilenced', 'remaining', 'legacy', 'other') as $group) {
                    if ($deprecations[$group.'Count']) {
                        echo "\n", $colorize(sprintf('%s deprecation notices (%d)', ucfirst($group), $deprecations[$group.'Count']), 'legacy' !== $group), "\n";

                        uasort($deprecations[$group], $cmp);

                        foreach ($deprecations[$group] as $msg => $notices) {
                            echo "\n", rtrim($msg, '.'), ': ', $notices['count'], "x\n";

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
                if (DeprecationErrorHandler::MODE_WEAK !== $mode && $mode < $deprecations['unsilencedCount'] + $deprecations['remainingCount'] + $deprecations['otherCount']) {
                    exit(1);
                }
            });
        }
    }

    private static function hasColorSupport()
    {
        if ('\\' === DIRECTORY_SEPARATOR) {
            return false !== getenv('ANSICON') || 'ON' === getenv('ConEmuANSI') || 'xterm' === getenv('TERM');
        }

        return defined('STDOUT') && function_exists('posix_isatty') && @posix_isatty(STDOUT);
    }
}
