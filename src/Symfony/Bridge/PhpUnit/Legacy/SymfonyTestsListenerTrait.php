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

use PHPUnit\Framework\AssertionFailedError;
use PHPUnit\Framework\DataProviderTestSuite;
use PHPUnit\Framework\RiskyTestError;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\TestSuite;
use PHPUnit\Runner\BaseTestRunner;
use PHPUnit\Runner\PhptTestCase;
use PHPUnit\Util\Blacklist;
use PHPUnit\Util\ExcludeList;
use PHPUnit\Util\Test;
use Symfony\Bridge\PhpUnit\ClockMock;
use Symfony\Bridge\PhpUnit\DnsMock;
use Symfony\Bridge\PhpUnit\ExpectDeprecationTrait;
use Symfony\Component\ErrorHandler\DebugClassLoader;

/**
 * PHP 5.3 compatible trait-like shared implementation.
 *
 * @author Nicolas Grekas <p@tchwork.com>
 *
 * @internal
 */
class SymfonyTestsListenerTrait
{
    public static $expectedDeprecations = [];
    public static $previousErrorHandler;
    private static $gatheredDeprecations = [];
    private static $globallyEnabled = false;
    private $state = -1;
    private $skippedFile = false;
    private $wasSkipped = [];
    private $isSkipped = [];
    private $runsInSeparateProcess = false;
    private $checkNumAssertions = false;

    /**
     * @param array $mockedNamespaces List of namespaces, indexed by mocked features (time-sensitive or dns-sensitive)
     */
    public function __construct(array $mockedNamespaces = [])
    {
        setlocale(\LC_ALL, $_ENV['SYMFONY_PHPUNIT_LOCALE'] ?? 'C');

        if (class_exists(ExcludeList::class)) {
            (new ExcludeList())->getExcludedDirectories();
            ExcludeList::addDirectory(\dirname((new \ReflectionClass(__CLASS__))->getFileName(), 2));
        } elseif (method_exists(Blacklist::class, 'addDirectory')) {
            (new Blacklist())->getBlacklistedDirectories();
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
    }

    public function __serialize(): array
    {
        throw new \BadMethodCallException('Cannot serialize '.__CLASS__);
    }

    public function __unserialize(array $data): void
    {
        throw new \BadMethodCallException('Cannot unserialize '.__CLASS__);
    }

    public function __destruct()
    {
        if (0 < $this->state) {
            file_put_contents($this->skippedFile, '<?php return '.var_export($this->isSkipped, true).';');
        }
    }

    public function globalListenerDisabled(): void
    {
        self::$globallyEnabled = false;
        $this->state = -1;
    }

    public function startTestSuite($suite): void
    {
        $suiteName = $suite->getName();

        foreach ($suite->tests() as $test) {
            if (!$test instanceof TestCase) {
                continue;
            }
            if (null === Test::getPreserveGlobalStateSettings($test::class, $test->getName(false))) {
                $test->setPreserveGlobalState(false);
            }
        }

        if (-1 === $this->state) {
            echo "Testing $suiteName\n";
            $this->state = 0;

            if ($this->skippedFile = getenv('SYMFONY_PHPUNIT_SKIPPED_TESTS')) {
                $this->state = 1;

                if (file_exists($this->skippedFile)) {
                    $this->state = 2;

                    if (!$this->wasSkipped = require $this->skippedFile) {
                        echo "All tests already ran successfully.\n";
                        $suite->setTests([]);
                    }
                }
            }
            $testSuites = [$suite];
            for ($i = 0; isset($testSuites[$i]); ++$i) {
                foreach ($testSuites[$i]->tests() as $test) {
                    if ($test instanceof TestSuite) {
                        if (!class_exists($test->getName(), false)) {
                            $testSuites[] = $test;
                            continue;
                        }
                        $groups = Test::getGroups($test->getName());
                        if (\in_array('time-sensitive', $groups, true)) {
                            ClockMock::register($test->getName());
                        }
                        if (\in_array('dns-sensitive', $groups, true)) {
                            DnsMock::register($test->getName());
                        }
                    }
                }
            }
        } elseif (2 === $this->state) {
            $suites = [$suite];
            $skipped = [];
            while ($s = array_shift($suites)) {
                foreach ($s->tests() as $test) {
                    if ($test instanceof TestSuite) {
                        $suites[] = $test;
                        continue;
                    }
                    if ($test instanceof TestCase
                        && isset($this->wasSkipped[$test::class][$test->getName()])
                    ) {
                        $skipped[] = $test;
                    }
                }
            }
            $suite->setTests($skipped);
        }
    }

    public function addSkippedTest($test, \Exception $e, $time): void
    {
        if (0 < $this->state) {
            if ($test instanceof DataProviderTestSuite) {
                foreach ($test->tests() as $testWithDataProvider) {
                    $this->isSkipped[$testWithDataProvider::class][$testWithDataProvider->getName()] = 1;
                }
            } else {
                $this->isSkipped[$test::class][$test->getName()] = 1;
            }
        }
    }

    public function startTest($test): void
    {
        if (-2 < $this->state && $test instanceof PhptTestCase) {
            $this->runsInSeparateProcess = tempnam(sys_get_temp_dir(), 'deprec');
            putenv('SYMFONY_DEPRECATIONS_SERIALIZE='.$this->runsInSeparateProcess);
            putenv('SYMFONY_EXPECTED_DEPRECATIONS_SERIALIZE='.tempnam(sys_get_temp_dir(), 'expectdeprec'));
        }

        if (-2 < $this->state && $test instanceof TestCase) {
            // This event is triggered before the test is re-run in isolation
            if ($this->willBeIsolated($test)) {
                $this->runsInSeparateProcess = tempnam(sys_get_temp_dir(), 'deprec');
                putenv('SYMFONY_DEPRECATIONS_SERIALIZE='.$this->runsInSeparateProcess);
                putenv('SYMFONY_EXPECTED_DEPRECATIONS_SERIALIZE='.tempnam(sys_get_temp_dir(), 'expectdeprec'));
            }

            $groups = Test::getGroups($test::class, $test->getName(false));

            if (!$this->runsInSeparateProcess) {
                if (\in_array('time-sensitive', $groups, true)) {
                    ClockMock::register($test::class);
                    ClockMock::withClockMock(true);
                }
                if (\in_array('dns-sensitive', $groups, true)) {
                    DnsMock::register($test::class);
                }
            }

            if (!$test->getTestResultObject()) {
                return;
            }

            $annotations = Test::parseTestMethodAnnotations($test::class, $test->getName(false));

            if (isset($annotations['class']['expectedDeprecation'])) {
                $test->getTestResultObject()->addError($test, new AssertionFailedError('"@expectedDeprecation" annotations are not allowed at the class level.'), 0);
            }
            if (isset($annotations['method']['expectedDeprecation']) || $this->checkNumAssertions = method_exists($test, 'expectDeprecation') && (new \ReflectionMethod($test, 'expectDeprecation'))->getFileName() === (new \ReflectionMethod(ExpectDeprecationTrait::class, 'expectDeprecation'))->getFileName()) {
                if (isset($annotations['method']['expectedDeprecation'])) {
                    self::$expectedDeprecations = $annotations['method']['expectedDeprecation'];
                    self::$previousErrorHandler = set_error_handler([self::class, 'handleError']);
                    @trigger_error('Since symfony/phpunit-bridge 5.1: Using "@expectedDeprecation" annotations in tests is deprecated, use the "ExpectDeprecationTrait::expectDeprecation()" method instead.', \E_USER_DEPRECATED);
                }

                if ($this->checkNumAssertions) {
                    $this->checkNumAssertions = $test->getTestResultObject()->isStrictAboutTestsThatDoNotTestAnything();
                }

                $test->getTestResultObject()->beStrictAboutTestsThatDoNotTestAnything(false);
            }
        }
    }

    public function endTest($test, $time): void
    {
        if ($file = getenv('SYMFONY_EXPECTED_DEPRECATIONS_SERIALIZE')) {
            putenv('SYMFONY_EXPECTED_DEPRECATIONS_SERIALIZE');
            $expectedDeprecations = file_get_contents($file);
            if ($expectedDeprecations) {
                self::$expectedDeprecations = array_merge(self::$expectedDeprecations, unserialize($expectedDeprecations));
                if (!self::$previousErrorHandler) {
                    self::$previousErrorHandler = set_error_handler([self::class, 'handleError']);
                }
            }
        }

        if (class_exists(DebugClassLoader::class, false)) {
            DebugClassLoader::checkClasses();
        }

        $className = $test::class;
        $groups = Test::getGroups($className, $test->getName(false));

        if ($this->checkNumAssertions) {
            $assertions = \count(self::$expectedDeprecations) + $test->getNumAssertions();
            if ($test instanceof TestCase && $test->doesNotPerformAssertions() && $assertions > 0) {
                $test->getTestResultObject()->addFailure($test, new RiskyTestError(\sprintf('This test is annotated with "@doesNotPerformAssertions", but performed %s assertions', $assertions)), $time);
            } elseif ($test instanceof TestCase && 0 === $assertions && !$test->doesNotPerformAssertions() && $test->getTestResultObject()->noneSkipped()) {
                $test->getTestResultObject()->addFailure($test, new RiskyTestError('This test did not perform any assertions'), $time);
            }

            $this->checkNumAssertions = false;
        }

        if ($this->runsInSeparateProcess) {
            $deprecations = file_get_contents($this->runsInSeparateProcess);
            unlink($this->runsInSeparateProcess);
            putenv('SYMFONY_DEPRECATIONS_SERIALIZE');
            foreach ($deprecations ? unserialize($deprecations) : [] as $deprecation) {
                $error = serialize(['deprecation' => $deprecation[1], 'class' => $className, 'method' => $test->getName(false), 'triggering_file' => $deprecation[2] ?? null, 'files_stack' => $deprecation[3] ?? []]);
                if ($deprecation[0]) {
                    // unsilenced on purpose
                    trigger_error($error, \E_USER_DEPRECATED);
                } else {
                    @trigger_error($error, \E_USER_DEPRECATED);
                }
            }
            $this->runsInSeparateProcess = false;
        }

        if (self::$expectedDeprecations) {
            if ($test instanceof TestCase && !\in_array($test->getStatus(), [BaseTestRunner::STATUS_SKIPPED, BaseTestRunner::STATUS_INCOMPLETE], true)) {
                $test->addToAssertionCount(\count(self::$expectedDeprecations));
            }

            restore_error_handler();

            if ($test instanceof TestCase && !\in_array('legacy', $groups, true)) {
                $test->getTestResultObject()->addError($test, new AssertionFailedError('Only tests with the "@group legacy" annotation can expect a deprecation.'), 0);
            } elseif ($test instanceof TestCase && !\in_array($test->getStatus(), [BaseTestRunner::STATUS_SKIPPED, BaseTestRunner::STATUS_INCOMPLETE, BaseTestRunner::STATUS_FAILURE, BaseTestRunner::STATUS_ERROR], true)) {
                try {
                    $prefix = "@expectedDeprecation:\n";
                    $test->assertStringMatchesFormat($prefix.'%A  '.implode("\n%A  ", self::$expectedDeprecations)."\n%A", $prefix.'  '.implode("\n  ", self::$gatheredDeprecations)."\n");
                } catch (AssertionFailedError $e) {
                    $test->getTestResultObject()->addFailure($test, $e, $time);
                }
            }

            self::$expectedDeprecations = self::$gatheredDeprecations = [];
            self::$previousErrorHandler = null;
        }
        if (!$this->runsInSeparateProcess && -2 < $this->state && $test instanceof TestCase) {
            if (\in_array('time-sensitive', $groups, true)) {
                ClockMock::withClockMock(false);
            }
            if (\in_array('dns-sensitive', $groups, true)) {
                DnsMock::withMockedHosts([]);
            }
        }
    }

    public static function handleError($type, $msg, $file, $line, $context = [])
    {
        if (\E_USER_DEPRECATED !== $type && \E_DEPRECATED !== $type) {
            $h = self::$previousErrorHandler;

            return $h ? $h($type, $msg, $file, $line, $context) : false;
        }
        // If the message is serialized we need to extract the message. This occurs when the error is triggered
        // by the isolated test path in \Symfony\Bridge\PhpUnit\Legacy\SymfonyTestsListenerTrait::endTest().
        $parsedMsg = @unserialize($msg);
        if (\is_array($parsedMsg)) {
            $msg = $parsedMsg['deprecation'];
        }
        if (error_reporting() & $type) {
            $msg = 'Unsilenced deprecation: '.$msg;
        }
        self::$gatheredDeprecations[] = $msg;

        return true;
    }

    private function willBeIsolated(TestCase $test): bool
    {
        if ($test->isInIsolation()) {
            return false;
        }

        $r = new \ReflectionProperty($test, 'runTestInSeparateProcess');

        return $r->getValue($test) ?? false;
    }
}
