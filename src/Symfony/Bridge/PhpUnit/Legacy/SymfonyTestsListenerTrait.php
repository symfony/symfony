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
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\TestSuite;
use PHPUnit\Util\Blacklist;
use Symfony\Bridge\PhpUnit\ClockMock;
use Symfony\Bridge\PhpUnit\DnsMock;
use Symfony\Component\Debug\DebugClassLoader;

/**
 * PHP 5.3 compatible trait-like shared implementation.
 *
 * @author Nicolas Grekas <p@tchwork.com>
 *
 * @internal
 */
class SymfonyTestsListenerTrait
{
    private static $globallyEnabled = false;
    private $state = -1;
    private $skippedFile = false;
    private $wasSkipped = array();
    private $isSkipped = array();
    private $expectedDeprecations = array();
    private $gatheredDeprecations = array();
    private $previousErrorHandler;
    private $testsWithWarnings;
    private $reportUselessTests;
    private $error;
    private $runsInSeparateProcess = false;

    /**
     * @param array $mockedNamespaces List of namespaces, indexed by mocked features (time-sensitive or dns-sensitive)
     */
    public function __construct(array $mockedNamespaces = array())
    {
        if (class_exists('PHPUnit_Util_Blacklist')) {
            \PHPUnit_Util_Blacklist::$blacklistedClassNames['\Symfony\Bridge\PhpUnit\Legacy\SymfonyTestsListenerTrait'] = 2;
        } else {
            Blacklist::$blacklistedClassNames['\Symfony\Bridge\PhpUnit\Legacy\SymfonyTestsListenerTrait'] = 2;
        }

        $enableDebugClassLoader = \class_exists('Symfony\Component\Debug\DebugClassLoader');

        foreach ($mockedNamespaces as $type => $namespaces) {
            if (!\is_array($namespaces)) {
                $namespaces = array($namespaces);
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
        if (class_exists('PHPUnit_Util_Blacklist', false)) {
            $Test = 'PHPUnit_Util_Test';
        } else {
            $Test = 'PHPUnit\Util\Test';
        }
        $suiteName = $suite->getName();
        $this->testsWithWarnings = array();

        foreach ($suite->tests() as $test) {
            if (!($test instanceof \PHPUnit\Framework\TestCase || $test instanceof TestCase)) {
                continue;
            }
            if (null === $Test::getPreserveGlobalStateSettings(\get_class($test), $test->getName(false))) {
                $test->setPreserveGlobalState(false);
            }
        }

        if (-1 === $this->state) {
            echo "Testing $suiteName\n";
            $this->state = 0;

            if (!class_exists('Doctrine\Common\Annotations\AnnotationRegistry', false) && class_exists('Doctrine\Common\Annotations\AnnotationRegistry')) {
                if (method_exists('Doctrine\Common\Annotations\AnnotationRegistry', 'registerUniqueLoader')) {
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
                        $suite->setTests(array());
                    }
                }
            }
            $testSuites = array($suite);
            for ($i = 0; isset($testSuites[$i]); ++$i) {
                foreach ($testSuites[$i]->tests() as $test) {
                    if ($test instanceof \PHPUnit_Framework_TestSuite || $test instanceof TestSuite) {
                        if (!class_exists($test->getName(), false)) {
                            $testSuites[] = $test;
                            continue;
                        }
                        $groups = $Test::getGroups($test->getName());
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
            $skipped = array();
            foreach ($suite->tests() as $test) {
                if (!($test instanceof \PHPUnit\Framework\TestCase || $test instanceof TestCase)
                    || isset($this->wasSkipped[$suiteName]['*'])
                    || isset($this->wasSkipped[$suiteName][$test->getName()])) {
                    $skipped[] = $test;
                }
            }
            $suite->setTests($skipped);
        }
    }

    public function addSkippedTest($test, \Exception $e, $time)
    {
        if (0 < $this->state) {
            if ($test instanceof \PHPUnit\Framework\TestCase || $test instanceof TestCase) {
                $class = \get_class($test);
                $method = $test->getName();
            } else {
                $class = $test->getName();
                $method = '*';
            }

            $this->isSkipped[$class][$method] = 1;
        }
    }

    public function startTest($test)
    {
        if (-2 < $this->state && ($test instanceof \PHPUnit\Framework\TestCase || $test instanceof TestCase)) {
            if (null !== $test->getTestResultObject()) {
                $this->reportUselessTests = $test->getTestResultObject()->isStrictAboutTestsThatDoNotTestAnything();
            }

            // This event is triggered before the test is re-run in isolation
            if ($this->willBeIsolated($test)) {
                $this->runsInSeparateProcess = tempnam(sys_get_temp_dir(), 'deprec');
                putenv('SYMFONY_DEPRECATIONS_SERIALIZE='.$this->runsInSeparateProcess);
            }

            if (class_exists('PHPUnit_Util_Blacklist', false)) {
                $Test = 'PHPUnit_Util_Test';
                $AssertionFailedError = 'PHPUnit_Framework_AssertionFailedError';
            } else {
                $Test = 'PHPUnit\Util\Test';
                $AssertionFailedError = 'PHPUnit\Framework\AssertionFailedError';
            }
            $groups = $Test::getGroups(\get_class($test), $test->getName(false));

            if (!$this->runsInSeparateProcess) {
                if (\in_array('time-sensitive', $groups, true)) {
                    ClockMock::register(\get_class($test));
                    ClockMock::withClockMock(true);
                }
                if (\in_array('dns-sensitive', $groups, true)) {
                    DnsMock::register(\get_class($test));
                }
            }

            $annotations = $Test::parseTestMethodAnnotations(\get_class($test), $test->getName(false));

            if (isset($annotations['class']['expectedDeprecation'])) {
                $test->getTestResultObject()->addError($test, new $AssertionFailedError('`@expectedDeprecation` annotations are not allowed at the class level.'), 0);
            }
            if (isset($annotations['method']['expectedDeprecation'])) {
                if (!\in_array('legacy', $groups, true)) {
                    $this->error = new $AssertionFailedError('Only tests with the `@group legacy` annotation can have `@expectedDeprecation`.');
                }

                $test->getTestResultObject()->beStrictAboutTestsThatDoNotTestAnything(false);

                $this->expectedDeprecations = $annotations['method']['expectedDeprecation'];
                $this->previousErrorHandler = set_error_handler(array($this, 'handleError'));
            }
        }
    }

    public function addWarning($test, $e, $time)
    {
        if ($test instanceof \PHPUnit\Framework\TestCase || $test instanceof TestCase) {
            $this->testsWithWarnings[$test->getName()] = true;
        }
    }

    public function endTest($test, $time)
    {
        if (class_exists('PHPUnit_Util_Blacklist', false)) {
            $Test = 'PHPUnit_Util_Test';
            $BaseTestRunner = 'PHPUnit_Runner_BaseTestRunner';
            $Warning = 'PHPUnit_Framework_Warning';
        } else {
            $Test = 'PHPUnit\Util\Test';
            $BaseTestRunner = 'PHPUnit\Runner\BaseTestRunner';
            $Warning = 'PHPUnit\Framework\Warning';
        }
        $className = \get_class($test);
        $classGroups = $Test::getGroups($className);
        $groups = $Test::getGroups($className, $test->getName(false));

        if (null !== $this->reportUselessTests) {
            $test->getTestResultObject()->beStrictAboutTestsThatDoNotTestAnything($this->reportUselessTests);
            $this->reportUselessTests = null;
        }

        if ($errored = null !== $this->error) {
            $test->getTestResultObject()->addError($test, $this->error, 0);
            $this->error = null;
        }

        if ($this->runsInSeparateProcess) {
            $deprecations = file_get_contents($this->runsInSeparateProcess);
            unlink($this->runsInSeparateProcess);
            putenv('SYMFONY_DEPRECATIONS_SERIALIZE');
            foreach ($deprecations ? unserialize($deprecations) : array() as $deprecation) {
                $error = serialize(array('deprecation' => $deprecation[1], 'class' => $className, 'method' => $test->getName(false), 'triggering_file' => isset($deprecation[2]) ? $deprecation[2] : null));
                if ($deprecation[0]) {
                    @trigger_error($error, E_USER_DEPRECATED);
                } else {
                    @trigger_error($error, E_USER_DEPRECATED);
                }
            }
            $this->runsInSeparateProcess = false;
        }

        if ($this->expectedDeprecations) {
            if (!\in_array($test->getStatus(), array($BaseTestRunner::STATUS_SKIPPED, $BaseTestRunner::STATUS_INCOMPLETE), true)) {
                $test->addToAssertionCount(\count($this->expectedDeprecations));
            }

            restore_error_handler();

            if (!$errored && !\in_array($test->getStatus(), array($BaseTestRunner::STATUS_SKIPPED, $BaseTestRunner::STATUS_INCOMPLETE, $BaseTestRunner::STATUS_FAILURE, $BaseTestRunner::STATUS_ERROR), true)) {
                try {
                    $prefix = "@expectedDeprecation:\n";
                    $test->assertStringMatchesFormat($prefix.'%A  '.implode("\n%A  ", $this->expectedDeprecations)."\n%A", $prefix.'  '.implode("\n  ", $this->gatheredDeprecations)."\n");
                } catch (AssertionFailedError $e) {
                    $test->getTestResultObject()->addFailure($test, $e, $time);
                } catch (\PHPUnit_Framework_AssertionFailedError $e) {
                    $test->getTestResultObject()->addFailure($test, $e, $time);
                }
            }

            $this->expectedDeprecations = $this->gatheredDeprecations = array();
            $this->previousErrorHandler = null;
        }
        if (!$this->runsInSeparateProcess && -2 < $this->state && ($test instanceof \PHPUnit\Framework\TestCase || $test instanceof TestCase)) {
            if (\in_array('time-sensitive', $groups, true)) {
                ClockMock::withClockMock(false);
            }
            if (\in_array('dns-sensitive', $groups, true)) {
                DnsMock::withMockedHosts(array());
            }
        }
    }

    public function handleError($type, $msg, $file, $line, $context = array())
    {
        if (E_USER_DEPRECATED !== $type && E_DEPRECATED !== $type) {
            $h = $this->previousErrorHandler;

            return $h ? $h($type, $msg, $file, $line, $context) : false;
        }
        // If the message is serialized we need to extract the message. This occurs when the error is triggered by
        // by the isolated test path in \Symfony\Bridge\PhpUnit\Legacy\SymfonyTestsListenerTrait::endTest().
        $parsedMsg = @unserialize($msg);
        if (\is_array($parsedMsg)) {
            $msg = $parsedMsg['deprecation'];
        }
        if (error_reporting()) {
            $msg = 'Unsilenced deprecation: '.$msg;
        }
        $this->gatheredDeprecations[] = $msg;
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
