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

use PHPUnit\Framework\BaseTestListener;
use PHPUnit\Framework\Test;
use PHPUnit\Framework\TestSuite;
use PHPUnit\Framework\Warning;

/**
 * Collects and replays skipped tests.
 *
 * @author Nicolas Grekas <p@tchwork.com>
 *
 * @internal
 */
class SymfonyTestsListenerForV6 extends BaseTestListener
{
    private $trait;

    public function __construct(array $mockedNamespaces = array())
    {
        $this->trait = new SymfonyTestsListenerTrait($mockedNamespaces);
    }

    public function globalListenerDisabled()
    {
        $this->trait->globalListenerDisabled();
    }

    public function startTestSuite(TestSuite $suite)
    {
        $this->trait->startTestSuite($suite);
    }

    public function addSkippedTest(Test $test, \Exception $e, $time)
    {
        $this->trait->addSkippedTest($test, $e, $time);
    }

    public function startTest(Test $test)
    {
        $this->trait->startTest($test);
    }

    public function addWarning(Test $test, Warning $e, $time)
    {
        $this->trait->addWarning($test, $e, $time);
    }

    public function endTest(Test $test, $time)
    {
        $this->trait->endTest($test, $time);
    }
}
