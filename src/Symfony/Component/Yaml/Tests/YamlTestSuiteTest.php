<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Yaml\Tests;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Yaml\Exception\ParseException;
use Symfony\Component\Yaml\Yaml;

class YamlTestSuiteTest extends TestCase
{
    private const SKIP = [
        // Test cases that are known to fail or are not supported
        '26DV',
        '27NA',
        '2AUY',
        '2G84-00',
        '2G84-01',
        '2JQS',
        '2LFX',
        '2SXE',
        '2XXW',
        '33X3',
        '35KP',
        '36F6',
        '4ABK',
        '4FJ6',
        '4JVG',
        '4RWC',
        '565N',
        '57H4',
        '5LLU',
        '5TRB',
        '5TYM',
        '5U3A',
        '5WE3',
        '6BFJ',
        '6CA3',
        '6CK3',
        '6JWB',
        '6LVF',
        '6M2F',
        '6VJK',
        '6WLZ',
        '6XDY',
        '6ZKB',
        '735Y',
        '74H7',
        '7FWL',
        '7T8X',
        '7W2P',
        '7Z25',
        '87E4',
        '8G76',
        '93JH',
        '98YD',
        '9BXH',
        '9C9N',
        '9DXL',
        '9JBA',
        '9KAX',
        '9MAG',
        '9MMA',
        '9MMW',
        '9MQT-01',
        '9WXW',
        '9YRD',
        'A2M4',
        'AVM7',
        'B63P',
        'BEC7',
        'BF9H',
        'BS4K',
        'BU8L',
        'C4HZ',
        'CC74',
        'CFD4',
        'CML9',
        'CN3R',
        'CTN5',
        'CUP7',
        'CVW2',
        'CXX2',
        'DFF7',
        'DK4H',
        'DK95-00',
        'DK95-01',
        'DK95-03',
        'DK95-04',
        'DK95-07',
        'DWX9',
        'E76Z',
        'EB22',
        'EHF6',
        'F2C7',
        'FH7J',
        'FRK4',
        'G5U8',
        'G992',
        'GH63',
        'H7TQ',
        'HMQ5',
        'HS5T',
        'JHB9',
        'JTV5',
        'K527',
        'K54U',
        'KK5P',
        'L94M',
        'LE5A',
        'LQZ7',
        'LX3P',
        'M29M',
        'M2N8-00',
        'M5C3',
        'M5DY',
        'M9B4',
        'MJS9',
        'MUS6-00',
        'MUS6-02',
        'MUS6-03',
        'MUS6-04',
        'MUS6-05',
        'MUS6-06',
        'MYW6',
        'N782',
        'NKF9',
        'P76L',
        'P94K',
        'PUW8',
        'PW8X',
        'Q5MG',
        'Q9WF',
        'QB6E',
        'RR7F',
        'RXY3',
        'RZP5',
        'RZT7',
        'S3PD',
        'S4T7',
        'S98Z',
        'S9E8',
        'SKE5',
        'SR86',
        'SU5Z',
        'SU74',
        'SY6V',
        'T26H',
        'T833',
        'TS54',
        'U3C3',
        'U3XV',
        'U9NS',
        'UKK6-02',
        'UT92',
        'V9D5',
        'W4TN',
        'W9L4',
        'WZ62',
        'X38W',
        'X8DW',
        'XW4D',
        'Y79Y-003',
        'Y79Y-004',
        'Y79Y-005',
        'Y79Y-006',
        'Y79Y-008',
        'Y79Y-009',
        'YJV2',
        'Z9M4',
        'ZWK4',
        // Test cases that have different parsing results than using the in.json as expected
        '2G84-02',
        '2G84-03',
        '3R3P',
        '3RLN-02',
        '3RLN-05',
        '4Q9F',
        '4ZYM',
        '58MP',
        '5GBF',
        '5T43',
        '6FWR',
        '6JQW',
        '6PBE',
        '6WPF',
        '7A4E',
        '7BMT',
        '82AN',
        '8KB6',
        '96L6',
        '9MQT-00',
        '9TFX',
        'B3HG',
        'CT4Q',
        'DE56-01',
        'DE56-03',
        'DE56-04',
        'DE56-05',
        'DK3J',
        'DK95-02',
        'DK95-08',
        'F6MC',
        'FP8R',
        'HWV9',
        'JEF9-00',
        'JEF9-01',
        'JEF9-02',
        'K858',
        'KSS4',
        'L24T-01',
        'L383',
        'L9U5',
        'M2N8-01',
        'M7A3',
        'NAT4',
        'NHX8',
        'NJ66',
        'NP9H',
        'P2AD',
        'PRH3',
        'Q8AD',
        'QT73',
        'R4YG',
        'RTP8',
        'SBG9',
        'SM9W-01',
        'T4YY',
        'T5N4',
        'TL85',
        'UGM3',
        'UKK6-00',
        'Z67P',
        'ZH7C',
    ];

    #[DataProvider('yamlTestSuiteProvider')]
    public function testYamlTestSuite(string $testName, string $shortcode, string $file, bool $isErrorExpected, mixed $expected)
    {
        if ('' === $file) {
            $this->markTestSkipped(\sprintf('The YAML test suite is not available: %s', $testName));
        }

        if (\in_array($shortcode, self::SKIP, true)) {
            $this->markTestSkipped(\sprintf('Test case "%s" (%s) is skipped.', $testName, $shortcode));
        }

        if ($isErrorExpected) {
            $this->expectException(ParseException::class);
        }

        $data = Yaml::parseFile($file);

        if (!$isErrorExpected) {
            $this->assertEquals($expected, $data, \sprintf('Test case "%s" (%s) failed.', $testName, $shortcode));
        }
    }

    public static function yamlTestSuiteProvider(): \Generator
    {
        $dataDir = self::getTestSuitePath();

        if (null === $dataDir) {
            yield 'yaml-test-suite-missing' => ['YAML Test Suite Missing', '', '', false, null];

            return;
        }

        $shortcodeDirs = glob($dataDir.'/*', \GLOB_ONLYDIR);

        if (!$shortcodeDirs) {
            yield 'yaml-test-suite-empty' => ['YAML Test Suite Empty', '', '', false, null];

            return;
        }

        foreach ($shortcodeDirs as $shortcodeDir) {
            $shortcode = basename($shortcodeDir);

            if (\in_array($shortcode, ['name', 'tags'], true)) {
                continue;
            }

            if (file_exists($shortcodeDir.'/in.yaml')) {
                yield $shortcode => self::createTestItem($shortcode, $shortcodeDir);
                continue;
            }

            foreach (glob($shortcodeDir.'/*', \GLOB_ONLYDIR) as $subDir) {
                if (file_exists($subDir.'/in.yaml')) {
                    yield $shortcode.'-'.basename($subDir) => self::createTestItem($shortcode.'-'.basename($subDir), $subDir);
                }
            }
        }
    }

    private static function createTestItem(string $shortcode, string $dir): array
    {
        $isErrorExpected = file_exists($dir.'/error');
        $json = file_exists($dir.'/in.json') ? json_decode(file_get_contents($dir.'/in.json'), true) : null;
        $testName = is_file($dir.'/===') ? trim(file_get_contents($dir.'/===')) : $shortcode;

        return [$testName, $shortcode, $dir.'/in.yaml', $isErrorExpected, $json];
    }

    private static function getTestSuitePath(): ?string
    {
        $testSuiteDir = '/vendor/yaml/yaml-test-suite';

        $monorepoPath = \dirname(__DIR__, 5).$testSuiteDir;
        if (file_exists($monorepoPath)) {
            return $monorepoPath;
        }

        $standalonePath = \dirname(__DIR__).$testSuiteDir;

        return file_exists($standalonePath) ? $standalonePath : null;
    }
}
