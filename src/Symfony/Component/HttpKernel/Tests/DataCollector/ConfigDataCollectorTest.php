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
        $this->assertSame('testkernel', $c->getAppName());
        $this->assertMatchesRegularExpression('~^'.preg_quote($c->getPhpVersion(), '~').'~', \PHP_VERSION);
        $this->assertMatchesRegularExpression('~'.preg_quote((string) $c->getPhpVersionExtra(), '~').'$~', \PHP_VERSION);
        $this->assertSame(\PHP_INT_SIZE * 8, $c->getPhpArchitecture());
        $this->assertSame(class_exists('Locale', false) && \Locale::getDefault() ? \Locale::getDefault() : 'n/a', $c->getPhpIntlLocale());
        $this->assertSame(date_default_timezone_get(), $c->getPhpTimezone());
        $this->assertSame(Kernel::VERSION, $c->getSymfonyVersion());
        $this->assertNull($c->getToken());
        $this->assertSame(\extension_loaded('xdebug'), $c->hasXDebug());
        $this->assertSame(\extension_loaded('Zend OPcache') && filter_var(ini_get('opcache.enable'), \FILTER_VALIDATE_BOOLEAN), $c->hasZendOpcache());
        $this->assertSame(\extension_loaded('apcu') && filter_var(ini_get('apc.enabled'), \FILTER_VALIDATE_BOOLEAN), $c->hasApcu());
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
        return [];
    }

    public function registerContainerConfiguration(LoaderInterface $loader)
    {
    }
}
