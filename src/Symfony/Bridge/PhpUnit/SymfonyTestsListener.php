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

/**
 * Collects and replays skipped tests.
 *
 * @author Nicolas Grekas <p@tchwork.com>
 */
class SymfonyTestsListener extends \PHPUnit_Framework_BaseTestListener
{
    private static $globallyEnabled = false;
    private $state = -1;
    private $skippedFile = false;
    private $wasSkipped = array();
    private $isSkipped = array();

    /**
     * @param array $mockedNamespaces List of namespaces, indexed by mocked features (time-sensitive)
     */
    public function __construct(array $mockedNamespaces = array())
    {
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

    public function startTestSuite(\PHPUnit_Framework_TestSuite $suite)
    {
        $suiteName = $suite->getName();

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
                    if ($test instanceof \PHPUnit_Framework_TestSuite) {
                        if (!class_exists($test->getName(), false)) {
                            $testSuites[] = $test;
                            continue;
                        }
                        $groups = \PHPUnit_Util_Test::getGroups($test->getName());
                        if (in_array('time-sensitive', $groups, true)) {
                            ClockMock::register($test->getName());
                        }
                    }
                }
            }
        } elseif (2 === $this->state) {
            $skipped = array();
            foreach ($suite->tests() as $test) {
                if (!$test instanceof \PHPUnit_Framework_TestCase
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
        if (-2 < $this->state && $test instanceof \PHPUnit_Framework_TestCase) {
            $groups = \PHPUnit_Util_Test::getGroups(get_class($test), $test->getName());

            if (in_array('time-sensitive', $groups, true)) {
                ClockMock::register(get_class($test));
                ClockMock::withClockMock(true);
            }
        }
    }

    public function endTest(\PHPUnit_Framework_Test $test, $time)
    {
        if (-2 < $this->state && $test instanceof \PHPUnit_Framework_TestCase) {
            $groups = \PHPUnit_Util_Test::getGroups(get_class($test), $test->getName());

            if (in_array('time-sensitive', $groups, true)) {
                ClockMock::withClockMock(false);
            }
        }
    }
}
