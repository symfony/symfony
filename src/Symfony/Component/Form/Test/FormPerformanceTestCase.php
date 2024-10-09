<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Form\Test;

use Symfony\Component\Form\Test\Traits\RunTestTrait;

/**
 * Base class for performance tests.
 *
 * Copied from Doctrine 2's OrmPerformanceTestCase.
 *
 * @author robo
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
abstract class FormPerformanceTestCase extends FormIntegrationTestCase
{
    use RunTestTrait;

    /**
     * @var int
     */
    protected $maxRunningTime = 0;

    private function doRunTest(): mixed
    {
        $s = microtime(true);
        $result = parent::runTest();
        $time = microtime(true) - $s;

        if (0 != $this->maxRunningTime && $time > $this->maxRunningTime) {
            $this->fail(sprintf('expected running time: <= %s but was: %s', $this->maxRunningTime, $time));
        }

        $this->expectNotToPerformAssertions();

        return $result;
    }

    /**
     * @throws \InvalidArgumentException
     */
    public function setMaxRunningTime(int $maxRunningTime)
    {
        if ($maxRunningTime < 0) {
            throw new \InvalidArgumentException();
        }

        $this->maxRunningTime = $maxRunningTime;
    }

    public function getMaxRunningTime(): int
    {
        return $this->maxRunningTime;
    }
}
