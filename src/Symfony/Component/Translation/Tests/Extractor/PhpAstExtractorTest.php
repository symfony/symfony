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
use Symfony\Component\Translation\Extractor\PhpAstExtractor;
use Symfony\Component\Translation\Extractor\Visitor\ConstraintVisitor;
use Symfony\Component\Translation\Extractor\Visitor\TranslatableMessageVisitor;
use Symfony\Component\Translation\Extractor\Visitor\TransMethodVisitor;
use Symfony\Component\Translation\MessageCatalogue;

final class PhpAstExtractorTest extends TestCase
{
    public const OTHER_DOMAIN = 'not_messages';

    /**
     * @dataProvider resourcesProvider
     */
    public function testExtraction(iterable|string $resource)
    {
        $extractor = new PhpAstExtractor([
            new TransMethodVisitor(),
            new TranslatableMessageVisitor(),
            new ConstraintVisitor([
                'NotBlank',
                'Isbn',
                'Length',
            ], new TranslatableMessageVisitor()),
        ]);
        $extractor->setPrefix('prefix');
        $catalogue = new MessageCatalogue('en');

        $extractor->extract($resource, $catalogue);

        $expectedHeredoc = <<<EOF
heredoc key with whitespace and escaped \$\n sequences
EOF;
        $expectedNowdoc = <<<'EOF'
nowdoc key with whitespace and nonescaped \$\n sequences
EOF;
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
                'translatable-short-fqn single-quoted key' => 'prefixtranslatable-short-fqn single-quoted key',
                'translatable-short-fqn double-quoted key' => 'prefixtranslatable-short-fqn double-quoted key',
                'translatable-short-fqn heredoc key' => 'prefixtranslatable-short-fqn heredoc key',
                'translatable-short-fqn nowdoc key' => 'prefixtranslatable-short-fqn nowdoc key',
                "translatable-short-fqn double-quoted key with whitespace and escaped \$\n\" sequences" => "prefixtranslatable-short-fqn double-quoted key with whitespace and escaped \$\n\" sequences",
                'translatable-short-fqn single-quoted key with whitespace and nonescaped \$\n\' sequences' => 'prefixtranslatable-short-fqn single-quoted key with whitespace and nonescaped \$\n\' sequences',
                'translatable-short-fqn single-quoted key with "quote mark at the end"' => 'prefixtranslatable-short-fqn single-quoted key with "quote mark at the end"',
                'translatable-short-fqn '.$expectedHeredoc => 'prefixtranslatable-short-fqn '.$expectedHeredoc,
                'translatable-short-fqn '.$expectedNowdoc => 'prefixtranslatable-short-fqn '.$expectedNowdoc,
                'translatable-short-fqn concatenated message with heredoc and nowdoc' => 'prefixtranslatable-short-fqn concatenated message with heredoc and nowdoc',
                'translatable-short-fqn default domain' => 'prefixtranslatable-short-fqn default domain',
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
                'mix-named-arguments' => 'prefixmix-named-arguments',
                'mix-named-arguments-locale' => 'prefixmix-named-arguments-locale',
                'mix-named-arguments-without-domain' => 'prefixmix-named-arguments-without-domain',
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
                'translatable-short-fqn other-domain-test-no-params-short-array' => 'prefixtranslatable-short-fqn other-domain-test-no-params-short-array',
                'translatable-short-fqn other-domain-test-no-params-long-array' => 'prefixtranslatable-short-fqn other-domain-test-no-params-long-array',
                'translatable-short-fqn other-domain-test-params-short-array' => 'prefixtranslatable-short-fqn other-domain-test-params-short-array',
                'translatable-short-fqn other-domain-test-params-long-array' => 'prefixtranslatable-short-fqn other-domain-test-params-long-array',
                'translatable-short-fqn typecast' => 'prefixtranslatable-short-fqn typecast',
                'other-domain-test-no-params-short-array' => 'prefixother-domain-test-no-params-short-array',
                'other-domain-test-no-params-long-array' => 'prefixother-domain-test-no-params-long-array',
                'other-domain-test-params-short-array' => 'prefixother-domain-test-params-short-array',
                'other-domain-test-params-long-array' => 'prefixother-domain-test-params-long-array',
                'typecast' => 'prefixtypecast',
                'ordered-named-arguments-in-trans-method' => 'prefixordered-named-arguments-in-trans-method',
                'disordered-named-arguments-in-trans-method' => 'prefixdisordered-named-arguments-in-trans-method',
                'variable-assignation-inlined-in-trans-method-call1' => 'prefixvariable-assignation-inlined-in-trans-method-call1',
                'variable-assignation-inlined-in-trans-method-call2' => 'prefixvariable-assignation-inlined-in-trans-method-call2',
                'variable-assignation-inlined-in-trans-method-call3' => 'prefixvariable-assignation-inlined-in-trans-method-call3',
                'variable-assignation-inlined-with-named-arguments-in-trans-method' => 'prefixvariable-assignation-inlined-with-named-arguments-in-trans-method',
                'mix-named-arguments-without-parameters' => 'prefixmix-named-arguments-without-parameters',
                'mix-named-arguments-disordered' => 'prefixmix-named-arguments-disordered',
                'const-domain' => 'prefixconst-domain',
            ],
            'validators' => [
                'message-in-constraint-attribute' => 'prefixmessage-in-constraint-attribute',
                // 'custom Isbn message from attribute' => 'prefixcustom Isbn message from attribute',
                'custom Isbn message from attribute with options as array' => 'prefixcustom Isbn message from attribute with options as array',
                'custom Length exact message from attribute from named argument' => 'prefixcustom Length exact message from attribute from named argument',
                'custom Length exact message from attribute from named argument 1/2' => 'prefixcustom Length exact message from attribute from named argument 1/2',
                'custom Length min message from attribute from named argument 2/2' => 'prefixcustom Length min message from attribute from named argument 2/2',
                // 'custom Isbn message' => 'prefixcustom Isbn message',
                'custom Isbn message with options as array' => 'prefixcustom Isbn message with options as array',
                'custom Isbn message from named argument' => 'prefixcustom Isbn message from named argument',
                'custom Length exact message from named argument' => 'prefixcustom Length exact message from named argument',
                'custom Length exact message from named argument 1/2' => 'prefixcustom Length exact message from named argument 1/2',
                'custom Length min message from named argument 2/2' => 'prefixcustom Length min message from named argument 2/2',
            ],
        ];
        $actualCatalogue = $catalogue->all();

        $this->assertEquals($expectedCatalogue, $actualCatalogue);

        $filename = str_replace(\DIRECTORY_SEPARATOR, '/', __DIR__).'/../Fixtures/extractor-ast/translatable.html.php';
        $this->assertEquals(['sources' => [$filename.':2']], $catalogue->getMetadata('translatable single-quoted key'));
        $this->assertEquals(['sources' => [$filename.':37']], $catalogue->getMetadata('translatable other-domain-test-no-params-short-array', 'not_messages'));

        $filename = str_replace(\DIRECTORY_SEPARATOR, '/', __DIR__).'/../Fixtures/extractor-ast/translatable-fqn.html.php';
        $this->assertEquals(['sources' => [$filename.':2']], $catalogue->getMetadata('translatable-fqn single-quoted key'));
        $this->assertEquals(['sources' => [$filename.':37']], $catalogue->getMetadata('translatable-fqn other-domain-test-no-params-short-array', 'not_messages'));

        $filename = str_replace(\DIRECTORY_SEPARATOR, '/', __DIR__).'/../Fixtures/extractor-ast/translatable-short.html.php';
        $this->assertEquals(['sources' => [$filename.':2']], $catalogue->getMetadata('translatable-short single-quoted key'));
        $this->assertEquals(['sources' => [$filename.':37']], $catalogue->getMetadata('translatable-short other-domain-test-no-params-short-array', 'not_messages'));

        $filename = str_replace(\DIRECTORY_SEPARATOR, '/', __DIR__).'/../Fixtures/extractor-ast/translatable-short-fqn.html.php';
        $this->assertEquals(['sources' => [$filename.':2']], $catalogue->getMetadata('translatable-short-fqn single-quoted key'));
        $this->assertEquals(['sources' => [$filename.':37']], $catalogue->getMetadata('translatable-short-fqn other-domain-test-no-params-short-array', 'not_messages'));

        $filename = str_replace(\DIRECTORY_SEPARATOR, '/', __DIR__).'/../Fixtures/extractor-ast/translation.html.php';
        $this->assertEquals(['sources' => [$filename.':2']], $catalogue->getMetadata('single-quoted key'));
        $this->assertEquals(['sources' => [$filename.':37']], $catalogue->getMetadata('other-domain-test-no-params-short-array', 'not_messages'));
    }

    public function testExtractionFromIndentedHeredocNowdoc()
    {
        $catalogue = new MessageCatalogue('en');

        $extractor = new PhpAstExtractor([
            new TransMethodVisitor(),
            new TranslatableMessageVisitor(),
            new ConstraintVisitor([
                'NotBlank',
                'Isbn',
                'Length',
            ], new TranslatableMessageVisitor()),
        ]);
        $extractor->setPrefix('prefix');
        $extractor->extract(__DIR__.'/../Fixtures/extractor-7.3/translation.html.php', $catalogue);

        $expectedCatalogue = [
            'messages' => [
                "heredoc\nindented\n  further" => "prefixheredoc\nindented\n  further",
                "nowdoc\nindented\n  further" => "prefixnowdoc\nindented\n  further",
            ],
        ];

        $this->assertEquals($expectedCatalogue, $catalogue->all());
    }

    public static function resourcesProvider(): array
    {
        $directory = __DIR__.'/../Fixtures/extractor-ast/';
        $phpFiles = [];
        $splFiles = [];
        foreach (new \DirectoryIterator($directory) as $fileInfo) {
            if ($fileInfo->isDot()) {
                continue;
            }
            if (\in_array($fileInfo->getBasename(), ['translatable.html.php', 'translatable-fqn.html.php', 'translatable-short.html.php', 'translatable-short-fqn.html.php', 'translation.html.php', 'validator-constraints.php'], true)) {
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
