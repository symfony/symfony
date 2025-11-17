<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\JsonPath\Tests;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\JsonPath\Exception\JsonCrawlerException;
use Symfony\Component\JsonPath\JsonCrawler;

final class JsonPathComplianceTestSuiteTest extends TestCase
{
    #[DataProvider('complianceCaseProvider')]
    public function testComplianceTestCase(string $selector, mixed $document, array $expectedResults, bool $invalidSelector)
    {
        $jsonCrawler = new JsonCrawler(json_encode($document));

        if ($invalidSelector) {
            $this->expectException(JsonCrawlerException::class);
        }

        $result = $jsonCrawler->find($selector);

        if (!$invalidSelector) {
            $this->assertContains($result, $expectedResults);
        }
    }

    #[DataProvider('resourceComplianceCaseProvider')]
    public function testComplianceTestCaseWithResource(string $selector, mixed $document, array $expectedResults, bool $invalidSelector)
    {
        $json = json_encode($document);
        $resource = fopen('php://memory', 'r+');
        fwrite($resource, $json);
        rewind($resource);

        $jsonCrawler = new JsonCrawler($resource);

        if ($invalidSelector) {
            $this->expectException(JsonCrawlerException::class);
        }

        $result = $jsonCrawler->find($selector);

        if (!$invalidSelector) {
            $this->assertContains($result, $expectedResults);
        }

        fclose($resource);
    }

    public static function complianceCaseProvider(): iterable
    {
        $data = json_decode(file_get_contents(__DIR__.'/Fixtures/cts.json'), false, flags: \JSON_THROW_ON_ERROR);

        foreach ($data->tests as $test) {
            yield $test->name => [
                $test->selector,
                $test->document ?? [],
                isset($test->result) ? [self::convertToArray($test->result)] : (isset($test->results) ? array_map([self::class, 'convertToArray'], $test->results) : []),
                $test->invalid_selector ?? false,
            ];
        }
    }

    public static function resourceComplianceCaseProvider(): iterable
    {
        $data = json_decode(file_get_contents(__DIR__.'/Fixtures/cts.json'), false, flags: \JSON_THROW_ON_ERROR);

        foreach ($data->tests as $test) {
            // if there's no document, no resource can be created
            if (!isset($test->document)) {
                continue;
            }

            yield $test->name => [
                $test->selector,
                $test->document,
                isset($test->result) ? [self::convertToArray($test->result)] : (isset($test->results) ? array_map([self::class, 'convertToArray'], $test->results) : []),
                $test->invalid_selector ?? false,
            ];
        }
    }

    private static function convertToArray(mixed $value): mixed
    {
        if ($value instanceof \stdClass) {
            return array_map(function ($val) {
                return self::convertToArray($val);
            }, get_object_vars($value));
        }

        if (\is_array($value)) {
            return array_map([self::class, 'convertToArray'], $value);
        }

        return $value;
    }
}
