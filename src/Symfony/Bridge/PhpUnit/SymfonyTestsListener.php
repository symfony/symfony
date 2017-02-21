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

if (class_exists('PHPUnit_Framework_BaseTestListener')) {
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
    use SymfonyTestsListenerTrait;

    public function startTestSuite(TestSuite $suite)
    {
        return $this->doStartTestSuite($suite);
    }

    public function addSkippedTest(Test $test, \Exception $e, $time)
    {
        return $this->doAddSkippedTest($test, $e, $time);
    }

    public function startTest(Test $test)
    {
        return $this->doStartTest($test);
    }

    public function addWarning(Test $test, Warning $e, $time)
    {
        return $this->doAddWarning($test, $e, $time);
    }

    public function endTest(Test $test, $time)
    {
        return $this->doEndTest($test, $time);
    }
}
