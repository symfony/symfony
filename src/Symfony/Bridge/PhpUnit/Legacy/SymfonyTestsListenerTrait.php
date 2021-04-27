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
use PHPUnit\Framework\AssertionFailedError;
use PHPUnit\Framework\RiskyTestError;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\TestSuite;
use PHPUnit\Runner\BaseTestRunner;
use PHPUnit\Util\Blacklist;
use PHPUnit\Util\ExcludeList;
use PHPUnit\Util\Test;
use Symfony\Bridge\PhpUnit\ClockMock;
use Symfony\Bridge\PhpUnit\DnsMock;
use Symfony\Bridge\PhpUnit\ExpectDeprecationTrait;
use Symfony\Component\Debug\DebugClassLoader as LegacyDebugClassLoader;
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
        if (class_exists(ExcludeList::class)) {
            (new ExcludeList())->getExcludedDirectories();
            ExcludeList::addDirectory(\dirname((new \ReflectionClass(__CLASS__))->getFileName(), 2));
        } elseif (method_exists(Blacklist::class, 'addDirectory')) {
            (new BlackList())->getBlacklistedDirectories();
            Blacklist::addDirectory(\dirname((new \ReflectionClass(__CLASS__))->getFileName(), 2));
        } else {
            Blacklist::$blacklistedClassNames[__CLASS__] = 2;
        }

        $enableDebugClassLoader = class_exists(DebugClassLoader::class) || class_exists(LegacyDebugClassLoader::class);

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
            if (class_exists(DebugClassLoader::class)) {
                DebugClassLoader::enable();
            } else {
                LegacyDebugClassLoader::enable();
            }
        }
        if (self::$globallyEnabled) {
            $this->state = -2;
        } else {
            self::$globallyEnabled = true;
        }
    }

    public function __sleep()
    {
        throw new \BadMethodCallException('Cannot serialize '.__CLASS__);
    }

    public function __wakeup()
    {
        throw new \BadMethodCallException('Cannot unserialize '.__CLASS__);
    }

    public function __destruct()
    {
        if (0 < $this->state) {
            file_put_contents($this->skippedFile, '<?php return '.var_export($this->isSkipped, true).';');
        }
    }

    public function globalListenerDisabled()
    {
        self::$globallyEnabled = false;
        $this->state = -1;
    }

    public function startTestSuite($suite)
    {
        $suiteName = $suite->getName();

        foreach ($suite->tests() as $test) {
            if (!($test instanceof \PHPUnit_Framework_TestCase || $test instanceof TestCase)) {
                continue;
            }
            if (null === Test::getPreserveGlobalStateSettings(\get_class($test), $test->getName(false))) {
                $test->setPreserveGlobalState(false);
            }
        }

        if (-1 === $this->state) {
            echo "Testing $suiteName\n";
            $this->state = 0;

            if (!class_exists(AnnotationRegistry::class, false) && class_exists(AnnotationRegistry::class)) {
                if (method_exists(AnnotationRegistry::class, 'registerUniqueLoader')) {
                    AnnotationRegistry::registerUniqueLoader('class_exists');
                } else {
                    AnnotationRegistry::registerLoader('class_exists');
                }
            }

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
                    if ($test instanceof \PHPUnit_Framework_TestSuite || $test instanceof TestSuite) {
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
                    if ($test instanceof \PHPUnit_Framework_TestSuite || $test instanceof TestSuite) {
                        $suites[] = $test;
                        continue;
                    }
                    if (($test instanceof \PHPUnit_Framework_TestCase || $test instanceof TestCase)
                        && isset($this->wasSkipped[\get_class($test)][$test->getName()])
                    ) {
                        $skipped[] = $test;
                    }
                }
            }
            $suite->setTests($skipped);
        }
    }

    public function addSkippedTest($test, \Exception $e, $time)
    {
        if (0 < $this->state) {
            $this->isSkipped[\get_class($test)][$test->getName()] = 1;
        }
    }

    public function startTest($test)
    {
        if (-2 < $this->state && ($test instanceof \PHPUnit_Framework_TestCase || $test instanceof TestCase)) {
            // This event is triggered before the test is re-run in isolation
            if ($this->willBeIsolated($test)) {
                $this->runsInSeparateProcess = tempnam(sys_get_temp_dir(), 'deprec');
                putenv('SYMFONY_DEPRECATIONS_SERIALIZE='.$this->runsInSeparateProcess);
                putenv('SYMFONY_EXPECTED_DEPRECATIONS_SERIALIZE='.tempnam(sys_get_temp_dir(), 'expectdeprec'));
            }

            $groups = Test::getGroups(\get_class($test), $test->getName(false));

            if (!$this->runsInSeparateProcess) {
                if (\in_array('time-sensitive', $groups, true)) {
                    ClockMock::register(\get_class($test));
                    ClockMock::withClockMock(true);
                }
                if (\in_array('dns-sensitive', $groups, true)) {
                    DnsMock::register(\get_class($test));
                }
            }

            if (!$test->getTestResultObject()) {
                return;
            }

            $annotations = Test::parseTestMethodAnnotations(\get_class($test), $test->getName(false));

            if (isset($annotations['class']['expectedDeprecation'])) {
                $test->getTestResultObject()->addError($test, new AssertionFailedError('`@expectedDeprecation` annotations are not allowed at the class level.'), 0);
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

    public function endTest($test, $time)
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

        $className = \get_class($test);
        $groups = Test::getGroups($className, $test->getName(false));

        if ($this->checkNumAssertions) {
            $assertions = \count(self::$expectedDeprecations) + $test->getNumAssertions();
            if ($test->doesNotPerformAssertions() && $assertions > 0) {
                $test->getTestResultObject()->addFailure($test, new RiskyTestError(sprintf('This test is annotated with "@doesNotPerformAssertions", but performed %s assertions', $assertions)), $time);
            } elseif ($assertions === 0 && $test->getTestResultObject()->noneSkipped()) {
                $test->getTestResultObject()->addFailure($test, new RiskyTestError('This test did not perform any assertions'), $time);
            }

            $this->checkNumAssertions = false;
        }

        if ($this->runsInSeparateProcess) {
            $deprecations = file_get_contents($this->runsInSeparateProcess);
            unlink($this->runsInSeparateProcess);
            putenv('SYMFONY_DEPRECATIONS_SERIALIZE');
            foreach ($deprecations ? unserialize($deprecations) : [] as $deprecation) {
                $error = serialize(['deprecation' => $deprecation[1], 'class' => $className, 'method' => $test->getName(false), 'triggering_file' => isset($deprecation[2]) ? $deprecation[2] : null, 'files_stack' => isset($deprecation[3]) ? $deprecation[3] : []]);
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
            if (!\in_array($test->getStatus(), [BaseTestRunner::STATUS_SKIPPED, BaseTestRunner::STATUS_INCOMPLETE], true)) {
                $test->addToAssertionCount(\count(self::$expectedDeprecations));
            }

            restore_error_handler();

            if (!\in_array('legacy', $groups, true)) {
                $test->getTestResultObject()->addError($test, new AssertionFailedError('Only tests with the `@group legacy` annotation can expect a deprecation.'), 0);
            } elseif (!\in_array($test->getStatus(), [BaseTestRunner::STATUS_SKIPPED, BaseTestRunner::STATUS_INCOMPLETE, BaseTestRunner::STATUS_FAILURE, BaseTestRunner::STATUS_ERROR], true)) {
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
        if (!$this->runsInSeparateProcess && -2 < $this->state && ($test instanceof \PHPUnit_Framework_TestCase || $test instanceof TestCase)) {
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
        // If the message is serialized we need to extract the message. This occurs when the error is triggered by
        // by the isolated test path in \Symfony\Bridge\PhpUnit\Legacy\SymfonyTestsListenerTrait::endTest().
        $parsedMsg = @unserialize($msg);
        if (\is_array($parsedMsg)) {
            $msg = $parsedMsg['deprecation'];
        }
        if (error_reporting() & $type) {
            $msg = 'Unsilenced deprecation: '.$msg;
        }
        self::$gatheredDeprecations[] = $msg;

        return null;
    }

    /**
     * @param TestCase $test
     *
     * @return bool
     */
    private function willBeIsolated($test)
    {
        if ($test->isInIsolation()) {
            return false;
        }

        $r = new \ReflectionProperty($test, 'runTestInSeparateProcess');
        $r->setAccessible(true);

        return $r->getValue($test);
    }
}
