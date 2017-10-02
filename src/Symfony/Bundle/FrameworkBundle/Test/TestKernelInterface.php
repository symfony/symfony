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
     * @param string      $tmpDir     Directory holds all the kernel temporary data
     * @param string      $testCase   Directory name where kernel configs are stored
     * @param string      $configDir  Path to directory with test cases configurations
     * @param string      $rootConfig Name of the application config file
     * @param string|null $rootDir    Optional path to root directory of test application
     */
    public function setTestKernelConfiguration($tmpDir, $testCase, $configDir, $rootConfig, $rootDir = null);
}
