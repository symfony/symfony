<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bridge\PhpUnit\Legacy;

use Doctrine\Common\Annotations\AnnotationRegistry;
use PHPUnit\Event\Facade;
use PHPUnit\Framework\Assert;
use PHPUnit\Framework\AssertionFailedError;
use PHPUnit\Framework\ExpectationFailedException;
use PHPUnit\Framework\RiskyTestError;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\TestResult;
use PHPUnit\Framework\TestSuite;
use PHPUnit\Metadata\Api\Groups;
use PHPUnit\Runner\BaseTestRunner;
use PHPUnit\Util\Blacklist;
use PHPUnit\Util\ExcludeList;
use PHPUnit\Util\Test;
use Symfony\Bridge\PhpUnit\ClockMock;
use Symfony\Bridge\PhpUnit\DeprecationErrorHandler;
use Symfony\Bridge\PhpUnit\DeprecationErrorHandler\Configuration;
use Symfony\Bridge\PhpUnit\DeprecationErrorHandler\Deprecation;
use Symfony\Bridge\PhpUnit\DeprecationErrorHandler\DeprecationGroup;
use Symfony\Bridge\PhpUnit\DnsMock;
use Symfony\Bridge\PhpUnit\ExpectDeprecationTrait;
use Symfony\Bridge\PhpUnit\Legacy\EventSubscribers\ApplicationFinishedSubscriber;
use Symfony\Bridge\PhpUnit\Legacy\EventSubscribers\DeprecationTriggeredSubscriber;
use Symfony\Bridge\PhpUnit\Legacy\EventSubscribers\PhpDeprecationTriggeredSubscriber;
use Symfony\Bridge\PhpUnit\Legacy\EventSubscribers\TestFinishedSubscriber;
use Symfony\Bridge\PhpUnit\Legacy\EventSubscribers\TestRunnerFinishedSubscriber;
use Symfony\Component\ErrorHandler\DebugClassLoader;

/**
 * @internal
 */
class SymfonyTestEventsCollectorForV10_2
{
    public const MODE_DISABLED = 'disabled';

    private static ?self $instance = NULL;
    private static bool $globallyEnabled = false;
    private array $expectedDeprecations = [];
    private array $gatheredDeprecations = [];
    private $state = -1;
    private bool $skippedFile = false;
    private array $wasSkipped = [];
    private array $isSkipped = [];
    private $runsInSeparateProcess = false;
    private $checkNumAssertions = false;

    private $mode;
    private $configuration;
    /**
     * @var DeprecationGroup[]
     */
    private array $deprecationGroups = [];
    private array $errors = [];
    private array $failures = [];
    private int $bridgeExitStatus = 0;

    /**
     * @param int|string|false $mode The reporting mode, defaults to not allowing any deprecations
     * @param array $mockedNamespaces List of namespaces, indexed by mocked features (time-sensitive or dns-sensitive)
     */
    // REF - DeprecationErrorHandler::__construct()
    // REF - SymfonyTestsListenerTrait::__construct()
    public function __construct(int|string|false $mode, array $mockedNamespaces)
    {
        $facade = Facade::instance();
        $facade->registerSubscriber(new ApplicationFinishedSubscriber($this));
        $facade->registerSubscriber(new DeprecationTriggeredSubscriber($this));
        $facade->registerSubscriber(new PhpDeprecationTriggeredSubscriber($this));
        $facade->registerSubscriber(new TestFinishedSubscriber($this));
        $facade->registerSubscriber(new TestRunnerFinishedSubscriber($this));

        if (class_exists(ExcludeList::class)) {
            (new ExcludeList())->getExcludedDirectories();
            ExcludeList::addDirectory(\dirname((new \ReflectionClass(__CLASS__))->getFileName(), 2));
        } elseif (method_exists(Blacklist::class, 'addDirectory')) {
            (new BlackList())->getBlacklistedDirectories();
            Blacklist::addDirectory(\dirname((new \ReflectionClass(__CLASS__))->getFileName(), 2));
        } else {
            Blacklist::$blacklistedClassNames[__CLASS__] = 2;
        }

        $enableDebugClassLoader = class_exists(DebugClassLoader::class);

        foreach ($mockedNamespaces as $type => $namespaces) {
            if (!\is_array($namespaces)) {
                $namespaces = [$namespaces];
            }
            if ('time-sensitive' === $type) {
                foreach ($namespaces as $ns) {
                    ClockMock::register($ns.'\DummyClass');
                }
            }
            if ('dns-sensitive' === $type) {
                foreach ($namespaces as $ns) {
                    DnsMock::register($ns.'\DummyClass');
                }
            }
            if ('debug-class-loader' === $type) {
                $enableDebugClassLoader = $namespaces && $namespaces[0];
            }
        }
        if ($enableDebugClassLoader) {
            DebugClassLoader::enable();
        }
        if (self::$globallyEnabled) {
            $this->state = -2;
        } else {
            self::$globallyEnabled = true;
        }

        $this->resetDeprecationGroups();

        $this->mode = $mode;
    }

    /**
     * Initializes the deprecation bridge event collector.
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
     *
     * @throws \PHPUnit\Event\EventFacadeIsSealedException
     * @throws \PHPUnit\Util\Exception
     * @throws \PHPUnit\Event\UnknownSubscriberTypeException
     * @throws \RuntimeException
     */
    public static function init(int|string|false $mode = 0, array $mockedNamespaces = []): void
    {
        if (self::$instance === NULL) {
            self::$instance = new self($mode, $mockedNamespaces);
        }
    }

    public static function instance(): self
    {
        return self::$instance;
    }

    public static function isEnabled(): bool
    {
        return self::$instance !== NULL;
    }

    public function __sleep()
    {
        throw new \BadMethodCallException('Cannot serialize '.__CLASS__);
    }

    public function __wakeup()
    {
        throw new \BadMethodCallException('Cannot unserialize '.__CLASS__);
    }

    public function addExpectedDeprecation(string $message): void
    {
        $this->expectedDeprecations[] = $message;
    }

    // REF - SymfonyTestsListenerTrait::endTest()
    public function onTestFinished($event): void
    {
//        public function endTest($test, $time)
        if ($file = getenv('SYMFONY_EXPECTED_DEPRECATIONS_SERIALIZE')) {
            putenv('SYMFONY_EXPECTED_DEPRECATIONS_SERIALIZE');
            $expectedDeprecations = file_get_contents($file);
            if ($expectedDeprecations) {
                $this->expectedDeprecations = array_merge($this->expectedDeprecations, unserialize($expectedDeprecations));
//                if (!self::$previousErrorHandler) {
//                    self::$previousErrorHandler = set_error_handler([self::class, 'handleError']);
//                }
            }
        }

        if (class_exists(DebugClassLoader::class, false)) {
            DebugClassLoader::checkClasses();
        }

        $className = $event->test()->className();
        $methodName = $event->test()->methodName();
        $groups = (new Groups)->groups($className, $methodName, false);

//        if ($this->checkNumAssertions) {
//            $assertions = \count($this->expectedDeprecations) + $event->numberOfAssertionsPerformed();
//            if ($event->test()->doesNotPerformAssertions() && $assertions > 0) {
//                $event->test()->getTestResultObject()->addFailure($event->test(), new RiskyTestError(sprintf('This test is annotated with "@doesNotPerformAssertions", but performed %s assertions', $assertions)), $time);
//            } elseif ($assertions === 0 && !$event->test()->doesNotPerformAssertions() && $event->test()->getTestResultObject()->noneSkipped()) {
//                $event->test()->getTestResultObject()->addFailure($event->test(), new RiskyTestError('This test did not perform any assertions'), $time);
//            }
//
//            $this->checkNumAssertions = false;
//        }

//        if ($this->runsInSeparateProcess) {
//            $deprecations = file_get_contents($this->runsInSeparateProcess);
//            unlink($this->runsInSeparateProcess);
//            putenv('SYMFONY_DEPRECATIONS_SERIALIZE');
//            foreach ($deprecations ? unserialize($deprecations) : [] as $deprecation) {
//                $error = serialize(['deprecation' => $deprecation[1], 'class' => $className, 'method' => $event->test()->getName(false), 'triggering_file' => $deprecation[2] ?? null, 'files_stack' => $deprecation[3] ?? []]);
//                if ($deprecation[0]) {
//                    // unsilenced on purpose
//                    trigger_error($error, \E_USER_DEPRECATED);
//                } else {
//                    @trigger_error($error, \E_USER_DEPRECATED);
//                }
//            }
//            $this->runsInSeparateProcess = false;
//        }

        if ($this->expectedDeprecations) {
//            if (!\in_array($event->test()->getStatus(), [BaseTestRunner::STATUS_SKIPPED, BaseTestRunner::STATUS_INCOMPLETE], true)) {
//                $event->test()->addToAssertionCount(\count($this->expectedDeprecations));
//            }

//            restore_error_handler();

            if (!\in_array('legacy', $groups, true)) {
//                $event->test()->getTestResultObject()->addError($event->test(), new AssertionFailedError('Only tests with the `@group legacy` annotation can expect a deprecation.'), 0);
                $this->errors['Only tests with the `@group legacy` annotation can expect a deprecation.'][$className . '::' . $methodName] = 1;
//            } elseif (!\in_array($event->test()->getStatus(), [BaseTestRunner::STATUS_SKIPPED, BaseTestRunner::STATUS_INCOMPLETE, BaseTestRunner::STATUS_FAILURE, BaseTestRunner::STATUS_ERROR], true)) {
            } else {
                try {
                    $prefix = "@expectedDeprecation:\n";
//                    $event->test()->assertStringMatchesFormat($prefix.'%A  '.implode("\n%A  ", $this->expectedDeprecations)."\n%A", $prefix.'  '.implode("\n  ", $this->gatheredDeprecations)."\n");
                    Assert::assertStringMatchesFormat($prefix.'%A  '.implode("\n%A  ", $this->expectedDeprecations)."\n%A", $prefix.'  '.implode("\n  ", $this->gatheredDeprecations)."\n");
//                } catch (AssertionFailedError $e) {
                } catch (ExpectationFailedException $e) {
//                    $event->test()->getTestResultObject()->addFailure($event->test(), $e, $time);
                    $this->failures[$e->getMessage()][$className . '::' . $methodName] = 1;
                }
            }

            $this->expectedDeprecations = $this->gatheredDeprecations = [];
//            $this->previousErrorHandler = null;
        }

//        if (!$this->runsInSeparateProcess && -2 < $this->state && $event->test() instanceof TestCase) {
        if (!$this->runsInSeparateProcess && -2 < $this->state) {
            if (\in_array('time-sensitive', $groups, true)) {
                ClockMock::withClockMock(false);
            }
            if (\in_array('dns-sensitive', $groups, true)) {
                DnsMock::withMockedHosts([]);
            }
        }
    }

    // REF - DeprecationErrorHandler::handleError()
    // REF - SymfonyTestsListenerTrait::handleError()
    public function onTriggeredDeprecation(int $type, $event): void
    {
        $msg = $event->message();
        $file = $event->file();
        $line = $event->line();

        $trace = debug_backtrace();

        $deprecation = new Deprecation($msg, $trace, $file, \E_DEPRECATED === $type);
        if ($deprecation->isMuted()) {
            return;
        }
        if ($this->getConfiguration()->isIgnoredDeprecation($deprecation)) {
            return;
        }
        if ($this->getConfiguration()->isBaselineDeprecation($deprecation)) {
            return;
        }

        $msg = $deprecation->getMessage();

        // REF - SymfonyTestsListenerTrait::handleError()
        $this->gatheredDeprecations[] = $msg;

//        if (\E_DEPRECATED !== $type && (error_reporting() & $type)) {
        if (\E_DEPRECATED !== $type && !$event->wasSuppressed()) {
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
    }

    // REF - SymfonyTestsListenerTrait::__destruct()
    // REF - DeprecationErrorHandler::shutdown()
    public function onTestRunnerFinished(): void
    {
        // REF - SymfonyTestsListenerTrait::__destruct()
        if (0 < $this->state) {
            file_put_contents($this->skippedFile, '<?php return '.var_export($this->isSkipped, true).';');
            return;
        }

        $configuration = $this->getConfiguration();

        if ($configuration->isInRegexMode()) {
            return;
        }

        if (class_exists(DebugClassLoader::class, false)) {
            DebugClassLoader::checkClasses();
        }
//        $currErrorHandler = set_error_handler('is_int');
//        restore_error_handler();

//        if ($currErrorHandler !== [$this, 'handleError']) {
//            echo "\n", self::colorize('THE ERROR HANDLER HAS CHANGED!', true), "\n";
//        }

        if ($this->errors) {
dump('**** PHPUnit-Bridge ERRORS ****', $this->errors);
            $this->bridgeExitStatus = $this->bridgeExitStatus < 2 ? 2 : $this->bridgeExitStatus;
        }

        if ($this->failures) {
dump('**** PHPUnit-Bridge FAILURES ****', $this->failures);
            $this->bridgeExitStatus = $this->bridgeExitStatus < 1 ? 1 : $this->bridgeExitStatus;
        }

        $groups = array_keys($this->deprecationGroups);

        // store failing status
        $isFailing = !$configuration->tolerates($this->deprecationGroups);
        $this->bridgeExitStatus = $isFailing ? ($this->bridgeExitStatus < 1 ? 1 : $this->bridgeExitStatus) : $this->bridgeExitStatus;

        $this->displayDeprecations($groups, $configuration);

        $this->resetDeprecationGroups();

//        register_shutdown_function(function () use ($isFailing, $groups, $configuration) {
//            foreach ($this->deprecationGroups as $group) {
//                if ($group->count() > 0) {
//                    echo "Shutdown-time deprecations:\n";
//                    break;
//                }
//            }
//
//            $isFailingAtShutdown = !$configuration->tolerates($this->deprecationGroups);
//            $this->displayDeprecations($groups, $configuration);
//
//            if ($configuration->isGeneratingBaseline()) {
//                $configuration->writeBaseline();
//            }
//
//            if ($isFailing || $isFailingAtShutdown) {
//                exit(1);
//            }
//        });
    }

    public function onApplicationFinished($event): void
    {
        if ($event->shellExitCode() < $this->bridgeExitStatus) {
            exit($this->bridgeExitStatus);
        }
    }

    // REF - DeprecationErrorHandler::resetDeprecationGroups()
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

    // REF - DeprecationErrorHandler::getConfiguration()
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

    // REF - DeprecationErrorHandler::collectDeprecations()
    public function collectDeprecations($outputFile)
    {
//        $deprecations = [];
//        $previousErrorHandler = set_error_handler(function ($type, $msg, $file, $line, $context = []) use (&$deprecations, &$previousErrorHandler) {
//            if (\E_USER_DEPRECATED !== $type && \E_DEPRECATED !== $type && (\E_WARNING !== $type || false === strpos($msg, '" targeting switch is equivalent to "break'))) {
//                if ($previousErrorHandler) {
//                    return $previousErrorHandler($type, $msg, $file, $line, $context);
//                }
//
//                return \call_user_func(self::getPhpUnitErrorHandler(), $type, $msg, $file, $line, $context);
//            }
//
//            $filesStack = [];
//            foreach (debug_backtrace() as $frame) {
//                if (!isset($frame['file']) || \in_array($frame['function'], ['require', 'require_once', 'include', 'include_once'], true)) {
//                    continue;
//                }
//
//                $filesStack[] = $frame['file'];
//            }
//
//            $deprecations[] = [error_reporting() & $type, $msg, $file, $filesStack];
//
//            return null;
//        });

        register_shutdown_function(function () use ($outputFile, &$deprecations) {
            file_put_contents($outputFile, serialize($deprecations));
        });
    }

    /**
     * @param string[]      $groups
     * @param Configuration $configuration
     *
     * @throws \InvalidArgumentException
     */
    // REF - DeprecationErrorHandler::shutdown()
    private function displayDeprecations($groups, $configuration)
    {
        $cmp = function ($a, $b) {
            return $b->count() - $a->count();
        };

//        if ($configuration->shouldWriteToLogFile()) {
//            if (false === $handle = @fopen($file = $configuration->getLogFile(), 'a')) {
//                throw new \InvalidArgumentException(sprintf('The configured log file "%s" is not writeable.', $file));
//            }
//        } else {
            $handle = fopen('php://output', 'w');
//        }

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
                    fwrite($handle, "\n".DeprecationErrorHandler::colorize($deprecationGroupMessage, 'legacy' !== $group && 'indirect' !== $group)."\n");
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
}
