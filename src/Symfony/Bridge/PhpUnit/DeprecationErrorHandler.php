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
 * Catch deprecation notices and print a summary report at the end of the test suite
 *
 * @author Nicolas Grekas <p@tchwork.com>
 */
class DeprecationErrorHandler
{
    private static $isRegistered = false;

    public static function register($mode = false)
    {
        if (self::$isRegistered) {
            return;
        }
        $deprecations = array(
            'remainingCount' => 0,
            'legacyCount' => 0,
            'otherCount' => 0,
            'remaining' => array(),
            'legacy' => array(),
            'other' => array(),
        );
        $deprecationHandler = function ($type, $msg, $file, $line, $context) use (&$deprecations) {
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

                $group = 0 === strpos($method, 'testLegacy') || 0 === strpos($method, 'provideLegacy') || 0 === strpos($method, 'getLegacy') || strpos($class, '\Legacy') || in_array('legacy', \PHPUnit_Util_Test::getGroups($class, $method), true) ? 'legacy' : 'remaining';

                if ('legacy' === $group && 0 === (error_reporting() & E_USER_DEPRECATED)) {
                    $ref =& $deprecations[$group]['Silenced']['count'];
                    ++$ref;
                } else {
                    $ref =& $deprecations[$group][$msg]['count'];
                    ++$ref;
                    $ref =& $deprecations[$group][$msg][$class.'::'.$method];
                    ++$ref;
                }
            } else {
                $group = 'other';
                $ref =& $deprecations[$group][$msg]['count'];
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

                foreach (array('remaining', 'legacy', 'other') as $group) {
                    if ($deprecations[$group]) {
                        echo "\n", $colorize(sprintf('%s deprecation notices (%d)', ucfirst($group), $deprecations[$group.'Count']), 'legacy' !== $group), "\n";

                        uasort($deprecations[$group], $cmp);

                        foreach ($deprecations[$group] as $msg => $notices) {
                            echo "\n", $msg, ': ', $notices['count'], "x\n";

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
                if ('weak' !== $mode) {
                    if ($deprecations['remaining'] || $deprecations['other']) {
                        exit(1);
                    }
                    if ('strict' === $mode && $deprecations['legacy'] && $deprecations['legacyCount'] !== $ref =& $deprecations['legacy']['Silenced']['count']) {
                        exit(1);
                    }
                }
            });
        }
    }

    private static function hasColorSupport()
    {
        if ('\\' === DIRECTORY_SEPARATOR) {
            return false !== getenv('ANSICON') || 'ON' === getenv('ConEmuANSI');
        }

        return defined('STDOUT') && function_exists('posix_isatty') && @posix_isatty(STDOUT);
    }
}
