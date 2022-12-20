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

        self::assertSame('test', $c->getEnv());
        self::assertTrue($c->isDebug());
        self::assertSame('config', $c->getName());
        self::assertMatchesRegularExpression('~^'.preg_quote($c->getPhpVersion(), '~').'~', \PHP_VERSION);
        self::assertMatchesRegularExpression('~'.preg_quote((string) $c->getPhpVersionExtra(), '~').'$~', \PHP_VERSION);
        self::assertSame(\PHP_INT_SIZE * 8, $c->getPhpArchitecture());
        self::assertSame(class_exists(\Locale::class, false) && \Locale::getDefault() ? \Locale::getDefault() : 'n/a', $c->getPhpIntlLocale());
        self::assertSame(date_default_timezone_get(), $c->getPhpTimezone());
        self::assertSame(Kernel::VERSION, $c->getSymfonyVersion());
        self::assertSame(4 === Kernel::MINOR_VERSION, $c->isSymfonyLts());
        self::assertNull($c->getToken());
        self::assertSame(\extension_loaded('xdebug'), $c->hasXDebug());
        self::assertSame(\extension_loaded('Zend OPcache') && filter_var(\ini_get('opcache.enable'), \FILTER_VALIDATE_BOOLEAN), $c->hasZendOpcache());
        self::assertSame(\extension_loaded('apcu') && filter_var(\ini_get('apc.enabled'), \FILTER_VALIDATE_BOOLEAN), $c->hasApcu());
        self::assertSame(sprintf('%s.%s', Kernel::MAJOR_VERSION, Kernel::MINOR_VERSION), $c->getSymfonyMinorVersion());
        self::assertContains($c->getSymfonyState(), ['eol', 'eom', 'dev', 'stable']);

        $eom = \DateTime::createFromFormat('d/m/Y', '01/'.Kernel::END_OF_MAINTENANCE)->format('F Y');
        $eol = \DateTime::createFromFormat('d/m/Y', '01/'.Kernel::END_OF_LIFE)->format('F Y');
        self::assertSame($eom, $c->getSymfonyEom());
        self::assertSame($eol, $c->getSymfonyEol());
    }

    public function testCollectWithoutKernel()
    {
        $c = new ConfigDataCollector();
        $c->collect(new Request(), new Response());

        self::assertSame('n/a', $c->getEnv());
        self::assertSame('n/a', $c->isDebug());
        self::assertSame('config', $c->getName());
        self::assertMatchesRegularExpression('~^'.preg_quote($c->getPhpVersion(), '~').'~', \PHP_VERSION);
        self::assertMatchesRegularExpression('~'.preg_quote((string) $c->getPhpVersionExtra(), '~').'$~', \PHP_VERSION);
        self::assertSame(\PHP_INT_SIZE * 8, $c->getPhpArchitecture());
        self::assertSame(class_exists(\Locale::class, false) && \Locale::getDefault() ? \Locale::getDefault() : 'n/a', $c->getPhpIntlLocale());
        self::assertSame(date_default_timezone_get(), $c->getPhpTimezone());
        self::assertSame(Kernel::VERSION, $c->getSymfonyVersion());
        self::assertSame(4 === Kernel::MINOR_VERSION, $c->isSymfonyLts());
        self::assertNull($c->getToken());
        self::assertSame(\extension_loaded('xdebug'), $c->hasXDebug());
        self::assertSame(\extension_loaded('Zend OPcache') && filter_var(\ini_get('opcache.enable'), \FILTER_VALIDATE_BOOLEAN), $c->hasZendOpcache());
        self::assertSame(\extension_loaded('apcu') && filter_var(\ini_get('apc.enabled'), \FILTER_VALIDATE_BOOLEAN), $c->hasApcu());
        self::assertSame(sprintf('%s.%s', Kernel::MAJOR_VERSION, Kernel::MINOR_VERSION), $c->getSymfonyMinorVersion());
        self::assertContains($c->getSymfonyState(), ['eol', 'eom', 'dev', 'stable']);

        $eom = \DateTime::createFromFormat('d/m/Y', '01/'.Kernel::END_OF_MAINTENANCE)->format('F Y');
        $eol = \DateTime::createFromFormat('d/m/Y', '01/'.Kernel::END_OF_LIFE)->format('F Y');
        self::assertSame($eom, $c->getSymfonyEom());
        self::assertSame($eol, $c->getSymfonyEol());
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

    public function registerContainerConfiguration(LoaderInterface $loader)
    {
    }
}
