<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HttpKernel\Tests\Debug;

use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Debug\FileLinkFormatter;
use Symfony\Component\Routing\Generator\UrlGenerator;
use Symfony\Component\Routing\RequestContext;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;

class FileLinkFormatterTest extends TestCase
{
    public function testWhenNoFileLinkFormat()
    {
        $sut = new FileLinkFormatter();

        $this->assertFalse($sut->format('/kernel/root/src/my/very/best/file.php', 3));
    }

    public function testWhenFileLinkFormat()
    {
        $file = __DIR__.DIRECTORY_SEPARATOR.'file.php';

        $sut = new FileLinkFormatter('debug://open?url=file://%f&line=%l');

        $this->assertSame("debug://open?url=file://$file&line=3", $sut->format($file, 3));
    }

    public function testWhenFileLinkFormatAndRequestStack()
    {
        $file = __DIR__.DIRECTORY_SEPARATOR.'file.php';
        $baseDir = __DIR__;
        $requestStack = new RequestStack();
        $request = new Request();
        $requestStack->push($request);

        $sut = new FileLinkFormatter('debug://open?url=file://%f&line=%l', $requestStack, __DIR__, '/_profiler/open?file=%f&line=%l#line%l');

        $this->assertSame("debug://open?url=file://$file&line=3", $sut->format($file, 3));
    }

    public function testWhenFileLinkFormatAndRouter()
    {
        $file = __DIR__.DIRECTORY_SEPARATOR.'file.php';
        $baseDir = __DIR__;
        $router = $this->getRouter();

        $sut = new FileLinkFormatter('debug://open?url=file://%f&line=%l', null, $baseDir, '/_profiler/open?file=%f&line=%l#line%l', $router);

        $this->assertSame("debug://open?url=file://$file&line=3", $sut->format($file, 3));
    }

    public function testWhenNoFileLinkFormatAndRouter()
    {
        $file = __DIR__.DIRECTORY_SEPARATOR.'file.php';
        $baseDir = __DIR__;
        $router = $this->getRouter();

        $sut = new FileLinkFormatter(null, null, $baseDir, '?file=%f&line=%l#line%l', $router);

        $this->assertSame('/_profiler_customized?file=file.php&line=3#line3', $sut->format($file, 3));
    }

    private function getRouter()
    {
        $routes = new RouteCollection();
        $routes->add('_profiler_open_file', new Route('/_profiler_customized'));

        return new UrlGenerator($routes, new RequestContext());
    }
}
