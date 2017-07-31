<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\WebServerBundle\Tests\WebServerConfig;

use PHPUnit\Framework\TestCase;
use Symfony\Bundle\WebServerBundle\WebServerConfig;

class WebServerConfigTest extends TestCase
{
    public function testConstructor()
    {
        $config = new WebServerConfig(
            __DIR__.'/fixtures',
            'dev',
            '85.111.31.18:8080',
            __DIR__.'/fixtures/router.php',
            '/usr/bin/php -c /tmp/custom/php.ini'
        );

        $this->assertSame(
            $this->normalizePath(__DIR__.'/fixtures'),
            $this->normalizePath($config->getDocumentRoot())
        );
        $this->assertSame('dev', $config->getEnv());
        $this->assertSame('85.111.31.18:8080', $config->getAddress());
        $this->assertEquals(8080, $config->getPort());
        $this->assertSame(
            $this->normalizePath(__DIR__.'/fixtures/router.php'),
            $this->normalizePath($config->getRouter())
        );
        $this->assertSame('/usr/bin/php -c /tmp/custom/php.ini', $config->getExecutable());
    }

    public function testWillSetCorrectAddressAndPortAutomatically()
    {
        $config = new WebServerConfig(__DIR__.'/fixtures', 'dev');

        $this->assertEquals('127.0.0.1:8000', $config->getAddress());
        $this->assertLessThanOrEqual(8100, $config->getPort());
        $this->assertGreaterThanOrEqual(8000, $config->getPort());
    }

    public function testWillCorrectlyParseAsteriskAndPort()
    {
        $config = new WebServerConfig(__DIR__.'/fixtures', 'dev', '*:8080');

        $this->assertSame('0.0.0.0:8080', $config->getAddress());
        $this->assertEquals(8080, $config->getPort());
    }

    public function testWillParsePlainNumberAsPort()
    {
        $config = new WebServerConfig(__DIR__.'/fixtures', 'dev', '8080');

        $this->assertSame('127.0.0.1:8080', $config->getAddress());
        $this->assertEquals(8080, $config->getPort());
    }

    public function testWillFailForNonNumberPort()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Port "12a" is not valid.');

        $config = new WebServerConfig(__DIR__.'/fixtures', 'dev', '127.0.0.1:12a');
    }

    public function testWillFailIfDocumentRootIsNotADirectory()
    {
        $this->expectException(\InvalidArgumentException::class);
        // symplified to workaround directory separator issues
        $this->expectExceptionMessage('The document root directory');

        $config = new WebServerConfig(__DIR__.'/does-not-exist', 'dev');
    }

    public function testWillFailIfDocumentRootDoesNotContainFrontController()
    {
        $this->expectException(\InvalidArgumentException::class);
        // symplified to workaround directory separator issues
        $this->expectExceptionMessage('Unable to find the front controller under');

        $config = new WebServerConfig(__DIR__.'/fixtures/not-containing-anything', 'dev');
    }

    public function testWillFailIfRouterDirectoryDoesNotContainRouter()
    {
        $this->expectException(\InvalidArgumentException::class);
        // symplified to workaround directory separator issues
        $this->expectExceptionMessage('Router script');

        $config = new WebServerConfig(
            __DIR__.'/fixtures',
            'dev',
            null,
            __DIR__.'/fixtures/not-containing-anything/router.php'
        );
    }

    public function testWillSetRouterToDeaultIfNotPresent()
    {
        $config = new WebServerConfig(__DIR__.'/fixtures', 'dev');

        // router is relative to the WebServerConfig.php file (therefore two levels above)
        $this->assertSame(
            $this->normalizePath(dirname(dirname(__DIR__)).'/Resources/router.php'),
            $this->normalizePath($config->getRouter())
        );
    }

    public function testWillTryToFindExectuableIfNotPresent()
    {
        $config = new WebServerConfig(__DIR__.'/fixtures', 'dev');

        // symplified as path will vary a lot on different systems
        $this->assertNotEmpty($config->getExecutable());
    }

    /**
     * Normalizes directory separators to what is native on the current platform.
     *
     * @param $path
     *
     * @return string
     */
    private function normalizePath($path)
    {
        return strtr($path, '/', DIRECTORY_SEPARATOR);
    }
}
