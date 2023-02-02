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
use Symfony\Component\Asset\Exception\AssetNotFoundException;
use Symfony\Component\Asset\Exception\RuntimeException;
use Symfony\Component\Asset\VersionStrategy\JsonManifestVersionStrategy;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

class JsonManifestVersionStrategyTest extends TestCase
{
    /**
     * @dataProvider provideValidStrategies
     */
    public function testGetVersion(JsonManifestVersionStrategy $strategy)
    {
        $this->assertSame('main.123abc.js', $strategy->getVersion('main.js'));
    }

    /**
     * @dataProvider provideValidStrategies
     */
    public function testApplyVersion(JsonManifestVersionStrategy $strategy)
    {
        $this->assertSame('css/styles.555def.css', $strategy->applyVersion('css/styles.css'));
    }

    /**
     * @dataProvider provideValidStrategies
     */
    public function testApplyVersionWhenKeyDoesNotExistInManifest(JsonManifestVersionStrategy $strategy)
    {
        $this->assertSame('css/other.css', $strategy->applyVersion('css/other.css'));
    }

    /**
     * @dataProvider provideStrictStrategies
     */
    public function testStrictExceptionWhenKeyDoesNotExistInManifest(JsonManifestVersionStrategy $strategy, $path, $message)
    {
        $this->expectException(AssetNotFoundException::class);
        $this->expectExceptionMessageMatches($message);

        $strategy->getVersion($path);
    }

    /**
     * @dataProvider provideMissingStrategies
     */
    public function testMissingManifestFileThrowsException(JsonManifestVersionStrategy $strategy)
    {
        $this->expectException(RuntimeException::class);
        $strategy->getVersion('main.js');
    }

    /**
     * @dataProvider provideInvalidStrategies
     */
    public function testManifestFileWithBadJSONThrowsException(JsonManifestVersionStrategy $strategy)
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Error parsing JSON');
        $strategy->getVersion('main.js');
    }

    public function testRemoteManifestFileWithoutHttpClient()
    {
        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage(sprintf('The "%s" class needs an HTTP client to use a remote manifest. Try running "composer require symfony/http-client".', JsonManifestVersionStrategy::class));

        new JsonManifestVersionStrategy('https://cdn.example.com/manifest.json');
    }

    public static function provideValidStrategies(): \Generator
    {
        yield from static::provideStrategies('manifest-valid.json');
    }

    public static function provideInvalidStrategies(): \Generator
    {
        yield from static::provideStrategies('manifest-invalid.json');
    }

    public static function provideMissingStrategies(): \Generator
    {
        yield from static::provideStrategies('non-existent-file.json');
    }

    public static function provideStrategies(string $manifestPath): \Generator
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

    public static function provideStrictStrategies(): \Generator
    {
        $strategy = new JsonManifestVersionStrategy(__DIR__.'/../fixtures/manifest-valid.json', null, true);

        yield [
            $strategy,
            'css/styles.555def.css',
            '~Asset "css/styles.555def.css" not found in manifest "(.*)/manifest-valid.json"\. Did you mean one of these\? "css/styles.css", "css/style.css".~',
        ];

        yield [
            $strategy,
            'img/avatar.png',
            '~Asset "img/avatar.png" not found in manifest "(.*)/manifest-valid.json"\.~',
        ];
    }
}
