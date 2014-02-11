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
     * @var    integer
     */
    protected $maxRunningTime = 0;

    /**
     */
    protected function runTest()
    {
        $s = microtime(true);
        parent::runTest();
        $time = microtime(true) - $s;

        if ($this->maxRunningTime != 0 && $time > $this->maxRunningTime) {
            $this->fail(
                sprintf(
                    'expected running time: <= %s but was: %s',

                    $this->maxRunningTime,
                    $time
                )
            );
        }
    }

    /**
     * @param  integer $maxRunningTime
     * @throws \InvalidArgumentException
     */
    public function setMaxRunningTime($maxRunningTime)
    {
        if (is_integer($maxRunningTime) && $maxRunningTime >= 0) {
            $this->maxRunningTime = $maxRunningTime;
        } else {
            throw new \InvalidArgumentException();
        }
    }

    /**
     * @return integer
     * @since  Method available since Release 2.3.0
     */
    public function getMaxRunningTime()
    {
        return $this->maxRunningTime;
    }
}
