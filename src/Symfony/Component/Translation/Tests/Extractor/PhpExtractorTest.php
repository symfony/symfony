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
                // translatable.html.php
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
                'translatable test-no-params-short-array' => 'prefixtranslatable test-no-params-short-array',
                'translatable test-no-params-long-array' => 'prefixtranslatable test-no-params-long-array',
                'translatable test-params-short-array' => 'prefixtranslatable test-params-short-array',
                'translatable test-params-long-array' => 'prefixtranslatable test-params-long-array',
                'translatable test-multiple-params-short-array' => 'prefixtranslatable test-multiple-params-short-array',
                'translatable test-multiple-params-long-array' => 'prefixtranslatable test-multiple-params-long-array',
                'translatable test-params-trailing-comma-short-array' => 'prefixtranslatable test-params-trailing-comma-short-array',
                'translatable test-params-trailing-comma-long-array' => 'prefixtranslatable test-params-trailing-comma-long-array',
                'translatable typecast-short-array' => 'prefixtranslatable typecast-short-array',
                'translatable typecast-long-array' => 'prefixtranslatable typecast-long-array',
                'translatable default-domain-short-array' => 'prefixtranslatable default-domain-short-array',
                'translatable default-domain-long-array' => 'prefixtranslatable default-domain-long-array',

                // translatable-fqn.html.php
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
                'translatable-fqn test-no-params-short-array' => 'prefixtranslatable-fqn test-no-params-short-array',
                'translatable-fqn test-no-params-long-array' => 'prefixtranslatable-fqn test-no-params-long-array',
                'translatable-fqn test-params-short-array' => 'prefixtranslatable-fqn test-params-short-array',
                'translatable-fqn test-params-long-array' => 'prefixtranslatable-fqn test-params-long-array',
                'translatable-fqn test-multiple-params-short-array' => 'prefixtranslatable-fqn test-multiple-params-short-array',
                'translatable-fqn test-multiple-params-long-array' => 'prefixtranslatable-fqn test-multiple-params-long-array',
                'translatable-fqn test-params-trailing-comma-short-array' => 'prefixtranslatable-fqn test-params-trailing-comma-short-array',
                'translatable-fqn test-params-trailing-comma-long-array' => 'prefixtranslatable-fqn test-params-trailing-comma-long-array',
                'translatable-fqn typecast-short-array' => 'prefixtranslatable-fqn typecast-short-array',
                'translatable-fqn typecast-long-array' => 'prefixtranslatable-fqn typecast-long-array',
                'translatable-fqn default-domain-short-array' => 'prefixtranslatable-fqn default-domain-short-array',
                'translatable-fqn default-domain-long-array' => 'prefixtranslatable-fqn default-domain-long-array',

                // translatable-short.html.php
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
                'translatable-short test-no-params-short-array' => 'prefixtranslatable-short test-no-params-short-array',
                'translatable-short test-no-params-long-array' => 'prefixtranslatable-short test-no-params-long-array',
                'translatable-short test-params-short-array' => 'prefixtranslatable-short test-params-short-array',
                'translatable-short test-params-long-array' => 'prefixtranslatable-short test-params-long-array',
                'translatable-short test-multiple-params-short-array' => 'prefixtranslatable-short test-multiple-params-short-array',
                'translatable-short test-multiple-params-long-array' => 'prefixtranslatable-short test-multiple-params-long-array',
                'translatable-short test-params-trailing-comma-short-array' => 'prefixtranslatable-short test-params-trailing-comma-short-array',
                'translatable-short test-params-trailing-comma-long-array' => 'prefixtranslatable-short test-params-trailing-comma-long-array',
                'translatable-short typecast-short-array' => 'prefixtranslatable-short typecast-short-array',
                'translatable-short typecast-long-array' => 'prefixtranslatable-short typecast-long-array',
                'translatable-short default-domain-short-array' => 'prefixtranslatable-short default-domain-short-array',
                'translatable-short default-domain-long-array' => 'prefixtranslatable-short default-domain-long-array',

                // translation.html.php
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
                'test-no-params-short-array' => 'prefixtest-no-params-short-array',
                'test-no-params-long-array' => 'prefixtest-no-params-long-array',
                'test-params-short-array' => 'prefixtest-params-short-array',
                'test-params-long-array' => 'prefixtest-params-long-array',
                'test-multiple-params-short-array' => 'prefixtest-multiple-params-short-array',
                'test-multiple-params-long-array' => 'prefixtest-multiple-params-long-array',
                'test-params-trailing-comma-short-array' => 'prefixtest-params-trailing-comma-short-array',
                'test-params-trailing-comma-long-array' => 'prefixtest-params-trailing-comma-long-array',
                'typecast-short-array' => 'prefixtypecast-short-array',
                'typecast-long-array' => 'prefixtypecast-long-array',
                'default-domain-short-array' => 'prefixdefault-domain-short-array',
                'default-domain-long-array' => 'prefixdefault-domain-long-array',
            ],
            'not_messages' => [
                // translatable.html.php
                'translatable other-domain-test-no-params-short-array' => 'prefixtranslatable other-domain-test-no-params-short-array',
                'translatable other-domain-test-no-params-long-array' => 'prefixtranslatable other-domain-test-no-params-long-array',
                'translatable other-domain-test-params-short-array' => 'prefixtranslatable other-domain-test-params-short-array',
                'translatable other-domain-test-params-long-array' => 'prefixtranslatable other-domain-test-params-long-array',
                'translatable other-domain-typecast-short-array' => 'prefixtranslatable other-domain-typecast-short-array',
                'translatable other-domain-typecast-long-array' => 'prefixtranslatable other-domain-typecast-long-array',

                // translatable-fqn.html.php
                'translatable-fqn other-domain-test-no-params-short-array' => 'prefixtranslatable-fqn other-domain-test-no-params-short-array',
                'translatable-fqn other-domain-test-no-params-long-array' => 'prefixtranslatable-fqn other-domain-test-no-params-long-array',
                'translatable-fqn other-domain-test-params-short-array' => 'prefixtranslatable-fqn other-domain-test-params-short-array',
                'translatable-fqn other-domain-test-params-long-array' => 'prefixtranslatable-fqn other-domain-test-params-long-array',
                'translatable-fqn other-domain-typecast-short-array' => 'prefixtranslatable-fqn other-domain-typecast-short-array',
                'translatable-fqn other-domain-typecast-long-array' => 'prefixtranslatable-fqn other-domain-typecast-long-array',

                // translatable-short.html.php
                'translatable-short other-domain-test-no-params-short-array' => 'prefixtranslatable-short other-domain-test-no-params-short-array',
                'translatable-short other-domain-test-no-params-long-array' => 'prefixtranslatable-short other-domain-test-no-params-long-array',
                'translatable-short other-domain-test-params-short-array' => 'prefixtranslatable-short other-domain-test-params-short-array',
                'translatable-short other-domain-test-params-long-array' => 'prefixtranslatable-short other-domain-test-params-long-array',
                'translatable-short other-domain-typecast-short-array' => 'prefixtranslatable-short other-domain-typecast-short-array',
                'translatable-short other-domain-typecast-long-array' => 'prefixtranslatable-short other-domain-typecast-long-array',

                // translation.html.php
                'other-domain-test-no-params-short-array' => 'prefixother-domain-test-no-params-short-array',
                'other-domain-test-no-params-long-array' => 'prefixother-domain-test-no-params-long-array',
                'other-domain-test-params-short-array' => 'prefixother-domain-test-params-short-array',
                'other-domain-test-params-long-array' => 'prefixother-domain-test-params-long-array',
                'other-domain-typecast-short-array' => 'prefixother-domain-typecast-short-array',
                'other-domain-typecast-long-array' => 'prefixother-domain-typecast-long-array',
            ],
        ];

        // Expected metadata (variables)
        $expectedVariables = [
            'messages' => [
                // translatable.html.php
                'translatable single-quoted key' => null,
                'translatable double-quoted key' => null,
                'translatable heredoc key' => null,
                'translatable nowdoc key' => null,
                "translatable double-quoted key with whitespace and escaped \$\n\" sequences" => null,
                'translatable single-quoted key with whitespace and nonescaped \$\n\' sequences' => null,
                'translatable single-quoted key with "quote mark at the end"' => null,
                'translatable '.$expectedHeredoc => null,
                'translatable '.$expectedNowdoc => null,
                'translatable concatenated message with heredoc and nowdoc' => null,
                'translatable test-no-params-short-array' => null,
                'translatable test-no-params-long-array' => null,
                'translatable test-params-short-array' => 'Available variables: foo',
                'translatable test-params-long-array' => 'Available variables: foo',
                'translatable test-multiple-params-short-array' => 'Available variables: foo, foz',
                'translatable test-multiple-params-long-array' => 'Available variables: foo, foz',
                'translatable test-params-trailing-comma-short-array' => 'Available variables: foo',
                'translatable test-params-trailing-comma-long-array' => 'Available variables: foo',
                'translatable typecast-short-array' => 'Available variables: a',
                'translatable typecast-long-array' => 'Available variables: a',
                'translatable default-domain-short-array' => null,
                'translatable default-domain-long-array' => null,

                // translatable-fqn.html.php
                'translatable-fqn single-quoted key' => null,
                'translatable-fqn double-quoted key' => null,
                'translatable-fqn heredoc key' => null,
                'translatable-fqn nowdoc key' => null,
                "translatable-fqn double-quoted key with whitespace and escaped \$\n\" sequences" => null,
                'translatable-fqn single-quoted key with whitespace and nonescaped \$\n\' sequences' => null,
                'translatable-fqn single-quoted key with "quote mark at the end"' => null,
                'translatable-fqn '.$expectedHeredoc => null,
                'translatable-fqn '.$expectedNowdoc => null,
                'translatable-fqn concatenated message with heredoc and nowdoc' => null,
                'translatable-fqn test-no-params-short-array' => null,
                'translatable-fqn test-no-params-long-array' => null,
                'translatable-fqn test-params-short-array' => 'Available variables: foo',
                'translatable-fqn test-params-long-array' => 'Available variables: foo',
                'translatable-fqn test-multiple-params-short-array' => 'Available variables: foo, foz',
                'translatable-fqn test-multiple-params-long-array' => 'Available variables: foo, foz',
                'translatable-fqn test-params-trailing-comma-short-array' => 'Available variables: foo',
                'translatable-fqn test-params-trailing-comma-long-array' => 'Available variables: foo',
                'translatable-fqn typecast-short-array' => 'Available variables: a',
                'translatable-fqn typecast-long-array' => 'Available variables: a',
                'translatable-fqn default-domain-short-array' => null,
                'translatable-fqn default-domain-long-array' => null,

                // translatable-short.html.php
                'translatable-short single-quoted key' => null,
                'translatable-short double-quoted key' => null,
                'translatable-short heredoc key' => null,
                'translatable-short nowdoc key' => null,
                "translatable-short double-quoted key with whitespace and escaped \$\n\" sequences" => null,
                'translatable-short single-quoted key with whitespace and nonescaped \$\n\' sequences' => null,
                'translatable-short single-quoted key with "quote mark at the end"' => null,
                'translatable-short '.$expectedHeredoc => null,
                'translatable-short '.$expectedNowdoc => null,
                'translatable-short concatenated message with heredoc and nowdoc' => null,
                'translatable-short test-no-params-short-array' => null,
                'translatable-short test-no-params-long-array' => null,
                'translatable-short test-params-short-array' => 'Available variables: foo',
                'translatable-short test-params-long-array' => 'Available variables: foo',
                'translatable-short test-multiple-params-short-array' => 'Available variables: foo, foz',
                'translatable-short test-multiple-params-long-array' => 'Available variables: foo, foz',
                'translatable-short test-params-trailing-comma-short-array' => 'Available variables: foo',
                'translatable-short test-params-trailing-comma-long-array' => 'Available variables: foo',
                'translatable-short typecast-short-array' => 'Available variables: a',
                'translatable-short typecast-long-array' => 'Available variables: a',
                'translatable-short default-domain-short-array' => null,
                'translatable-short default-domain-long-array' => null,

                // translation.html.php
                'single-quoted key' => null,
                'double-quoted key' => null,
                'heredoc key' => null,
                'nowdoc key' => null,
                "double-quoted key with whitespace and escaped \$\n\" sequences" => null,
                'single-quoted key with whitespace and nonescaped \$\n\' sequences' => null,
                'single-quoted key with "quote mark at the end"' => null,
                $expectedHeredoc => null,
                $expectedNowdoc => null,
                'concatenated message with heredoc and nowdoc' => null,
                'test-no-params-short-array' => null,
                'test-no-params-long-array' => null,
                'test-params-short-array' => 'Available variables: foo',
                'test-params-long-array' => 'Available variables: foo',
                'test-multiple-params-short-array' => 'Available variables: foo, foz',
                'test-multiple-params-long-array' => 'Available variables: foo, foz',
                'test-params-trailing-comma-short-array' => 'Available variables: foo',
                'test-params-trailing-comma-long-array' => 'Available variables: foo',
                'typecast-short-array' => 'Available variables: a',
                'typecast-long-array' => 'Available variables: a',
                'default-domain-short-array' => null,
                'default-domain-long-array' => null,
            ],
            'not_messages' => [
                // translatable.html.php
                'translatable other-domain-test-no-params-short-array' => null,
                'translatable other-domain-test-no-params-long-array' => null,
                'translatable other-domain-test-params-short-array' => 'Available variables: foo',
                'translatable other-domain-test-params-long-array' => 'Available variables: foo',
                'translatable other-domain-typecast-short-array' => 'Available variables: a',
                'translatable other-domain-typecast-long-array' => 'Available variables: a',

                // translatable-fqn.html.php
                'translatable-fqn other-domain-test-no-params-short-array' => null,
                'translatable-fqn other-domain-test-no-params-long-array' => null,
                'translatable-fqn other-domain-test-params-short-array' => 'Available variables: foo',
                'translatable-fqn other-domain-test-params-long-array' => 'Available variables: foo',
                'translatable-fqn other-domain-typecast-short-array' => 'Available variables: a',
                'translatable-fqn other-domain-typecast-long-array' => 'Available variables: a',

                // translatable-short.html.php
                'translatable-short other-domain-test-no-params-short-array' => null,
                'translatable-short other-domain-test-no-params-long-array' => null,
                'translatable-short other-domain-test-params-short-array' => 'Available variables: foo',
                'translatable-short other-domain-test-params-long-array' => 'Available variables: foo',
                'translatable-short other-domain-typecast-short-array' => 'Available variables: a',
                'translatable-short other-domain-typecast-long-array' => 'Available variables: a',

                // translation.html.php
                'other-domain-test-no-params-short-array' => null,
                'other-domain-test-no-params-long-array' => null,
                'other-domain-test-params-short-array' => 'Available variables: foo',
                'other-domain-test-params-long-array' => 'Available variables: foo',
                'other-domain-typecast-short-array' => 'Available variables: a',
                'other-domain-typecast-long-array' => 'Available variables: a',
            ],
        ];
        $actualCatalogue = $catalogue->all();

        $this->assertEquals($expectedCatalogue, $actualCatalogue);

        $filename = str_replace(\DIRECTORY_SEPARATOR, '/', __DIR__).'/../fixtures/extractor/translatable.html.php';
        $this->assertEquals(['sources' => [$filename.':2']], $catalogue->getMetadata('translatable single-quoted key'));
        $this->assertEquals(['sources' => [$filename.':57']], $catalogue->getMetadata('translatable other-domain-test-no-params-short-array', 'not_messages'));

        $filename = str_replace(\DIRECTORY_SEPARATOR, '/', __DIR__).'/../fixtures/extractor/translatable-fqn.html.php';
        $this->assertEquals(['sources' => [$filename.':2']], $catalogue->getMetadata('translatable-fqn single-quoted key'));
        $this->assertEquals(['sources' => [$filename.':57']], $catalogue->getMetadata('translatable-fqn other-domain-test-no-params-short-array', 'not_messages'));

        $filename = str_replace(\DIRECTORY_SEPARATOR, '/', __DIR__).'/../fixtures/extractor/translatable-short.html.php';
        $this->assertEquals(['sources' => [$filename.':2']], $catalogue->getMetadata('translatable-short single-quoted key'));
        $this->assertEquals(['sources' => [$filename.':57']], $catalogue->getMetadata('translatable-short other-domain-test-no-params-short-array', 'not_messages'));

        $filename = str_replace(\DIRECTORY_SEPARATOR, '/', __DIR__).'/../fixtures/extractor/translation.html.php';
        $this->assertEquals(['sources' => [$filename.':2']], $catalogue->getMetadata('single-quoted key'));
        $this->assertEquals(['sources' => [$filename.':57']], $catalogue->getMetadata('other-domain-test-no-params-short-array', 'not_messages'));

        // Check variables metadata
        $extractedVariables = [];
        foreach (array_keys($actualCatalogue) as $domain) {
            foreach (array_keys($actualCatalogue[$domain]) as $id) {
                $extractedVariables[$domain][$id] = $this->getVariablesNoteContentFromMetadata($catalogue->getMetadata($id, $domain));
            }
        }
        $this->assertEquals($expectedVariables, $extractedVariables);
    }

    /**
     * @requires PHP 7.3
     */
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

    public function resourcesProvider()
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

    private function getVariablesNoteContentFromMetadata(array $metadata)
    {
        /*
         *     $metadata = [
         *         'notes' => [
         *             0 => [
         *                 'category' => 'symfony-extractor-variables',
         *                 'content' => 'Available variables: var1, var2',
         *             ],
         *             ...
         *         ],
         *         ...
         *     ]
         */
        return array_filter($metadata['notes'] ?? [], function ($note) { return 'symfony-extractor-variables' === $note['category']; })[0]['content'] ?? null;
    }
}
