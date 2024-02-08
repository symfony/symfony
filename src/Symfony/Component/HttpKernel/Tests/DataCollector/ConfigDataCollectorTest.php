<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HttpKernel\Tests\DataCollector;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Config\Loader\LoaderInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\DataCollector\ConfigDataCollector;
use Symfony\Component\HttpKernel\Kernel;

class ConfigDataCollectorTest extends TestCase
{
    public function testCollect()
    {
        $kernel = new KernelForTest('test', true);
        $c = new ConfigDataCollector();
        $c->setKernel($kernel);
        $c->collect(new Request(), new Response());

        $this->assertSame('test', $c->getEnv());
        $this->assertTrue($c->isDebug());
        $this->assertSame('config', $c->getName());
        $this->assertMatchesRegularExpression('~^'.preg_quote($c->getPhpVersion(), '~').'~', \PHP_VERSION);
        $this->assertMatchesRegularExpression('~'.preg_quote((string) $c->getPhpVersionExtra(), '~').'$~', \PHP_VERSION);
        $this->assertSame(\PHP_INT_SIZE * 8, $c->getPhpArchitecture());
        $this->assertSame(class_exists(\Locale::class, false) && \Locale::getDefault() ? \Locale::getDefault() : 'n/a', $c->getPhpIntlLocale());
        $this->assertSame(date_default_timezone_get(), $c->getPhpTimezone());
        $this->assertSame(Kernel::VERSION, $c->getSymfonyVersion());
        $this->assertSame(4 === Kernel::MINOR_VERSION, $c->isSymfonyLts());
        $this->assertNull($c->getToken());
        $this->assertSame(\extension_loaded('xdebug'), $c->hasXDebug());
        $this->assertSame(\extension_loaded('Zend OPcache') && filter_var(\ini_get('opcache.enable'), \FILTER_VALIDATE_BOOL), $c->hasZendOpcache());
        $this->assertSame(\extension_loaded('apcu') && filter_var(\ini_get('apc.enabled'), \FILTER_VALIDATE_BOOL), $c->hasApcu());
        $this->assertSame(sprintf('%s.%s', Kernel::MAJOR_VERSION, Kernel::MINOR_VERSION), $c->getSymfonyMinorVersion());
        $this->assertContains($c->getSymfonyState(), ['eol', 'eom', 'dev', 'stable']);

        $eom = \DateTimeImmutable::createFromFormat('d/m/Y', '01/'.Kernel::END_OF_MAINTENANCE)->format('F Y');
        $eol = \DateTimeImmutable::createFromFormat('d/m/Y', '01/'.Kernel::END_OF_LIFE)->format('F Y');
        $this->assertSame($eom, $c->getSymfonyEom());
        $this->assertSame($eol, $c->getSymfonyEol());
    }

    public function testCollectWithoutKernel()
    {
        $c = new ConfigDataCollector();
        $c->collect(new Request(), new Response());

        $this->assertSame('n/a', $c->getEnv());
        $this->assertSame('n/a', $c->isDebug());
        $this->assertSame('config', $c->getName());
        $this->assertMatchesRegularExpression('~^'.preg_quote($c->getPhpVersion(), '~').'~', \PHP_VERSION);
        $this->assertMatchesRegularExpression('~'.preg_quote((string) $c->getPhpVersionExtra(), '~').'$~', \PHP_VERSION);
        $this->assertSame(\PHP_INT_SIZE * 8, $c->getPhpArchitecture());
        $this->assertSame(class_exists(\Locale::class, false) && \Locale::getDefault() ? \Locale::getDefault() : 'n/a', $c->getPhpIntlLocale());
        $this->assertSame(date_default_timezone_get(), $c->getPhpTimezone());
        $this->assertSame(Kernel::VERSION, $c->getSymfonyVersion());
        $this->assertSame(4 === Kernel::MINOR_VERSION, $c->isSymfonyLts());
        $this->assertNull($c->getToken());
        $this->assertSame(\extension_loaded('xdebug'), $c->hasXDebug());
        $this->assertSame(\extension_loaded('Zend OPcache') && filter_var(\ini_get('opcache.enable'), \FILTER_VALIDATE_BOOL), $c->hasZendOpcache());
        $this->assertSame(\extension_loaded('apcu') && filter_var(\ini_get('apc.enabled'), \FILTER_VALIDATE_BOOL), $c->hasApcu());
        $this->assertSame(sprintf('%s.%s', Kernel::MAJOR_VERSION, Kernel::MINOR_VERSION), $c->getSymfonyMinorVersion());
        $this->assertContains($c->getSymfonyState(), ['eol', 'eom', 'dev', 'stable']);

        $eom = \DateTimeImmutable::createFromFormat('d/m/Y', '01/'.Kernel::END_OF_MAINTENANCE)->format('F Y');
        $eol = \DateTimeImmutable::createFromFormat('d/m/Y', '01/'.Kernel::END_OF_LIFE)->format('F Y');
        $this->assertSame($eom, $c->getSymfonyEom());
        $this->assertSame($eol, $c->getSymfonyEol());
    }
}

class KernelForTest extends Kernel
{
    public function registerBundles(): iterable
    {
    }

    public function getBundles(): array
    {
        return [];
    }

    public function registerContainerConfiguration(LoaderInterface $loader): void
    {
    }
}
