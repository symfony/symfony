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

use PHPUnit\Framework\TestResult;
use PHPUnit\Runner\ErrorHandler;
use PHPUnit\Util\Error\Handler;
use PHPUnit\Util\ErrorHandler as UtilErrorHandler;
use Symfony\Bridge\PhpUnit\DeprecationErrorHandler\Configuration;
use Symfony\Bridge\PhpUnit\DeprecationErrorHandler\Deprecation;
use Symfony\Bridge\PhpUnit\DeprecationErrorHandler\DeprecationGroup;
use Symfony\Component\ErrorHandler\DebugClassLoader;

/**
 * Catch deprecation notices and print a summary report at the end of the test suite.
 *
 * @author Nicolas Grekas <p@tchwork.com>
 */
class DeprecationErrorHandler
{
    public const MODE_DISABLED = 'disabled';
    public const MODE_WEAK = 'max[total]=999999&verbose=0';
    public const MODE_STRICT = 'max[total]=0';

    private $mode;
    private $configuration;

    /**
     * @var DeprecationGroup[]
     */
    private $deprecationGroups = [];

    private static $isRegistered = false;
    private static $errorHandler;

    public function __construct()
    {
        $this->resetDeprecationGroups();
    }

    /**
     * Registers and configures the deprecation handler.
     *
     * The mode is a query string with options:
     *  - "disabled" to enable/disable the deprecation handler
     *  - "verbose" to enable/disable displaying the deprecation report
     *  - "quiet" to disable displaying the deprecation report only for some groups (i.e. quiet[]=other)
     *  - "max" to configure the number of deprecations to allow before exiting with a non-zero
     *    status code; it's an array with keys "total", "self", "direct" and "indirect"
     *
     * The default mode is "max[total]=0&verbose=1".
     *
     * The mode can alternatively be "/some-regexp/" to stop the test suite whenever
     * a deprecation message matches the given regular expression.
     *
     * @param int|string|false $mode The reporting mode, defaults to not allowing any deprecations
     */
    public static function register($mode = 0)
    {
        if (self::$isRegistered) {
            return;
        }

        $handler = new self();
        $oldErrorHandler = set_error_handler([$handler, 'handleError']);

        if (null !== $oldErrorHandler) {
            restore_error_handler();

            if (
                $oldErrorHandler instanceof UtilErrorHandler
                || [UtilErrorHandler::class, 'handleError'] === $oldErrorHandler
                || $oldErrorHandler instanceof ErrorHandler
                || [ErrorHandler::class, 'handleError'] === $oldErrorHandler
            ) {
                restore_error_handler();
                self::register($mode);
            }
        } else {
            $handler->mode = $mode;
            self::$isRegistered = true;
            register_shutdown_function([$handler, 'shutdown']);
        }
    }

    public static function collectDeprecations($outputFile)
    {
        $deprecations = [];
        $previousErrorHandler = set_error_handler(function ($type, $msg, $file, $line, $context = []) use (&$deprecations, &$previousErrorHandler) {
            if (\E_USER_DEPRECATED !== $type && \E_DEPRECATED !== $type && (\E_WARNING !== $type || false === strpos($msg, '" targeting switch is equivalent to "break'))) {
                if ($previousErrorHandler) {
                    return $previousErrorHandler($type, $msg, $file, $line, $context);
                }

                return \call_user_func(self::getPhpUnitErrorHandler(), $type, $msg, $file, $line, $context);
            }

            $filesStack = [];
            foreach (debug_backtrace() as $frame) {
                if (!isset($frame['file']) || \in_array($frame['function'], ['require', 'require_once', 'include', 'include_once'], true)) {
                    continue;
                }

                $filesStack[] = $frame['file'];
            }

            $deprecations[] = [error_reporting() & $type, $msg, $file, $filesStack];

            return null;
        });

        register_shutdown_function(function () use ($outputFile, &$deprecations) {
            file_put_contents($outputFile, serialize($deprecations));
        });
    }

    /**
     * @internal
     */
    public function handleError($type, $msg, $file, $line, $context = [])
    {
        if ((\E_USER_DEPRECATED !== $type && \E_DEPRECATED !== $type && (\E_WARNING !== $type || false === strpos($msg, '" targeting switch is equivalent to "break'))) || !$this->getConfiguration()->isEnabled()) {
            return \call_user_func(self::getPhpUnitErrorHandler(), $type, $msg, $file, $line, $context);
        }

        $trace = debug_backtrace();

        if (isset($trace[1]['function'], $trace[1]['args'][0]) && ('trigger_error' === $trace[1]['function'] || 'user_error' === $trace[1]['function'])) {
            $msg = $trace[1]['args'][0];
        }

        $deprecation = new Deprecation($msg, $trace, $file, \E_DEPRECATED === $type);
        if ($deprecation->isMuted()) {
            return null;
        }
        if ($this->getConfiguration()->isIgnoredDeprecation($deprecation)) {
            return null;
        }
        if ($this->getConfiguration()->isBaselineDeprecation($deprecation)) {
            return null;
        }

        $msg = $deprecation->getMessage();

        if (\E_DEPRECATED !== $type && (error_reporting() & $type)) {
            $group = 'unsilenced';
        } elseif ($deprecation->isLegacy()) {
            $group = 'legacy';
        } else {
            $group = [
                Deprecation::TYPE_SELF => 'self',
                Deprecation::TYPE_DIRECT => 'direct',
                Deprecation::TYPE_INDIRECT => 'indirect',
                Deprecation::TYPE_UNDETERMINED => 'other',
            ][$deprecation->getType()];
        }

        if ($this->getConfiguration()->shouldDisplayStackTrace($msg)) {
            echo "\n".ucfirst($group).' '.$deprecation->toString();

            exit(1);
        }

        if ('legacy' === $group) {
            $this->deprecationGroups[$group]->addNotice();
        } elseif ($deprecation->originatesFromAnObject()) {
            $class = $deprecation->originatingClass();
            $method = $deprecation->originatingMethod();
            $this->deprecationGroups[$group]->addNoticeFromObject($msg, $class, $method);
        } else {
            $this->deprecationGroups[$group]->addNoticeFromProceduralCode($msg);
        }

        return null;
    }

    /**
     * @internal
     */
    public function shutdown()
    {
        $configuration = $this->getConfiguration();

        if ($configuration->isInRegexMode()) {
            return;
        }

        if (class_exists(DebugClassLoader::class, false)) {
            DebugClassLoader::checkClasses();
        }
        $currErrorHandler = set_error_handler('is_int');
        restore_error_handler();

        if ($currErrorHandler !== [$this, 'handleError']) {
            echo "\n", self::colorize('THE ERROR HANDLER HAS CHANGED!', true), "\n";
        }

        $groups = array_keys($this->deprecationGroups);

        // store failing status
        $isFailing = !$configuration->tolerates($this->deprecationGroups);

        $this->displayDeprecations($groups, $configuration);

        $this->resetDeprecationGroups();

        register_shutdown_function(function () use ($isFailing, $groups, $configuration) {
            foreach ($this->deprecationGroups as $group) {
                if ($group->count() > 0) {
                    echo "Shutdown-time deprecations:\n";
                    break;
                }
            }

            $isFailingAtShutdown = !$configuration->tolerates($this->deprecationGroups);
            $this->displayDeprecations($groups, $configuration);

            if ($configuration->isGeneratingBaseline()) {
                $configuration->writeBaseline();
            }

            if ($isFailing || $isFailingAtShutdown) {
                exit(1);
            }
        });
    }

    private function resetDeprecationGroups()
    {
        $this->deprecationGroups = [
            'unsilenced' => new DeprecationGroup(),
            'self' => new DeprecationGroup(),
            'direct' => new DeprecationGroup(),
            'indirect' => new DeprecationGroup(),
            'legacy' => new DeprecationGroup(),
            'other' => new DeprecationGroup(),
        ];
    }

    private function getConfiguration()
    {
        if (null !== $this->configuration) {
            return $this->configuration;
        }
        if (false === $mode = $this->mode) {
            $mode = $_SERVER['SYMFONY_DEPRECATIONS_HELPER'] ?? $_ENV['SYMFONY_DEPRECATIONS_HELPER'] ?? getenv('SYMFONY_DEPRECATIONS_HELPER');
        }
        if ('strict' === $mode) {
            return $this->configuration = Configuration::inStrictMode();
        }
        if (self::MODE_DISABLED === $mode) {
            return $this->configuration = Configuration::inDisabledMode();
        }
        if ('weak' === $mode) {
            return $this->configuration = Configuration::inWeakMode();
        }
        if (isset($mode[0]) && '/' === $mode[0]) {
            return $this->configuration = Configuration::fromRegex($mode);
        }

        if (preg_match('/^[1-9][0-9]*$/', (string) $mode)) {
            return $this->configuration = Configuration::fromNumber($mode);
        }

        if (!$mode) {
            return $this->configuration = Configuration::fromNumber(0);
        }

        return $this->configuration = Configuration::fromUrlEncodedString((string) $mode);
    }

    private static function colorize(string $str, bool $red): string
    {
        if (!self::hasColorSupport()) {
            return $str;
        }

        $color = $red ? '41;37' : '43;30';

        return "\x1B[{$color}m{$str}\x1B[0m";
    }

    /**
     * @param string[] $groups
     */
    private function displayDeprecations(array $groups, Configuration $configuration): void
    {
        $cmp = function ($a, $b) {
            return $b->count() - $a->count();
        };

        if ($configuration->shouldWriteToLogFile()) {
            if (false === $handle = @fopen($file = $configuration->getLogFile(), 'a')) {
                throw new \InvalidArgumentException(sprintf('The configured log file "%s" is not writeable.', $file));
            }
        } else {
            $handle = fopen('php://output', 'w');
        }

        foreach ($groups as $group) {
            if ($this->deprecationGroups[$group]->count()) {
                $deprecationGroupMessage = sprintf(
                    '%s deprecation notices (%d)',
                    \in_array($group, ['direct', 'indirect', 'self'], true) ? "Remaining $group" : ucfirst($group),
                    $this->deprecationGroups[$group]->count()
                );
                if ($configuration->shouldWriteToLogFile()) {
                    fwrite($handle, "\n$deprecationGroupMessage\n");
                } else {
                    fwrite($handle, "\n".self::colorize($deprecationGroupMessage, 'legacy' !== $group && 'indirect' !== $group)."\n");
                }

                // Skip the verbose output if the group is quiet and not failing according to its threshold:
                if ('legacy' !== $group && !$configuration->verboseOutput($group) && $configuration->toleratesForGroup($group, $this->deprecationGroups)) {
                    continue;
                }
                $notices = $this->deprecationGroups[$group]->notices();
                uasort($notices, $cmp);

                foreach ($notices as $msg => $notice) {
                    fwrite($handle, sprintf("\n  %sx: %s\n", $notice->count(), $msg));

                    $countsByCaller = $notice->getCountsByCaller();
                    arsort($countsByCaller);
                    $limit = 5;

                    foreach ($countsByCaller as $method => $count) {
                        if ('count' !== $method) {
                            if (!$limit--) {
                                fwrite($handle, "    ...\n");
                                break;
                            }
                            fwrite($handle, sprintf("    %dx in %s\n", $count, preg_replace('/(.*)\\\\(.*?::.*?)$/', '$2 from $1', $method)));
                        }
                    }
                }
            }
        }

        if (!empty($notices)) {
            fwrite($handle, "\n");
        }
    }

    private static function getPhpUnitErrorHandler(): callable
    {
        if (!$eh = self::$errorHandler) {
            if (class_exists(Handler::class)) {
                $eh = self::$errorHandler = Handler::class;
            } elseif (method_exists(UtilErrorHandler::class, '__invoke')) {
                $eh = self::$errorHandler = UtilErrorHandler::class;
            } elseif (method_exists(ErrorHandler::class, '__invoke')) {
                $eh = self::$errorHandler = ErrorHandler::class;
            } else {
                return self::$errorHandler = 'PHPUnit\Util\ErrorHandler::handleError';
            }
        }

        if ('PHPUnit\Util\ErrorHandler::handleError' === $eh) {
            return $eh;
        }

        foreach (debug_backtrace(\DEBUG_BACKTRACE_PROVIDE_OBJECT | \DEBUG_BACKTRACE_IGNORE_ARGS) as $frame) {
            if (!isset($frame['object'])) {
                continue;
            }

            if ($frame['object'] instanceof TestResult) {
                return new $eh(
                    $frame['object']->getConvertDeprecationsToExceptions(),
                    $frame['object']->getConvertErrorsToExceptions(),
                    $frame['object']->getConvertNoticesToExceptions(),
                    $frame['object']->getConvertWarningsToExceptions()
                );
            } elseif (ErrorHandler::class === $eh && $frame['object'] instanceof TestCase) {
                return function (int $errorNumber, string $errorString, string $errorFile, int $errorLine) {
                    ErrorHandler::instance()($errorNumber, $errorString, $errorFile, $errorLine);

                    return true;
                };
            }
        }

        return function () { return false; };
    }

    /**
     * Returns true if STDOUT is defined and supports colorization.
     *
     * Reference: Composer\XdebugHandler\Process::supportsColor
     * https://github.com/composer/xdebug-handler
     */
    private static function hasColorSupport(): bool
    {
        if (!\defined('STDOUT')) {
            return false;
        }

        // Follow https://no-color.org/
        if (isset($_SERVER['NO_COLOR']) || false !== getenv('NO_COLOR')) {
            return false;
        }

        // Detect msysgit/mingw and assume this is a tty because detection
        // does not work correctly, see https://github.com/composer/composer/issues/9690
        if (!@stream_isatty(\STDOUT) && !\in_array(strtoupper((string) getenv('MSYSTEM')), ['MINGW32', 'MINGW64'], true)) {
            return false;
        }

        if ('\\' === \DIRECTORY_SEPARATOR && @sapi_windows_vt100_support(\STDOUT)) {
            return true;
        }

        if ('Hyper' === getenv('TERM_PROGRAM')
            || false !== getenv('COLORTERM')
            || false !== getenv('ANSICON')
            || 'ON' === getenv('ConEmuANSI')
        ) {
            return true;
        }

        if ('dumb' === $term = (string) getenv('TERM')) {
            return false;
        }

        // See https://github.com/chalk/supports-color/blob/d4f413efaf8da045c5ab440ed418ef02dbb28bf1/index.js#L157
        return preg_match('/^((screen|xterm|vt100|vt220|putty|rxvt|ansi|cygwin|linux).*)|(.*-256(color)?(-bce)?)$/', $term);
    }
}
