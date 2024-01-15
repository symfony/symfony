<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Serializer\Tests\Builder;

use PhpParser\ParserFactory;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Serializer\Builder\DefinitionExtractor;
use Symfony\Component\Serializer\Builder\NormalizerBuilder;

class NormalizerBuilderFixtureTest extends TestCase
{
    private static NormalizerBuilder $builder;
    private static DefinitionExtractor $definitionExtractor;
    private static string $outputDir;
    private static bool $compareOutput;

    public static function setUpBeforeClass(): void
    {
        self::$definitionExtractor = FixtureHelper::getDefinitionExtractor();
        self::$outputDir = \dirname(__DIR__).'/_output/SerializerBuilderFixtureTest';
        self::$builder = new NormalizerBuilder();

        // Only compare on nikic/php-parser: ^5.0
        self::$compareOutput = method_exists(ParserFactory::class, 'createForVersion');

        parent::setUpBeforeClass();
    }

    /**
     * If one does changes to the NormalizerBuilder, this test will probably fail.
     * Run `php Tests/Builder/generateUpdatedFixtures.php` to update the fixtures.
     *
     * This will help reviewers to see the effect of the changes in the NormalizerBuilder.
     *
     * @dataProvider fixtureClassGenerator
     */
    public function testBuildFixtures(string $inputClass, string $expectedOutputFile)
    {
        $def = self::$definitionExtractor->getDefinition($inputClass);
        $result = self::$builder->build($def, self::$outputDir);
        $result->loadClass();
        $this->assertTrue(class_exists($result->classNs));

        if (self::$compareOutput) {
            $this->assertFileEquals($expectedOutputFile, $result->filePath);
        }
    }

    public static function fixtureClassGenerator(): iterable
    {
        foreach (FixtureHelper::getFixturesAndResultFiles() as $class => $normalizerFile) {
            yield $class => [$class, $normalizerFile];
        }
    }
}
