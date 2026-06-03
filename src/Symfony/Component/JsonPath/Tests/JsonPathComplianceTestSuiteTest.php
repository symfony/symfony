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
    private const COMPLIANCE_TEST_SUITE_FILE = 'vendor/jsonpath-standard/jsonpath-compliance-test-suite/cts.json';

    #[DataProvider('complianceCaseProvider')]
    public function testComplianceTestCase(?string $selector, mixed $document, array $expectedResults, bool $invalidSelector)
    {
        if (null === $selector) {
            $this->markTestSkipped('The JsonPath compliance test suite is not available. Did you run "composer update"?');
        }

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
    public function testComplianceTestCaseWithResource(?string $selector, mixed $document, array $expectedResults, bool $invalidSelector)
    {
        if (null === $selector) {
            $this->markTestSkipped('The JsonPath compliance test suite is not available. Did you run "composer update"?');
        }

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
        $testSuiteFile = self::getComplianceTestSuiteFile();
        if (null === $testSuiteFile) {
            yield 'missing test suite dataset' => [null, null, [], false];

            return;
        }

        $data = json_decode(file_get_contents($testSuiteFile), false, flags: \JSON_THROW_ON_ERROR);

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
        $testSuiteFile = self::getComplianceTestSuiteFile();
        if (null === $testSuiteFile) {
            yield 'missing test suite dataset' => [null, null, [], false];

            return;
        }

        $data = json_decode(file_get_contents($testSuiteFile), false, flags: \JSON_THROW_ON_ERROR);

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
            return array_map(static fn ($val) => self::convertToArray($val), get_object_vars($value));
        }

        if (\is_array($value)) {
            return array_map([self::class, 'convertToArray'], $value);
        }

        return $value;
    }

    private static function getComplianceTestSuiteFile(): ?string
    {
        $monorepoPath = \dirname(__DIR__, 5).'/'.self::COMPLIANCE_TEST_SUITE_FILE;
        if (file_exists($monorepoPath)) {
            return $monorepoPath;
        }

        $standalonePath = \dirname(__DIR__).'/'.self::COMPLIANCE_TEST_SUITE_FILE;

        return file_exists($standalonePath) ? $standalonePath : null;
    }
}
