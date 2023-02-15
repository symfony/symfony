<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bridge\PhpUnit\Tests\DeprecationErrorHandler;

use PHPUnit\Framework\TestCase;
use Symfony\Bridge\PhpUnit\DeprecationErrorHandler\Configuration;
use Symfony\Bridge\PhpUnit\DeprecationErrorHandler\Deprecation;
use Symfony\Bridge\PhpUnit\DeprecationErrorHandler\DeprecationGroup;

class ConfigurationTest extends TestCase
{
    private $files;

    public function testItThrowsOnStringishValue()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('hi');
        Configuration::fromUrlEncodedString('hi');
    }

    public function testItThrowsOnUnknownConfigurationOption()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('min');
        Configuration::fromUrlEncodedString('min[total]=42');
    }

    public function testItThrowsOnUnknownThreshold()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('deep');
        Configuration::fromUrlEncodedString('max[deep]=42');
    }

    public function testItThrowsOnStringishThreshold()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('forty-two');
        Configuration::fromUrlEncodedString('max[total]=forty-two');
    }

    public function testItNoticesExceededTotalThreshold()
    {
        $configuration = Configuration::fromUrlEncodedString('max[total]=3');
        $this->assertTrue($configuration->tolerates($this->buildGroups([
            'unsilenced' => 1,
            'self' => 0,
            'legacy' => 1,
            'other' => 0,
            'direct' => 1,
            'indirect' => 1,
        ])));
        $this->assertFalse($configuration->tolerates($this->buildGroups([
            'unsilenced' => 1,
            'self' => 1,
            'legacy' => 1,
            'other' => 0,
            'direct' => 1,
            'indirect' => 1,
        ])));
    }

    public function testItNoticesExceededSelfThreshold()
    {
        $configuration = Configuration::fromUrlEncodedString('max[self]=1');
        $this->assertTrue($configuration->tolerates($this->buildGroups([
            'unsilenced' => 1234,
            'self' => 1,
            'legacy' => 23,
            'other' => 13,
            'direct' => 124,
            'indirect' => 3244,
        ])));
        $this->assertFalse($configuration->tolerates($this->buildGroups([
            'unsilenced' => 1234,
            'self' => 2,
            'legacy' => 23,
            'other' => 13,
            'direct' => 124,
            'indirect' => 3244,
        ])));
    }

    public function testItNoticesExceededDirectThreshold()
    {
        $configuration = Configuration::fromUrlEncodedString('max[direct]=1&max[self]=999999');
        $this->assertTrue($configuration->tolerates($this->buildGroups([
            'unsilenced' => 1234,
            'self' => 123,
            'legacy' => 23,
            'other' => 13,
            'direct' => 1,
            'indirect' => 3244,
        ])));
        $this->assertFalse($configuration->tolerates($this->buildGroups([
            'unsilenced' => 1234,
            'self' => 124,
            'legacy' => 23,
            'other' => 13,
            'direct' => 2,
            'indirect' => 3244,
        ])));
    }

    public function testItNoticesExceededIndirectThreshold()
    {
        $configuration = Configuration::fromUrlEncodedString('max[indirect]=1&max[direct]=999999&max[self]=999999');
        $this->assertTrue($configuration->tolerates($this->buildGroups([
            'unsilenced' => 1234,
            'self' => 123,
            'legacy' => 23,
            'other' => 13,
            'direct' => 1234,
            'indirect' => 1,
        ])));
        $this->assertFalse($configuration->tolerates($this->buildGroups([
            'unsilenced' => 1234,
            'self' => 124,
            'legacy' => 23,
            'other' => 13,
            'direct' => 2324,
            'indirect' => 2,
        ])));
    }

    public function testIndirectThresholdIsUsedAsADefaultForDirectAndSelfThreshold()
    {
        $configuration = Configuration::fromUrlEncodedString('max[indirect]=1');
        $this->assertTrue($configuration->tolerates($this->buildGroups([
            'unsilenced' => 0,
            'self' => 1,
            'legacy' => 0,
            'other' => 0,
            'direct' => 0,
            'indirect' => 0,
        ])));
        $this->assertFalse($configuration->tolerates($this->buildGroups([
            'unsilenced' => 0,
            'self' => 2,
            'legacy' => 0,
            'other' => 0,
            'direct' => 0,
            'indirect' => 0,
        ])));
        $this->assertTrue($configuration->tolerates($this->buildGroups([
            'unsilenced' => 0,
            'self' => 0,
            'legacy' => 0,
            'other' => 0,
            'direct' => 1,
            'indirect' => 0,
        ])));
        $this->assertFalse($configuration->tolerates($this->buildGroups([
            'unsilenced' => 0,
            'self' => 0,
            'legacy' => 0,
            'other' => 0,
            'direct' => 2,
            'indirect' => 0,
        ])));
    }

    public function testItCanTellWhetherToDisplayAStackTrace()
    {
        $configuration = Configuration::fromUrlEncodedString('');
        $this->assertFalse($configuration->shouldDisplayStackTrace('interesting'));

        $configuration = Configuration::fromRegex('/^interesting/');
        $this->assertFalse($configuration->shouldDisplayStackTrace('uninteresting'));
        $this->assertTrue($configuration->shouldDisplayStackTrace('interesting'));
    }

    public static function provideItCanBeDisabled(): array
    {
        return [
            ['disabled', false],
            ['disabled=1', false],
            ['disabled=0', true],
        ];
    }

    /**
     * @dataProvider provideItCanBeDisabled
     */
    public function testItCanBeDisabled(string $encodedString, bool $expectedEnabled)
    {
        $configuration = Configuration::fromUrlEncodedString($encodedString);
        $this->assertSame($expectedEnabled, $configuration->isEnabled());
    }

    public function testItCanBeShushed()
    {
        $configuration = Configuration::fromUrlEncodedString('verbose');
        $this->assertFalse($configuration->verboseOutput('unsilenced'));
        $this->assertFalse($configuration->verboseOutput('direct'));
        $this->assertFalse($configuration->verboseOutput('indirect'));
        $this->assertFalse($configuration->verboseOutput('self'));
        $this->assertFalse($configuration->verboseOutput('other'));
    }

    public function testItCanBePartiallyShushed()
    {
        $configuration = Configuration::fromUrlEncodedString('quiet[]=unsilenced&quiet[]=indirect&quiet[]=other');
        $this->assertFalse($configuration->verboseOutput('unsilenced'));
        $this->assertTrue($configuration->verboseOutput('direct'));
        $this->assertFalse($configuration->verboseOutput('indirect'));
        $this->assertTrue($configuration->verboseOutput('self'));
        $this->assertFalse($configuration->verboseOutput('other'));
    }

    public function testItThrowsOnUnknownVerbosityGroup()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('made-up');
        Configuration::fromUrlEncodedString('quiet[]=made-up');
    }

    public function testOutputIsNotVerboseInWeakMode()
    {
        $configuration = Configuration::inWeakMode();
        $this->assertFalse($configuration->verboseOutput('unsilenced'));
        $this->assertFalse($configuration->verboseOutput('direct'));
        $this->assertFalse($configuration->verboseOutput('indirect'));
        $this->assertFalse($configuration->verboseOutput('self'));
        $this->assertFalse($configuration->verboseOutput('other'));
    }

    /**
     * @dataProvider provideDataForToleratesForGroup
     */
    public function testToleratesForIndividualGroups(string $deprecationsHelper, array $deprecationsPerType, array $expected)
    {
        $configuration = Configuration::fromUrlEncodedString($deprecationsHelper);

        $groups = $this->buildGroups($deprecationsPerType);

        foreach ($expected as $groupName => $tolerates) {
            $this->assertSame($tolerates, $configuration->toleratesForGroup($groupName, $groups), sprintf('Deprecation type "%s" is %s', $groupName, $tolerates ? 'tolerated' : 'not tolerated'));
        }
    }

    public static function provideDataForToleratesForGroup() {

        yield 'total threshold not reached' => ['max[total]=1', [
            'unsilenced' => 0,
            'self' => 0,
            'legacy' => 1, // Legacy group is ignored in total threshold
            'other' => 0,
            'direct' => 1,
            'indirect' => 0,
        ], [
            'unsilenced' => true,
            'self' => true,
            'legacy' => true,
            'other' => true,
            'direct' => true,
            'indirect' => true,
        ]];

        yield 'total threshold reached' => ['max[total]=1', [
            'unsilenced' => 0,
            'self' => 0,
            'legacy' => 1,
            'other' => 0,
            'direct' => 1,
            'indirect' => 1,
        ], [
            'unsilenced' => false,
            'self' => false,
            'legacy' => false,
            'other' => false,
            'direct' => false,
            'indirect' => false,
        ]];

        yield 'direct threshold reached' => ['max[total]=99&max[direct]=0', [
            'unsilenced' => 0,
            'self' => 0,
            'legacy' => 1,
            'other' => 0,
            'direct' => 1,
            'indirect' => 1,
        ], [
            'unsilenced' => true,
            'self' => true,
            'legacy' => true,
            'other' => true,
            'direct' => false,
            'indirect' => true,
        ]];

        yield 'indirect & self threshold reached' => ['max[total]=99&max[direct]=0&max[self]=0', [
            'unsilenced' => 0,
            'self' => 1,
            'legacy' => 1,
            'other' => 1,
            'direct' => 1,
            'indirect' => 1,
        ], [
            'unsilenced' => true,
            'self' => false,
            'legacy' => true,
            'other' => true,
            'direct' => false,
            'indirect' => true,
        ]];

        yield 'indirect & self threshold not reached' => ['max[total]=99&max[direct]=2&max[self]=2', [
            'unsilenced' => 0,
            'self' => 1,
            'legacy' => 1,
            'other' => 1,
            'direct' => 1,
            'indirect' => 1,
        ], [
            'unsilenced' => true,
            'self' => true,
            'legacy' => true,
            'other' => true,
            'direct' => true,
            'indirect' => true,
        ]];
    }

    private function buildGroups($counts)
    {
        $groups = [];
        foreach ($counts as $name => $count) {
            $groups[$name] = new DeprecationGroup();
            $i = 0;
            while ($i++ < $count) {
                $groups[$name]->addNotice();
            }
        }

        return $groups;
    }

    public function testBaselineGenerationEmptyFile()
    {
        $filename = $this->createFile();
        $configuration = Configuration::fromUrlEncodedString('generateBaseline=true&baselineFile='.urlencode($filename));
        $this->assertTrue($configuration->isGeneratingBaseline());
        $trace = debug_backtrace();
        $this->assertTrue($configuration->isBaselineDeprecation(new Deprecation('Test message 1', $trace, '')));
        $this->assertTrue($configuration->isBaselineDeprecation(new Deprecation('Test message 2', $trace, '')));
        $this->assertTrue($configuration->isBaselineDeprecation(new Deprecation('Test message 1', $trace, '')));
        $configuration->writeBaseline();
        $this->assertEquals($filename, $configuration->getBaselineFile());
        $expected_baseline = [
            [
                'location' => 'Symfony\Bridge\PhpUnit\Tests\DeprecationErrorHandler\ConfigurationTest::runTest',
                'message' => 'Test message 1',
                'count' => 2,
            ],
            [
                'location' => 'Symfony\Bridge\PhpUnit\Tests\DeprecationErrorHandler\ConfigurationTest::runTest',
                'message' => 'Test message 2',
                'count' => 1,
            ],
        ];
        $this->assertEquals(json_encode($expected_baseline, \JSON_PRETTY_PRINT | \JSON_UNESCAPED_SLASHES), file_get_contents($filename));
    }

    public function testBaselineGenerationNoFile()
    {
        $filename = $this->createFile();
        $configuration = Configuration::fromUrlEncodedString('generateBaseline=true&baselineFile='.urlencode($filename));
        $this->assertTrue($configuration->isGeneratingBaseline());
        $trace = debug_backtrace();
        $this->assertTrue($configuration->isBaselineDeprecation(new Deprecation('Test message 1', $trace, '')));
        $this->assertTrue($configuration->isBaselineDeprecation(new Deprecation('Test message 2', $trace, '')));
        $this->assertTrue($configuration->isBaselineDeprecation(new Deprecation('Test message 2', $trace, '')));
        $this->assertTrue($configuration->isBaselineDeprecation(new Deprecation('Test message 1', $trace, '')));
        $configuration->writeBaseline();
        $this->assertEquals($filename, $configuration->getBaselineFile());
        $expected_baseline = [
            [
                'location' => 'Symfony\Bridge\PhpUnit\Tests\DeprecationErrorHandler\ConfigurationTest::runTest',
                'message' => 'Test message 1',
                'count' => 2,
            ],
            [
                'location' => 'Symfony\Bridge\PhpUnit\Tests\DeprecationErrorHandler\ConfigurationTest::runTest',
                'message' => 'Test message 2',
                'count' => 2,
            ],
        ];
        $this->assertEquals(json_encode($expected_baseline, \JSON_PRETTY_PRINT | \JSON_UNESCAPED_SLASHES), file_get_contents($filename));
    }

    public function testExistingBaseline()
    {
        $filename = $this->createFile();
        $baseline = [
            [
                'location' => 'Symfony\Bridge\PhpUnit\Tests\DeprecationErrorHandler\ConfigurationTest::runTest',
                'message' => 'Test message 1',
                'count' => 1,
            ],
            [
                'location' => 'Symfony\Bridge\PhpUnit\Tests\DeprecationErrorHandler\ConfigurationTest::runTest',
                'message' => 'Test message 2',
                'count' => 1,
            ],
        ];
        file_put_contents($filename, json_encode($baseline));

        $configuration = Configuration::fromUrlEncodedString('baselineFile='.urlencode($filename));
        $this->assertFalse($configuration->isGeneratingBaseline());
        $trace = debug_backtrace();
        $this->assertTrue($configuration->isBaselineDeprecation(new Deprecation('Test message 1', $trace, '')));
        $this->assertTrue($configuration->isBaselineDeprecation(new Deprecation('Test message 2', $trace, '')));
        $this->assertFalse($configuration->isBaselineDeprecation(new Deprecation('Test message 3', $trace, '')));
        $this->assertEquals($filename, $configuration->getBaselineFile());
    }

    public function testExistingBaselineAndGeneration()
    {
        $filename = $this->createFile();
        $baseline = [
            [
                'location' => 'Symfony\Bridge\PhpUnit\Tests\DeprecationErrorHandler\ConfigurationTest::runTest',
                'message' => 'Test message 1',
                'count' => 1,
            ],
            [
                'location' => 'Symfony\Bridge\PhpUnit\Tests\DeprecationErrorHandler\ConfigurationTest::runTest',
                'message' => 'Test message 2',
                'count' => 1,
            ],
        ];
        file_put_contents($filename, json_encode($baseline));
        $configuration = Configuration::fromUrlEncodedString('generateBaseline=true&baselineFile='.urlencode($filename));
        $this->assertTrue($configuration->isGeneratingBaseline());
        $trace = debug_backtrace();
        $this->assertTrue($configuration->isBaselineDeprecation(new Deprecation('Test message 2', $trace, '')));
        $this->assertTrue($configuration->isBaselineDeprecation(new Deprecation('Test message 3', $trace, '')));
        $configuration->writeBaseline();
        $this->assertEquals($filename, $configuration->getBaselineFile());
        $expected_baseline = [
            [
                'location' => 'Symfony\Bridge\PhpUnit\Tests\DeprecationErrorHandler\ConfigurationTest::runTest',
                'message' => 'Test message 2',
                'count' => 1,
            ],
            [
                'location' => 'Symfony\Bridge\PhpUnit\Tests\DeprecationErrorHandler\ConfigurationTest::runTest',
                'message' => 'Test message 3',
                'count' => 1,
            ],
        ];
        $this->assertEquals(json_encode($expected_baseline, \JSON_PRETTY_PRINT | \JSON_UNESCAPED_SLASHES), file_get_contents($filename));
    }

    public function testBaselineArgumentException()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('You cannot use the "generateBaseline" configuration option without providing a "baselineFile" configuration option.');
        Configuration::fromUrlEncodedString('generateBaseline=true');
    }

    public function testBaselineFileException()
    {
        $filename = $this->createFile();
        unlink($filename);
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage(sprintf('The baselineFile "%s" does not exist.', $filename));
        Configuration::fromUrlEncodedString('baselineFile='.urlencode($filename));
    }

    public function testBaselineFileWriteError()
    {
        $filename = $this->createFile();
        chmod($filename, 0444);
        $configuration = Configuration::fromUrlEncodedString('generateBaseline=true&baselineFile='.urlencode($filename));

        $this->expectException(\ErrorException::class);
        $this->expectExceptionMessageMatches('/[Ff]ailed to open stream: Permission denied/');

        set_error_handler(static function (int $errno, string $errstr, string $errfile = null, int $errline = null): bool {
            if ($errno & (E_WARNING | E_WARNING)) {
                throw new \ErrorException($errstr, 0, $errno, $errfile, $errline);
            }

            return false;
        });

        try {
            $configuration->writeBaseline();
        } finally {
            restore_error_handler();
        }
    }

    public function testExistingIgnoreFile()
    {
        $filename = $this->createFile();
        $ignorePatterns = [
            '/Test message .*/',
            '/^\d* occurrences/',
        ];
        file_put_contents($filename, implode("\n", $ignorePatterns));

        $configuration = Configuration::fromUrlEncodedString('ignoreFile='.urlencode($filename));
        $trace = debug_backtrace();
        $this->assertTrue($configuration->isIgnoredDeprecation(new Deprecation('Test message 1', $trace, '')));
        $this->assertTrue($configuration->isIgnoredDeprecation(new Deprecation('Test message 2', $trace, '')));
        $this->assertFalse($configuration->isIgnoredDeprecation(new Deprecation('Test mexxage 3', $trace, '')));
        $this->assertTrue($configuration->isIgnoredDeprecation(new Deprecation('1 occurrences', $trace, '')));
        $this->assertTrue($configuration->isIgnoredDeprecation(new Deprecation('1200 occurrences and more', $trace, '')));
        $this->assertFalse($configuration->isIgnoredDeprecation(new Deprecation('Many occurrences', $trace, '')));
    }

    public function testIgnoreFilePatternInvalid()
    {
        $filename = $this->createFile();
        $ignorePatterns = [
            '/Test message (.*/',
        ];
        file_put_contents($filename, implode("\n", $ignorePatterns));

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('missing closing parenthesis');
        $configuration = Configuration::fromUrlEncodedString('ignoreFile='.urlencode($filename));
    }

    public function testIgnoreFilePatternException()
    {
        $filename = $this->createFile();
        $ignorePatterns = [
            '/(?:\D+|<\d+>)*[!?]/',
        ];
        file_put_contents($filename, implode("\n", $ignorePatterns));

        $configuration = Configuration::fromUrlEncodedString('ignoreFile='.urlencode($filename));
        $trace = debug_backtrace();
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/[Bb]acktrack limit exhausted/');
        $configuration->isIgnoredDeprecation(new Deprecation('foobar foobar foobar', $trace, ''));
    }

    public function testIgnoreFileException()
    {
        $filename = $this->createFile();
        unlink($filename);
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage(sprintf('The ignoreFile "%s" does not exist.', $filename));
        Configuration::fromUrlEncodedString('ignoreFile='.urlencode($filename));
    }

    protected function setUp(): void
    {
        $this->files = [];
    }

    protected function tearDown(): void
    {
        foreach ($this->files as $file) {
            if (file_exists($file)) {
                @unlink($file);
            }
        }
    }

    private function createFile()
    {
        $filename = tempnam(sys_get_temp_dir(), 'sf-');
        $this->files[] = $filename;

        return $filename;
    }
}
