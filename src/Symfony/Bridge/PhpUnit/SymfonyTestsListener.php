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

use Doctrine\Common\Annotations\AnnotationRegistry;
use PHPUnit\Framework\AssertionFailedError as AssertionFailedError6;
use PHPUnit\Framework\TestCase as TestCase6;
use PHPUnit\Framework\TestSuite as TestSuite6;
use Symfony\Bridge\PhpUnit\Compat\Framework\AssertionFailedError;
use Symfony\Bridge\PhpUnit\Compat\Framework\BaseTestListener;
use Symfony\Bridge\PhpUnit\Compat\Framework\TestSuite;
use Symfony\Bridge\PhpUnit\Compat\Runner\BaseTestRunner;
use Symfony\Bridge\PhpUnit\Compat\Util\Blacklist;
use Symfony\Bridge\PhpUnit\Compat\Util\Test;

/**
 * Collects and replays skipped tests.
 *
 * @author Nicolas Grekas <p@tchwork.com>
 */
class SymfonyTestsListener extends BaseTestListener
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

    /**
     * @param array $mockedNamespaces List of namespaces, indexed by mocked features (time-sensitive or dns-sensitive)
     */
    public function __construct(array $mockedNamespaces = array())
    {
        Blacklist::$blacklistedClassNames['\Symfony\Bridge\PhpUnit\SymfonyTestsListener'] = 1;

        $warn = false;
        foreach ($mockedNamespaces as $type => $namespaces) {
            if (!is_array($namespaces)) {
                $namespaces = array($namespaces);
            }
            if (is_int($type)) {
                // @deprecated BC with v2.8 to v3.0
                $type = 'time-sensitive';
                $warn = true;
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
        }
        if (self::$globallyEnabled) {
            $this->state = -2;
        } else {
            self::$globallyEnabled = true;
            if ($warn) {
                echo "Clock-mocked namespaces for SymfonyTestsListener need to be nested in a \"time-sensitive\" key. This will be enforced in Symfony 4.0.\n";
            }
        }
    }

    public function __destruct()
    {
        if (0 < $this->state) {
            file_put_contents($this->skippedFile, '<?php return '.var_export($this->isSkipped, true).';');
        }
    }

    public function startTestSuite(TestSuite $suite)
    {
        $suiteName = $suite->getName();
        $this->testsWithWarnings = array();

        if (-1 === $this->state) {
            echo "Testing $suiteName\n";
            $this->state = 0;

            if (!class_exists('Doctrine\Common\Annotations\AnnotationRegistry', false) && class_exists('Doctrine\Common\Annotations\AnnotationRegistry')) {
                AnnotationRegistry::registerLoader('class_exists');
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
                    if ($this->isInstanceOfPHPUnitTestSuite($test)) {
                        if (!class_exists($test->getName(), false)) {
                            $testSuites[] = $test;
                            continue;
                        }
                        $groups = Test::getGroups($test->getName());
                        if (in_array('time-sensitive', $groups, true)) {
                            ClockMock::register($test->getName());
                        }
                        if (in_array('dns-sensitive', $groups, true)) {
                            DnsMock::register($test->getName());
                        }
                    }
                }
            }
        } elseif (2 === $this->state) {
            $skipped = array();
            foreach ($suite->tests() as $test) {
                if (!$this->isInstanceOfPHPUnitTestSuite($test)
                    || isset($this->wasSkipped[$suiteName]['*'])
                    || isset($this->wasSkipped[$suiteName][$test->getName()])) {
                    $skipped[] = $test;
                }
            }
            $suite->setTests($skipped);
        }
    }

    public function addSkippedTest(\PHPUnit_Framework_Test $test, \Exception $e, $time)
    {
        if (0 < $this->state) {
            if ($test instanceof \PHPUnit_Framework_TestCase) {
                $class = get_class($test);
                $method = $test->getName();
            } else {
                $class = $test->getName();
                $method = '*';
            }

            $this->isSkipped[$class][$method] = 1;
        }
    }

    public function startTest(\PHPUnit_Framework_Test $test)
    {
        if (-2 < $this->state && $this->isInstanceOfPHPUnitTestCase($test)) {
            $groups = Test::getGroups(get_class($test), $test->getName(false));

            if (in_array('time-sensitive', $groups, true)) {
                ClockMock::register(get_class($test));
                ClockMock::withClockMock(true);
            }
            if (in_array('dns-sensitive', $groups, true)) {
                DnsMock::register(get_class($test));
            }

            $annotations = Test::parseTestMethodAnnotations(get_class($test), $test->getName(false));

            if (isset($annotations['class']['expectedDeprecation'])) {
                $test->getTestResultObject()->addError($test, new AssertionFailedError('`@expectedDeprecation` annotations are not allowed at the class level.'), 0);
            }
            if (isset($annotations['method']['expectedDeprecation'])) {
                if (!in_array('legacy', $groups, true)) {
                    $test->getTestResultObject()->addError($test, new AssertionFailedError('Only tests with the `@group legacy` annotation can have `@expectedDeprecation`.'), 0);
                }
                $this->expectedDeprecations = $annotations['method']['expectedDeprecation'];
                $this->previousErrorHandler = set_error_handler(array($this, 'handleError'));
            }
        }
    }

    public function addWarning(\PHPUnit_Framework_Test $test, \PHPUnit_Framework_Warning $e, $time)
    {
        if ($test instanceof \PHPUnit_Framework_TestCase) {
            $this->testsWithWarnings[$test->getName()] = true;
        }
    }

    public function endTest(\PHPUnit_Framework_Test $test, $time)
    {
        $className = get_class($test);
        $classGroups = \PHPUnit_Util_Test::getGroups($className);
        $groups = \PHPUnit_Util_Test::getGroups($className, $test->getName(false));

        if ($this->expectedDeprecations) {
            restore_error_handler();

            if (!in_array($test->getStatus(), array(BaseTestRunner::STATUS_SKIPPED, BaseTestRunner::STATUS_INCOMPLETE, BaseTestRunner::STATUS_FAILURE, BaseTestRunner::STATUS_ERROR), true)) {
                try {
                    $prefix = "@expectedDeprecation:\n";
                    $test->assertStringMatchesFormat($prefix.'%A  '.implode("\n%A  ", $this->expectedDeprecations)."\n%A", $prefix.'  '.implode("\n  ", $this->gatheredDeprecations)."\n");
                } catch (\PHPUnit_Framework_AssertionFailedError $e) {
                    $test->getTestResultObject()->addFailure($test, $e, $time);
                } catch (AssertionFailedError6 $e) {
                    $test->getTestResultObject()->addFailure($test, $e, $time);
                }
            }

            $this->expectedDeprecations = $this->gatheredDeprecations = array();
            $this->previousErrorHandler = null;
        }
        if (-2 < $this->state && $this->isInstanceOfPHPUnitTestCase($test)) {
            if (in_array('time-sensitive', $groups, true)) {
                ClockMock::withClockMock(false);
            }
            if (in_array('dns-sensitive', $groups, true)) {
                DnsMock::withMockedHosts(array());
            }
        }

        if ($test instanceof \PHPUnit_Framework_TestCase && 0 === strpos($test->getName(), 'testLegacy') && !isset($this->testsWithWarnings[$test->getName()]) && !in_array('legacy', $groups, true)) {
            $result = $test->getTestResultObject();

            if (method_exists($result, 'addWarning')) {
                $result->addWarning($test, new \PHPUnit_Framework_Warning('Using the "testLegacy" prefix to mark tests as legacy is deprecated since version 3.3 and will be removed in 4.0. Use the "@group legacy" notation instead to add the test to the legacy group.'), $time);
            }
        }

        if ($test instanceof \PHPUnit_Framework_TestCase && strpos($className, '\Legacy') && !isset($this->testsWithWarnings[$test->getName()]) && !in_array('legacy', $classGroups, true)) {
            $result = $test->getTestResultObject();

            if (method_exists($result, 'addWarning')) {
                $result->addWarning($test, new \PHPUnit_Framework_Warning('Using the "Legacy" prefix to mark all tests of a class as legacy is deprecated since version 3.3 and will be removed in 4.0. Use the "@group legacy" notation instead to add the test to the legacy group.'), $time);
            }
        }
    }

    public function handleError($type, $msg, $file, $line, $context)
    {
        if (E_USER_DEPRECATED !== $type && E_DEPRECATED !== $type) {
            $h = $this->previousErrorHandler;

            return $h ? $h($type, $msg, $file, $line, $context) : false;
        }
        if (error_reporting()) {
            $msg = 'Unsilenced deprecation: '.$msg;
        }
        $this->gatheredDeprecations[] = $msg;
    }

    private function isInstanceOfPHPUnitTestSuite($object)
    {
        return $object instanceof \PHPUnit_Framework_TestSuite || $object instanceof TestSuite6;
    }

    private function isInstanceOfPHPUnitTestCase($object)
    {
        return $object instanceof \PHPUnit_Framework_TestCase || $object instanceof TestCase6;
    }
}
