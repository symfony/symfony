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

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\AssetMapper\Tests\Fixtures\AssetMapperTestAppKernel;
use Symfony\Component\AssetMapper\Tests\Fixtures\InstantiationCountingDataCollector;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class AssetMapperDevServerSubscriberFunctionalTest extends WebTestCase
{
    public function testGettingAssetWorks()
    {
        $client = static::createClient();

        $client->request('GET', '/assets/file1-s0Rct6h.css');
        $response = $client->getResponse();
        $this->assertSame(200, $response->getStatusCode());
        $this->assertInstanceOf(BinaryFileResponse::class, $response);
        $this->assertSame(<<<EOF
            /* file1.css */
            body {}

            EOF,
            $response->getFile()->getContent()
        );
        $this->assertSame('"b3445cb7a86a0795a7af7f2004498aef"', $response->headers->get('ETag'));
        $this->assertSame('immutable, max-age=604800, public', $response->headers->get('Cache-Control'));
        $this->assertTrue($response->headers->has('X-Assets-Dev'));
    }

    public function testGettingAssetDoesNotBuildTheProfiler()
    {
        InstantiationCountingDataCollector::$instances = 0;
        $client = static::createClient(['environment' => 'profiler']);

        $client->request('GET', '/assets/file1-s0Rct6h.css');
        $this->assertSame(200, $client->getResponse()->getStatusCode());
        $this->assertFalse($client->getResponse()->headers->has('X-Debug-Token'));
        $this->assertSame(0, InstantiationCountingDataCollector::$instances);

        $client->request('GET', '/assets/unknown.css');
        $this->assertSame(404, $client->getResponse()->getStatusCode());
        $this->assertTrue($client->getResponse()->headers->has('X-Debug-Token'));
        $this->assertSame(1, InstantiationCountingDataCollector::$instances);
    }

    public function testGettingAssetWithNonAsciiFilenameWorks()
    {
        $client = static::createClient();

        $client->request('GET', '/assets/voilà-Y0RCLaa.css');
        $response = $client->getResponse();
        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame(<<<EOF
            /* voilà.css */
            body {}

            EOF,
            $client->getInternalResponse()->getContent()
        );
    }

    public function test404OnUnknownAsset()
    {
        $client = static::createClient();

        $client->request('GET', '/assets/unknown.css');
        $response = $client->getResponse();
        $this->assertSame(404, $response->getStatusCode());
        $this->assertFalse($response->headers->has('X-Assets-Dev'));
    }

    public function test404OnInvalidDigest()
    {
        $client = static::createClient();

        $client->request('GET', '/assets/file1-fakedigest.css');
        $response = $client->getResponse();
        $this->assertSame(404, $response->getStatusCode());
    }

    public function testPreDigestedAssetIsReturned()
    {
        $client = static::createClient();

        $client->request('GET', '/assets/already-abcdefVWXYZ0123456789.digested.css');
        $response = $client->getResponse();
        $this->assertSame(200, $response->getStatusCode());
        $this->assertInstanceOf(BinaryFileResponse::class, $response);
        $this->assertSame(<<<EOF
            /* already-abcdefVWXYZ0123456789.digested.css */
            body {}

            EOF,
            $response->getFile()->getContent()
        );
    }

    protected static function getKernelClass(): string
    {
        return AssetMapperTestAppKernel::class;
    }
}
