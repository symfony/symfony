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

use PHPUnit\Framework\Test;
use PHPUnit\Framework\TestListener;
use PHPUnit\Framework\TestListenerDefaultImplementation;
use PHPUnit\Framework\TestSuite;
use PHPUnit\Framework\Warning;

// Using an early return instead of a else does not work when using the PHPUnit phar due to some weird PHP behavior (the class
// gets defined without executing the code before it and so the definition is not properly conditional)
if (class_exists('PHPUnit_Runner_Version') && version_compare(\PHPUnit_Runner_Version::id(), '6.0.0', '<')) {
    class_alias('Symfony\Bridge\PhpUnit\Legacy\SymfonyTestsListener', 'Symfony\Bridge\PhpUnit\SymfonyTestsListener');
} elseif (version_compare(\PHPUnit\Runner\Version::id(), '7.0.0', '<')) {
    class_alias('Symfony\Bridge\PhpUnit\Legacy\SymfonyTestsListenerPhpunit6', 'Symfony\Bridge\PhpUnit\SymfonyTestsListener');
} else {
    /**
     * Collects and replays skipped tests.
     *
     * @author Nicolas Grekas <p@tchwork.com>
     *
     * @final
     */
    class SymfonyTestsListener implements TestListener
    {
        use TestListenerDefaultImplementation;

        private $trait;

        public function __construct(array $mockedNamespaces = array())
        {
            $this->trait = new Legacy\SymfonyTestsListenerTrait($mockedNamespaces);
        }

        public function globalListenerDisabled()
        {
            $this->trait->globalListenerDisabled();
        }

        public function startTestSuite(TestSuite $suite): void
        {
            $this->trait->startTestSuite($suite);
        }

        public function addSkippedTest(Test $test, \Throwable $t, float $time): void
        {
            $this->trait->addSkippedTest($test, $t, $time);
        }

        public function startTest(Test $test): void
        {
            $this->trait->startTest($test);
        }

        public function addWarning(Test $test, Warning $e, float $time): void
        {
            $this->trait->addWarning($test, $e, $time);
        }

        public function endTest(Test $test, float $time): void
        {
            $this->trait->endTest($test, $time);
        }
    }
}
