<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Asset\Tests\VersionStrategy;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Asset\VersionStrategy\RemoteJsonManifestVersionStrategy;
use Symfony\Component\HttpClient\Exception\JsonException;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

/**
 * @group legacy
 */
class RemoteJsonManifestVersionStrategyTest extends TestCase
{
    public function testGetVersion()
    {
        $strategy = $this->createStrategy('https://cdn.example.com/manifest-valid.json');

        $this->assertSame('main.123abc.js', $strategy->getVersion('main.js'));
    }

    public function testApplyVersion()
    {
        $strategy = $this->createStrategy('https://cdn.example.com/manifest-valid.json');

        $this->assertSame('css/styles.555def.css', $strategy->applyVersion('css/styles.css'));
    }

    public function testApplyVersionWhenKeyDoesNotExistInManifest()
    {
        $strategy = $this->createStrategy('https://cdn.example.com/manifest-valid.json');

        $this->assertSame('css/other.css', $strategy->applyVersion('css/other.css'));
    }

    public function testMissingManifestFileThrowsException()
    {
        $this->expectException('RuntimeException');
        $this->expectExceptionMessage('HTTP 404 returned for "https://cdn.example.com/non-existent-file.json"');
        $strategy = $this->createStrategy('https://cdn.example.com/non-existent-file.json');
        $strategy->getVersion('main.js');
    }

    public function testManifestFileWithBadJSONThrowsException()
    {
        $this->expectException(JsonException::class);
        $this->expectExceptionMessage('Syntax error');
        $strategy = $this->createStrategy('https://cdn.example.com/manifest-invalid.json');
        $strategy->getVersion('main.js');
    }

    private function createStrategy($manifestUrl)
    {
        $httpClient = new MockHttpClient(function ($method, $url, $options) {
            $filename = __DIR__.'/../fixtures/'.basename($url);

            if (file_exists($filename)) {
                return new MockResponse(file_get_contents($filename), ['http_headers' => ['content-type' => 'application/json']]);
            }

            return new MockResponse('{}', ['http_code' => 404]);
        });

        return new RemoteJsonManifestVersionStrategy($manifestUrl, $httpClient);
    }
}
