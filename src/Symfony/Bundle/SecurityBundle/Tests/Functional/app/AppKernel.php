<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\SecurityBundle\Tests\Functional\app;

use Doctrine\ORM\Version;
use Symfony\Bundle\FrameworkBundle\Test\TestKernel;
use Symfony\Component\Config\Loader\LoaderInterface;

class AppKernel extends TestKernel
{
    public function setTestKernelConfiguration($tempDir, $testCase, $configDir, $rootConfig, $rootDir = null)
    {
        parent::setTestKernelConfiguration($tempDir, $testCase, $configDir, $rootConfig, $rootDir);

        // replace the name with custom once there is an information about test config
        $this->name = parent::getName().substr(md5($this->rootConfig), -16);
    }

    public function registerContainerConfiguration(LoaderInterface $loader)
    {
        $loader->load($this->rootConfig);

        // to be removed once https://github.com/doctrine/DoctrineBundle/pull/684 is merged
        if ('Acl' === $this->testCase && class_exists(Version::class)) {
            $loader->load(__DIR__.'/Acl/doctrine.yml');
        }
    }
}