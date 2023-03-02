<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Translation\Tests\Extractor;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Translation\Extractor\PhpExtractor;
use Symfony\Component\Translation\MessageCatalogue;

/**
 * @group legacy
 */
class PhpExtractorTest extends TestCase
{
    /**
     * @dataProvider resourcesProvider
     *
     * @param array|string $resource
     */
    public function testExtraction($resource)
    {
        // Arrange
        $extractor = new PhpExtractor();
        $extractor->setPrefix('prefix');
        $catalogue = new MessageCatalogue('en');

        // Act
        $extractor->extract($resource, $catalogue);

        $expectedHeredoc = <<<EOF
heredoc key with whitespace and escaped \$\n sequences
EOF;
        $expectedNowdoc = <<<'EOF'
nowdoc key with whitespace and nonescaped \$\n sequences
EOF;
        // Assert
        $expectedCatalogue = [
            'messages' => [
                'translatable single-quoted key' => 'prefixtranslatable single-quoted key',
                'translatable double-quoted key' => 'prefixtranslatable double-quoted key',
                'translatable heredoc key' => 'prefixtranslatable heredoc key',
                'translatable nowdoc key' => 'prefixtranslatable nowdoc key',
                "translatable double-quoted key with whitespace and escaped \$\n\" sequences" => "prefixtranslatable double-quoted key with whitespace and escaped \$\n\" sequences",
                'translatable single-quoted key with whitespace and nonescaped \$\n\' sequences' => 'prefixtranslatable single-quoted key with whitespace and nonescaped \$\n\' sequences',
                'translatable single-quoted key with "quote mark at the end"' => 'prefixtranslatable single-quoted key with "quote mark at the end"',
                'translatable '.$expectedHeredoc => 'prefixtranslatable '.$expectedHeredoc,
                'translatable '.$expectedNowdoc => 'prefixtranslatable '.$expectedNowdoc,
                'translatable concatenated message with heredoc and nowdoc' => 'prefixtranslatable concatenated message with heredoc and nowdoc',
                'translatable default domain' => 'prefixtranslatable default domain',
                'translatable-fqn single-quoted key' => 'prefixtranslatable-fqn single-quoted key',
                'translatable-fqn double-quoted key' => 'prefixtranslatable-fqn double-quoted key',
                'translatable-fqn heredoc key' => 'prefixtranslatable-fqn heredoc key',
                'translatable-fqn nowdoc key' => 'prefixtranslatable-fqn nowdoc key',
                "translatable-fqn double-quoted key with whitespace and escaped \$\n\" sequences" => "prefixtranslatable-fqn double-quoted key with whitespace and escaped \$\n\" sequences",
                'translatable-fqn single-quoted key with whitespace and nonescaped \$\n\' sequences' => 'prefixtranslatable-fqn single-quoted key with whitespace and nonescaped \$\n\' sequences',
                'translatable-fqn single-quoted key with "quote mark at the end"' => 'prefixtranslatable-fqn single-quoted key with "quote mark at the end"',
                'translatable-fqn '.$expectedHeredoc => 'prefixtranslatable-fqn '.$expectedHeredoc,
                'translatable-fqn '.$expectedNowdoc => 'prefixtranslatable-fqn '.$expectedNowdoc,
                'translatable-fqn concatenated message with heredoc and nowdoc' => 'prefixtranslatable-fqn concatenated message with heredoc and nowdoc',
                'translatable-fqn default domain' => 'prefixtranslatable-fqn default domain',
                'translatable-short single-quoted key' => 'prefixtranslatable-short single-quoted key',
                'translatable-short double-quoted key' => 'prefixtranslatable-short double-quoted key',
                'translatable-short heredoc key' => 'prefixtranslatable-short heredoc key',
                'translatable-short nowdoc key' => 'prefixtranslatable-short nowdoc key',
                "translatable-short double-quoted key with whitespace and escaped \$\n\" sequences" => "prefixtranslatable-short double-quoted key with whitespace and escaped \$\n\" sequences",
                'translatable-short single-quoted key with whitespace and nonescaped \$\n\' sequences' => 'prefixtranslatable-short single-quoted key with whitespace and nonescaped \$\n\' sequences',
                'translatable-short single-quoted key with "quote mark at the end"' => 'prefixtranslatable-short single-quoted key with "quote mark at the end"',
                'translatable-short '.$expectedHeredoc => 'prefixtranslatable-short '.$expectedHeredoc,
                'translatable-short '.$expectedNowdoc => 'prefixtranslatable-short '.$expectedNowdoc,
                'translatable-short concatenated message with heredoc and nowdoc' => 'prefixtranslatable-short concatenated message with heredoc and nowdoc',
                'translatable-short default domain' => 'prefixtranslatable-short default domain',
                'single-quoted key' => 'prefixsingle-quoted key',
                'double-quoted key' => 'prefixdouble-quoted key',
                'heredoc key' => 'prefixheredoc key',
                'nowdoc key' => 'prefixnowdoc key',
                "double-quoted key with whitespace and escaped \$\n\" sequences" => "prefixdouble-quoted key with whitespace and escaped \$\n\" sequences",
                'single-quoted key with whitespace and nonescaped \$\n\' sequences' => 'prefixsingle-quoted key with whitespace and nonescaped \$\n\' sequences',
                'single-quoted key with "quote mark at the end"' => 'prefixsingle-quoted key with "quote mark at the end"',
                $expectedHeredoc => 'prefix'.$expectedHeredoc,
                $expectedNowdoc => 'prefix'.$expectedNowdoc,
                'concatenated message with heredoc and nowdoc' => 'prefixconcatenated message with heredoc and nowdoc',
                'default domain' => 'prefixdefault domain',
            ],
            'not_messages' => [
                'translatable other-domain-test-no-params-short-array' => 'prefixtranslatable other-domain-test-no-params-short-array',
                'translatable other-domain-test-no-params-long-array' => 'prefixtranslatable other-domain-test-no-params-long-array',
                'translatable other-domain-test-params-short-array' => 'prefixtranslatable other-domain-test-params-short-array',
                'translatable other-domain-test-params-long-array' => 'prefixtranslatable other-domain-test-params-long-array',
                'translatable typecast' => 'prefixtranslatable typecast',
                'translatable-fqn other-domain-test-no-params-short-array' => 'prefixtranslatable-fqn other-domain-test-no-params-short-array',
                'translatable-fqn other-domain-test-no-params-long-array' => 'prefixtranslatable-fqn other-domain-test-no-params-long-array',
                'translatable-fqn other-domain-test-params-short-array' => 'prefixtranslatable-fqn other-domain-test-params-short-array',
                'translatable-fqn other-domain-test-params-long-array' => 'prefixtranslatable-fqn other-domain-test-params-long-array',
                'translatable-fqn typecast' => 'prefixtranslatable-fqn typecast',
                'translatable-short other-domain-test-no-params-short-array' => 'prefixtranslatable-short other-domain-test-no-params-short-array',
                'translatable-short other-domain-test-no-params-long-array' => 'prefixtranslatable-short other-domain-test-no-params-long-array',
                'translatable-short other-domain-test-params-short-array' => 'prefixtranslatable-short other-domain-test-params-short-array',
                'translatable-short other-domain-test-params-long-array' => 'prefixtranslatable-short other-domain-test-params-long-array',
                'translatable-short typecast' => 'prefixtranslatable-short typecast',
                'other-domain-test-no-params-short-array' => 'prefixother-domain-test-no-params-short-array',
                'other-domain-test-no-params-long-array' => 'prefixother-domain-test-no-params-long-array',
                'other-domain-test-params-short-array' => 'prefixother-domain-test-params-short-array',
                'other-domain-test-params-long-array' => 'prefixother-domain-test-params-long-array',
                'typecast' => 'prefixtypecast',
            ],
        ];
        $actualCatalogue = $catalogue->all();

        $this->assertEquals($expectedCatalogue, $actualCatalogue);

        $filename = str_replace(\DIRECTORY_SEPARATOR, '/', __DIR__).'/../fixtures/extractor/translatable.html.php';
        $this->assertEquals(['sources' => [$filename.':2']], $catalogue->getMetadata('translatable single-quoted key'));
        $this->assertEquals(['sources' => [$filename.':37']], $catalogue->getMetadata('translatable other-domain-test-no-params-short-array', 'not_messages'));

        $filename = str_replace(\DIRECTORY_SEPARATOR, '/', __DIR__).'/../fixtures/extractor/translatable-fqn.html.php';
        $this->assertEquals(['sources' => [$filename.':2']], $catalogue->getMetadata('translatable-fqn single-quoted key'));
        $this->assertEquals(['sources' => [$filename.':37']], $catalogue->getMetadata('translatable-fqn other-domain-test-no-params-short-array', 'not_messages'));

        $filename = str_replace(\DIRECTORY_SEPARATOR, '/', __DIR__).'/../fixtures/extractor/translatable-short.html.php';
        $this->assertEquals(['sources' => [$filename.':2']], $catalogue->getMetadata('translatable-short single-quoted key'));
        $this->assertEquals(['sources' => [$filename.':37']], $catalogue->getMetadata('translatable-short other-domain-test-no-params-short-array', 'not_messages'));

        $filename = str_replace(\DIRECTORY_SEPARATOR, '/', __DIR__).'/../fixtures/extractor/translation.html.php';
        $this->assertEquals(['sources' => [$filename.':2']], $catalogue->getMetadata('single-quoted key'));
        $this->assertEquals(['sources' => [$filename.':37']], $catalogue->getMetadata('other-domain-test-no-params-short-array', 'not_messages'));
    }

    public function testExtractionFromIndentedHeredocNowdoc()
    {
        $catalogue = new MessageCatalogue('en');

        $extractor = new PhpExtractor();
        $extractor->setPrefix('prefix');
        $extractor->extract(__DIR__.'/../fixtures/extractor-7.3/translation.html.php', $catalogue);

        $expectedCatalogue = [
            'messages' => [
                "heredoc\nindented\n  further" => "prefixheredoc\nindented\n  further",
                "nowdoc\nindented\n  further" => "prefixnowdoc\nindented\n  further",
            ],
        ];

        $this->assertEquals($expectedCatalogue, $catalogue->all());
    }

    public static function resourcesProvider()
    {
        $directory = __DIR__.'/../fixtures/extractor/';
        $phpFiles = [];
        $splFiles = [];
        foreach (new \DirectoryIterator($directory) as $fileInfo) {
            if ($fileInfo->isDot()) {
                continue;
            }
            if (\in_array($fileInfo->getBasename(), ['translatable.html.php', 'translatable-fqn.html.php', 'translatable-short.html.php', 'translation.html.php'], true)) {
                $phpFiles[] = $fileInfo->getPathname();
            }
            $splFiles[] = $fileInfo->getFileInfo();
        }

        return [
            [$directory],
            [$phpFiles],
            [glob($directory.'*')],
            [$splFiles],
            [new \ArrayObject(glob($directory.'*'))],
            [new \ArrayObject($splFiles)],
        ];
    }
}
