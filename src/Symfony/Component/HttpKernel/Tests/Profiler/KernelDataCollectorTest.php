<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HttpKernel\Tests\Profiler;

use Symfony\Component\HttpKernel\Bundle\Bundle;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Kernel;
use Symfony\Component\Config\Loader\LoaderInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Profiler\KernelDataCollector;

class KernelDataCollectorTest extends \PHPUnit_Framework_TestCase
{
    public function testCollect()
    {
        $kernel = new KernelForTest('test', true);
        $requestStack = new RequestStack();
        $requestStack->push(new Request());
        $c = new KernelDataCollector($kernel);
        $profileData = $c->getCollectedData();
        $this->assertInstanceOf('Symfony\Component\Profiler\ProfileData\ConfigData', $profileData);

        $this->assertNull($profileData->getApplicationName());
        $this->assertNull($profileData->getApplicationVersion());
        $this->assertSame('test', $profileData->getEnv());
        $this->assertTrue($profileData->isDebug());
        $this->assertSame('testkernel', $profileData->getAppName());
        $this->assertSame(PHP_VERSION, $profileData->getPhpVersion());
        $this->assertSame(Kernel::VERSION, $profileData->getSymfonyVersion());
        $this->assertSame($this->currentSymfonyState(), $profileData->getSymfonyState());
        if ('' !== Kernel::EXTRA_VERSION) {
            $this->assertSame(strtolower(Kernel::EXTRA_VERSION), $profileData->getSymfonyState());
        }

        $this->assertCount(1, $profileData->getBundles());

        $unserializedProfileData = unserialize(serialize($profileData));

        $this->assertCount(1, $unserializedProfileData->getBundles());
    }

    private function currentSymfonyState()
    {
        $now = new \DateTime();
        $eom = \DateTime::createFromFormat('m/Y', Kernel::END_OF_MAINTENANCE)->modify('last day of this month');
        $eol = \DateTime::createFromFormat('m/Y', Kernel::END_OF_LIFE)->modify('last day of this month');

        if ($now > $eol) {
            $versionState = 'eol';
        } elseif ($now > $eom) {
            $versionState = 'eom';
        } elseif ('' !== Kernel::EXTRA_VERSION) {
            $versionState = strtolower(Kernel::EXTRA_VERSION);
        } else {
            $versionState = 'stable';
        }

        return $versionState;
    }
}

class KernelForTest extends Kernel
{
    public function getName()
    {
        return 'testkernel';
    }

    public function registerBundles()
    {
    }

    public function getBundles()
    {
        return array(
            new BundleForTest(),
        );
    }

    public function registerContainerConfiguration(LoaderInterface $loader)
    {
    }
}

class BundleForTest extends Bundle
{
}
