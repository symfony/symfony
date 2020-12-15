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
use Symfony\Component\Asset\VersionStrategy\JsonManifestVersionStrategy;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

class JsonManifestVersionStrategyTest extends TestCase
{
    /**
     * @dataProvider ProvideValidStrategies
     */
    public function testGetVersion(JsonManifestVersionStrategy $strategy)
    {
        $this->assertSame('main.123abc.js', $strategy->getVersion('main.js'));
    }

    /**
     * @dataProvider ProvideValidStrategies
     */
    public function testApplyVersion(JsonManifestVersionStrategy $strategy)
    {
        $this->assertSame('css/styles.555def.css', $strategy->applyVersion('css/styles.css'));
    }

    /**
     * @dataProvider ProvideValidStrategies
     */
    public function testApplyVersionWhenKeyDoesNotExistInManifest(JsonManifestVersionStrategy $strategy)
    {
        $this->assertSame('css/other.css', $strategy->applyVersion('css/other.css'));
    }

    /**
     * @dataProvider ProvideMissingStrategies
     */
    public function testMissingManifestFileThrowsException(JsonManifestVersionStrategy $strategy)
    {
        $this->expectException('RuntimeException');
        $strategy->getVersion('main.js');
    }

    /**
     * @dataProvider ProvideInvalidStrategies
     */
    public function testManifestFileWithBadJSONThrowsException(JsonManifestVersionStrategy $strategy)
    {
        $this->expectException('RuntimeException');
        $this->expectExceptionMessage('Error parsing JSON');
        $strategy->getVersion('main.js');
    }

    public function provideValidStrategies()
    {
        yield from $this->provideStrategies('manifest-valid.json');
    }

    public function provideInvalidStrategies()
    {
        yield from $this->provideStrategies('manifest-invalid.json');
    }

    public function provideMissingStrategies()
    {
        yield from $this->provideStrategies('non-existent-file.json');
    }

    public function provideStrategies(string $manifestPath)
    {
        $httpClient = new MockHttpClient(function ($method, $url, $options) {
            $filename = __DIR__.'/../fixtures/'.basename($url);

            if (file_exists($filename)) {
                return new MockResponse(file_get_contents($filename), ['http_headers' => ['content-type' => 'application/json']]);
            }

            return new MockResponse('{}', ['http_code' => 404]);
        });

        yield [new JsonManifestVersionStrategy('https://cdn.example.com/'.$manifestPath, $httpClient)];

        yield [new JsonManifestVersionStrategy(__DIR__.'/../fixtures/'.$manifestPath)];
    }
}
