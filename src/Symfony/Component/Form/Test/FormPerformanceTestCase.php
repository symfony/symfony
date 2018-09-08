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
    /**
     * @var int
     */
    protected $maxRunningTime = 0;

    /**
     * {@inheritdoc}
     */
    protected function runTest()
    {
        $s = microtime(true);
        parent::runTest();
        $time = microtime(true) - $s;

        if (0 != $this->maxRunningTime && $time > $this->maxRunningTime) {
            $this->fail(sprintf('expected running time: <= %s but was: %s', $this->maxRunningTime, $time));
        }
    }

    /**
     * @param int $maxRunningTime
     *
     * @throws \InvalidArgumentException
     */
    public function setMaxRunningTime($maxRunningTime)
    {
        if (\is_int($maxRunningTime) && $maxRunningTime >= 0) {
            $this->maxRunningTime = $maxRunningTime;
        } else {
            throw new \InvalidArgumentException();
        }
    }

    /**
     * @return int
     */
    public function getMaxRunningTime()
    {
        return $this->maxRunningTime;
    }
}
