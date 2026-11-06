<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HttpKernel\Tests;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\IgnoreDeprecations;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\Kernel\FileLocator;
use Symfony\Component\DependencyInjection\Kernel\KernelInterface;
use Symfony\Component\DependencyInjection\ServicesResetter;
use Symfony\Component\DependencyInjection\ServicesResetterInterface;
use Symfony\Component\HttpKernel\Config\FileLocator as LegacyFileLocator;
use Symfony\Component\HttpKernel\DependencyInjection\ServicesResetter as LegacyServicesResetter;
use Symfony\Component\HttpKernel\DependencyInjection\ServicesResetterInterface as LegacyServicesResetterInterface;

class MovedClassesTest extends TestCase
{
    #[RunInSeparateProcess]
    public function testNewInstancesSatisfyLegacyTypes()
    {
        $this->assertFalse(class_exists(LegacyFileLocator::class, false));
        $this->assertFalse(class_exists(LegacyServicesResetter::class, false));
        $this->assertFalse(interface_exists(LegacyServicesResetterInterface::class, false));

        $locator = new FileLocator($this->createStub(KernelInterface::class));
        $resetter = new ServicesResetter(new \ArrayIterator(), []);

        $this->assertSame($locator, (static fn (LegacyFileLocator $locator) => $locator)($locator));
        $this->assertSame($resetter, (static fn (LegacyServicesResetter $resetter) => $resetter)($resetter));
        $this->assertSame($resetter, (static fn (LegacyServicesResetterInterface $resetter) => $resetter)($resetter));
    }

    #[DataProvider('provideLegacyNames')]
    #[Group('legacy')]
    #[IgnoreDeprecations]
    #[RunInSeparateProcess]
    public function testLoadingTheLegacyNameFirstTriggersADeprecation(string $legacyName, string $name, string $deprecation)
    {
        $this->expectUserDeprecationMessage('Since symfony/http-kernel 8.1: '.$deprecation);

        $this->assertSame($name, (new \ReflectionClass($legacyName))->getName());
    }

    public static function provideLegacyNames(): iterable
    {
        yield [LegacyFileLocator::class, FileLocator::class, 'The "Symfony\Component\HttpKernel\Config\FileLocator" class is deprecated, use "Symfony\Component\DependencyInjection\Kernel\FileLocator" instead.'];
        yield [LegacyServicesResetter::class, ServicesResetter::class, 'The "Symfony\Component\HttpKernel\DependencyInjection\ServicesResetter" class is deprecated, use "Symfony\Component\DependencyInjection\ServicesResetter" from the DependencyInjection component instead.'];
        yield [LegacyServicesResetterInterface::class, ServicesResetterInterface::class, 'The "Symfony\Component\HttpKernel\DependencyInjection\ServicesResetterInterface" interface is deprecated, use "Symfony\Component\DependencyInjection\ServicesResetterInterface" from the DependencyInjection component instead.'];
    }
}
