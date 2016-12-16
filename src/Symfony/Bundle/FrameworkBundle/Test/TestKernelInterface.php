<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\FrameworkBundle\Test;

use Symfony\Component\HttpKernel\KernelInterface;

/**
 * Describes base functionality for Kernel classes used in functional / integration tests.
 *
 * @author fduch alex.medwedew@gmail.com
 */
interface TestKernelInterface extends KernelInterface
{
    /**
     * Sets Kernel configuration.
     *
     * @param string      $testCase   Directory name where kernel configs are stored
     * @param string      $configDir  Path to directory with test cases configurations
     * @param string      $rootConfig Name of the application config file
     * @param string|null $rootDir    Optional path to root directory of test application
     */
    public function setTestKernelConfiguration($testCase, $configDir, $rootConfig, $rootDir = null);

    /**
     * Returns temporary application folder that is used to store cache, logs of test kernel.
     * This folder normally should be removed after test case is executed.
     *
     * @return string
     */
    public function getTempAppDir();
}
