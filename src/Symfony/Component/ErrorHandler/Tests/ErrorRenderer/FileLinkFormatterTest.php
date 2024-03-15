<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\ErrorHandler\Tests\ErrorRenderer;

use PHPUnit\Framework\TestCase;
use Symfony\Component\ErrorHandler\ErrorRenderer\FileLinkFormatter;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

class FileLinkFormatterTest extends TestCase
{
    public function testWhenNoFileLinkFormatAndNoRequest()
    {
        $sut = new FileLinkFormatter([]);

        $this->assertFalse($sut->format('/kernel/root/src/my/very/best/file.php', 3));
    }

    public function testAfterUnserialize()
    {
        if (get_cfg_var('xdebug.file_link_format')) {
            // There is no way to override "xdebug.file_link_format" option in a test.
            $this->markTestSkipped('php.ini has a custom option for "xdebug.file_link_format".');
        }

        $ide = $_ENV['SYMFONY_IDE'] ?? $_SERVER['SYMFONY_IDE'] ?? null;
        $_ENV['SYMFONY_IDE'] = $_SERVER['SYMFONY_IDE'] = null;
        $sut = unserialize(serialize(new FileLinkFormatter()));

        $this->assertSame('file:///kernel/root/src/my/very/best/file.php#L3', $sut->format('/kernel/root/src/my/very/best/file.php', 3));

        if (null === $ide) {
            unset($_ENV['SYMFONY_IDE'], $_SERVER['SYMFONY_IDE']);
        } else {
            $_ENV['SYMFONY_IDE'] = $_SERVER['SYMFONY_IDE'] = $ide;
        }
    }

    public function testWhenFileLinkFormatAndNoRequest()
    {
        $file = __DIR__.\DIRECTORY_SEPARATOR.'file.php';

        $sut = new FileLinkFormatter('debug://open?url=file://%f&line=%l', new RequestStack());

        $this->assertSame("debug://open?url=file://$file&line=3", $sut->format($file, 3));
    }

    public function testWhenNoFileLinkFormatAndRequest()
    {
        $file = __DIR__.\DIRECTORY_SEPARATOR.'file.php';
        $requestStack = new RequestStack();
        $request = new Request();
        $requestStack->push($request);

        $request->server->set('SERVER_NAME', 'www.example.org');
        $request->server->set('SERVER_PORT', 80);
        $request->server->set('SCRIPT_NAME', '/index.php');
        $request->server->set('SCRIPT_FILENAME', '/public/index.php');
        $request->server->set('REQUEST_URI', '/index.php/example');

        $sut = new FileLinkFormatter([], $requestStack, __DIR__, '/_profiler/open?file=%f&line=%l#line%l');

        $this->assertSame('http://www.example.org/_profiler/open?file=file.php&line=3#line3', $sut->format($file, 3));
    }

    public function testIdeFileLinkFormat()
    {
        $file = __DIR__.\DIRECTORY_SEPARATOR.'file.php';

        $sut = new FileLinkFormatter('atom');

        $this->assertSame("atom://core/open/file?filename=$file&line=3", $sut->format($file, 3));
    }

    public function testSerialize()
    {
        $this->assertInstanceOf(FileLinkFormatter::class, unserialize(serialize(new FileLinkFormatter())));
    }

    /**
     * @dataProvider providePathMappings
     */
    public function testIdeFileLinkFormatWithPathMappingParameters($mappings)
    {
        $params = array_reduce($mappings, function ($c, $m) {
            return "$c&".implode('>', $m);
        }, '');
        $sut = new FileLinkFormatter("vscode://file/%f:%l$params");
        foreach ($mappings as $mapping) {
            $fileGuest = $mapping['guest'].'file.php';
            $fileHost = $mapping['host'].'file.php';
            $this->assertSame("vscode://file/$fileHost:3", $sut->format($fileGuest, 3));
        }
    }

    public static function providePathMappings()
    {
        yield 'single path mapping' => [
            [
                [
                    'guest' => '/var/www/app/',
                    'host' => '/user/name/project/',
                ],
            ],
        ];
        yield 'multiple path mapping' => [
            [
                [
                    'guest' => '/var/www/app/',
                    'host' => '/user/name/project/',
                ],
                [
                    'guest' => '/var/www/app2/',
                    'host' => '/user/name/project2/',
                ],
            ],
        ];
    }
}
