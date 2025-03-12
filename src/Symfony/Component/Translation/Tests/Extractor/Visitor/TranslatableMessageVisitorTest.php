<?php

namespace Symfony\Component\Translation\Tests\Extractor\Visitor;

use PhpParser\NodeVisitor;
use Symfony\Component\Translation\Extractor\Visitor\TranslatableMessageVisitor;
use Symfony\Component\Translation\Extractor\Visitor\TransMethodVisitor;
use Symfony\Component\Translation\MessageCatalogue;

class TranslatableMessageVisitorTest extends AbstractVisitorTest
{
    private const FIXTURES_FOLDER = __DIR__ . '/../../Fixtures/extractor-php-ast/translatable-message-visitor/';

    public function getVisitors(): NodeVisitor
    {
        return new TranslatableMessageVisitor();
    }

    public function getResource(): iterable|string
    {
        return self::FIXTURES_FOLDER;
    }

    public function assertCatalogue(MessageCatalogue $catalogue): void
    {
        $expectedHeredoc = <<<EOF
heredoc key with whitespace and escaped \$\n sequences
EOF;
        $expectedNowdoc = <<<'EOF'
nowdoc key with whitespace and nonescaped \$\n sequences
EOF;

        $this->assertEquals(
            [
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
                ],
            ],
            $catalogue->all(),
        );

        $this->assertEquals(['sources' => [self::FIXTURES_FOLDER . 'translatable.html.php:2']], $catalogue->getMetadata('translatable single-quoted key'));
        $this->assertEquals(['sources' => [self::FIXTURES_FOLDER . 'translatable.html.php:37']], $catalogue->getMetadata('translatable other-domain-test-no-params-short-array', 'not_messages'));

        $this->assertEquals(['sources' => [self::FIXTURES_FOLDER . 'translatable-fqn.html.php:2']], $catalogue->getMetadata('translatable-fqn single-quoted key'));
        $this->assertEquals(['sources' => [self::FIXTURES_FOLDER . 'translatable-fqn.html.php:37']], $catalogue->getMetadata('translatable-fqn other-domain-test-no-params-short-array', 'not_messages'));
    }
}
