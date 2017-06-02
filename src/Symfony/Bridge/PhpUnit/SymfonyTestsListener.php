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

use PHPUnit\Framework\BaseTestListener;
use PHPUnit\Framework\Test;
use PHPUnit\Framework\TestSuite;
use PHPUnit\Framework\Warning;

if (class_exists('PHPUnit_Runner_Version') && version_compare(\PHPUnit_Runner_Version::id(), '6.0.0', '<')) {
    class_alias('Symfony\Bridge\PhpUnit\Legacy\SymfonyTestsListener', 'Symfony\Bridge\PhpUnit\SymfonyTestsListener');

    return;
}

/**
 * Collects and replays skipped tests.
 *
 * @author Nicolas Grekas <p@tchwork.com>
 *
 * @final
 */
class SymfonyTestsListener extends BaseTestListener
{
    private $trait;

    public function __construct(array $mockedNamespaces = array())
    {
        $this->trait = new Legacy\SymfonyTestsListenerTrait($mockedNamespaces);
    }

    public function globalListenerDisabled()
    {
        $this->trait->globalListenerDisabled();
    }

    public function startTestSuite(TestSuite $suite)
    {
        return $this->trait->startTestSuite($suite);
    }

    public function addSkippedTest(Test $test, \Exception $e, $time)
    {
        return $this->trait->addSkippedTest($test, $e, $time);
    }

    public function startTest(Test $test)
    {
        return $this->trait->startTest($test);
    }

    public function addWarning(Test $test, Warning $e, $time)
    {
        return $this->trait->addWarning($test, $e, $time);
    }

    public function endTest(Test $test, $time)
    {
        return $this->trait->endTest($test, $time);
    }
}
