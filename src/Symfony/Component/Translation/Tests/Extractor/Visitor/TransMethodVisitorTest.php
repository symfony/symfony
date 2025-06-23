<?php

namespace Symfony\Component\Translation\Tests\Extractor\Visitor;

use Symfony\Component\Translation\Extractor\Visitor\TransMethodVisitor;

final class TransMethodVisitorTest extends AbstractVisitorTestCase
{
    private const FIXTURES_FOLDER = __DIR__ . '/../../Fixtures/extractor-php-ast/trans-method-visitor/';
    public const OTHER_DOMAIN = 'not_messages';

    public function testExtractMessages()
    {
        $catalogue = $this->extract(new TransMethodVisitor(), self::FIXTURES_FOLDER);

        $expectedHeredoc = <<<EOF
heredoc key with whitespace and escaped \$\n sequences
EOF;
        $expectedNowdoc = <<<'EOF'
nowdoc key with whitespace and nonescaped \$\n sequences
EOF;

        $this->assertEquals(
            [
                'messages' => [
                    'single-quoted key' => 'prefixsingle-quoted key',
                    'double-quoted key' => 'prefixdouble-quoted key',
                    'heredoc key' => 'prefixheredoc key',
                    'nowdoc key' => 'prefixnowdoc key',
                    "double-quoted key with whitespace and escaped \$\n\" sequences" => "prefixdouble-quoted key with whitespace and escaped \$\n\" sequences",
                    'single-quoted key with whitespace and nonescaped \\$\\n\' sequences' => 'prefixsingle-quoted key with whitespace and nonescaped \\$\\n\' sequences',
                    $expectedHeredoc => 'prefix'.$expectedHeredoc,
                    $expectedNowdoc => 'prefix'.$expectedNowdoc,
                    'single-quoted key with "quote mark at the end"' => 'prefixsingle-quoted key with "quote mark at the end"',
                    'concatenated message with heredoc and nowdoc' => 'prefixconcatenated message with heredoc and nowdoc',
                    'default domain' => 'prefixdefault domain',
                    'mix-named-arguments' => 'prefixmix-named-arguments',
                    'mix-named-arguments-locale' => 'prefixmix-named-arguments-locale',
                    'mix-named-arguments-without-domain' => 'prefixmix-named-arguments-without-domain',
                    "heredoc\nindented\n  further" => "prefixheredoc\nindented\n  further",
                    "nowdoc\nindented\n  further" => "prefixnowdoc\nindented\n  further",
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
                ],
                'not_messages' => [
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
                ],
            ],
            $catalogue->all(),
        );

        $this->assertEquals(['sources' => [self::FIXTURES_FOLDER . 'translation.html.php:2']], $catalogue->getMetadata('single-quoted key'));
        $this->assertEquals(['sources' => [self::FIXTURES_FOLDER . 'translation.html.php:37']], $catalogue->getMetadata('other-domain-test-no-params-short-array', 'not_messages'));
        $this->assertEquals(['sources' => [self::FIXTURES_FOLDER . 'translation-73.html.php:8']], $catalogue->getMetadata("nowdoc\nindented\n  further"));

        $this->assertEquals(['sources' => [self::FIXTURES_FOLDER . 'translatable-short.html.php:2']], $catalogue->getMetadata('translatable-short single-quoted key'));
        $this->assertEquals(['sources' => [self::FIXTURES_FOLDER . 'translatable-short.html.php:37']], $catalogue->getMetadata('translatable-short other-domain-test-no-params-short-array', 'not_messages'));

        $this->assertEquals(['sources' => [self::FIXTURES_FOLDER . 'translatable-short-fqn.html.php:2']], $catalogue->getMetadata('translatable-short-fqn single-quoted key'));
        $this->assertEquals(['sources' => [self::FIXTURES_FOLDER . 'translatable-short-fqn.html.php:37']], $catalogue->getMetadata('translatable-short-fqn other-domain-test-no-params-short-array', 'not_messages'));
    }
}
