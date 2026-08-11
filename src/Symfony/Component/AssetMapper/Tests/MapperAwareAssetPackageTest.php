<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\AssetMapper\Tests;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Asset\Context\RequestStackContext;
use Symfony\Component\Asset\PackageInterface;
use Symfony\Component\Asset\PathPackage;
use Symfony\Component\Asset\VersionStrategy\EmptyVersionStrategy;
use Symfony\Component\AssetMapper\AssetMapperInterface;
use Symfony\Component\AssetMapper\MapperAwareAssetPackage;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

class MapperAwareAssetPackageTest extends TestCase
{
    public function testGetVersion()
    {
        $inner = $this->createMock(PackageInterface::class);
        $inner->expects($this->once())
            ->method('getVersion')
            ->with('foo')
            ->willReturn('2.0');

        $assetMapperPackage = new MapperAwareAssetPackage($inner, $this->createStub(AssetMapperInterface::class));

        $this->assertSame('2.0', $assetMapperPackage->getVersion('foo'));
    }

    #[DataProvider('getUrlTests')]
    public function testGetUrl(string $path, string $expectedPathSentToInner)
    {
        $inner = $this->createMock(PackageInterface::class);
        $inner->expects($this->once())
            ->method('getUrl')
            ->with($expectedPathSentToInner)
            ->willReturnCallback(static fn ($path) => '/'.$path);
        $assetMapper = $this->createStub(AssetMapperInterface::class);
        $assetMapper
            ->method('getPublicPath')
            ->willReturnCallback(static function ($path) {
                switch ($path) {
                    case 'images/foo.png':
                        return '/assets/images/foo.123456.png';
                    case 'more-styles.css':
                        return '/assets/more-styles.abcd123.css';
                    default:
                        return null;
                }
            });

        $assetMapperPackage = new MapperAwareAssetPackage($inner, $assetMapper);
        $this->assertSame('/'.$expectedPathSentToInner, $assetMapperPackage->getUrl($path));
    }

    #[DataProvider('getDevServerUrlTests')]
    public function testGetUrlWithDevServer(string $requestUri, string $scriptName, string $path, string $expectedUrl)
    {
        $requestStack = $this->createRequestStack($requestUri, $scriptName);
        $assetMapperPackage = new MapperAwareAssetPackage(
            $this->createInnerPackage($requestStack),
            $this->createAssetMapper(),
            $requestStack,
            '/assets/',
        );

        $this->assertSame($expectedUrl, $assetMapperPackage->getUrl($path));
    }

    public static function getDevServerUrlTests(): iterable
    {
        yield 'front controller at the document root' => [
            'requestUri' => '/index.php/blog',
            'scriptName' => '/index.php',
            'path' => 'images/foo.png',
            'expectedUrl' => '/index.php/assets/images/foo.123456.png',
        ];

        yield 'front controller below a base path' => [
            'requestUri' => '/public/index.php/blog',
            'scriptName' => '/public/index.php',
            'path' => 'images/foo.png',
            'expectedUrl' => '/public/index.php/assets/images/foo.123456.png',
        ];

        // the import map renders paths that the asset mapper already resolved
        yield 'already resolved public path' => [
            'requestUri' => '/index.php/blog',
            'scriptName' => '/index.php',
            'path' => 'assets/app.123456.js',
            'expectedUrl' => '/index.php/assets/app.123456.js',
        ];

        yield 'remote path' => [
            'requestUri' => '/index.php/blog',
            'scriptName' => '/index.php',
            'path' => 'https://cdn.example.com/assets/app.123456.js',
            'expectedUrl' => 'https://cdn.example.com/assets/app.123456.js',
        ];

        yield 'asset outside of the public prefix' => [
            'requestUri' => '/index.php/blog',
            'scriptName' => '/index.php',
            'path' => 'favicon.ico',
            'expectedUrl' => '/favicon.ico',
        ];

        yield 'no front controller in the url' => [
            'requestUri' => '/blog',
            'scriptName' => '/index.php',
            'path' => 'images/foo.png',
            'expectedUrl' => '/assets/images/foo.123456.png',
        ];
    }

    public function testGetUrlWithoutDevServer()
    {
        $requestStack = $this->createRequestStack('/index.php/blog', '/index.php');
        $assetMapperPackage = new MapperAwareAssetPackage(
            $this->createInnerPackage($requestStack),
            $this->createAssetMapper(),
            $requestStack,
        );

        $this->assertSame('/assets/images/foo.123456.png', $assetMapperPackage->getUrl('images/foo.png'));
    }

    public function testGetUrlWithoutRequest()
    {
        $requestStack = new RequestStack();
        $assetMapperPackage = new MapperAwareAssetPackage(
            $this->createInnerPackage($requestStack),
            $this->createAssetMapper(),
            $requestStack,
            '/assets/',
        );

        $this->assertSame('/assets/images/foo.123456.png', $assetMapperPackage->getUrl('images/foo.png'));
    }

    public static function getUrlTests(): iterable
    {
        yield 'path_is_found_in_asset_mapper' => [
            'path' => 'images/foo.png',
            'expectedPathSentToInner' => 'assets/images/foo.123456.png',
        ];

        yield 'path_not_found_in_asset_mapper' => [
            'path' => 'styles.css',
            'expectedPathSentToInner' => 'styles.css',
        ];
    }

    private function createAssetMapper(): AssetMapperInterface
    {
        $assetMapper = $this->createStub(AssetMapperInterface::class);
        $assetMapper->method('getPublicPath')->willReturnCallback(
            static fn (string $path) => 'images/foo.png' === $path ? '/assets/images/foo.123456.png' : null
        );

        return $assetMapper;
    }

    private function createRequestStack(string $requestUri, string $scriptName): RequestStack
    {
        $requestStack = new RequestStack();
        $requestStack->push(new Request([], [], [], [], [], [
            'SCRIPT_FILENAME' => '/var/www/public/index.php',
            'SCRIPT_NAME' => $scriptName,
            'REQUEST_URI' => $requestUri,
        ]));

        return $requestStack;
    }

    private function createInnerPackage(RequestStack $requestStack): PackageInterface
    {
        return new PathPackage('', new EmptyVersionStrategy(), new RequestStackContext($requestStack));
    }
}
