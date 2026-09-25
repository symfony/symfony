This template is used for translation message extraction tests
<?php echo $view['translator']->trans('single-quoted key'); ?>
<?php echo $view['translator']->trans('double-quoted key'); ?>
<?php echo $view['translator']->trans(<<<EOF
heredoc key
EOF
); ?>
<?php echo $view['translator']->trans(<<<'EOF'
nowdoc key
EOF
); ?>
<?php echo $view['translator']->trans(
    "double-quoted key with whitespace and escaped \$\n\" sequences"
); ?>
<?php echo $view['translator']->trans(
    'single-quoted key with whitespace and nonescaped \$\n\' sequences'
); ?>
<?php echo $view['translator']->trans(<<<EOF
heredoc key with whitespace and escaped \$\n sequences
EOF
); ?>
<?php echo $view['translator']->trans(<<<'EOF'
nowdoc key with whitespace and nonescaped \$\n sequences
EOF
); ?>

<?php echo $view['translator']->trans('single-quoted key with "quote mark at the end"'); ?>

<?php echo $view['translator']->trans('concatenated'.' message'.<<<EOF
 with heredoc
EOF
.<<<'EOF'
 and nowdoc
EOF
); ?>

<?php echo $view['translator']->trans('other-domain-test-no-params-short-array', [], 'not_messages'); ?>

<?php echo $view['translator']->trans('other-domain-test-no-params-long-array', [], 'not_messages'); ?>

<?php echo $view['translator']->trans('other-domain-test-params-short-array', ['foo' => 'bar'], 'not_messages'); ?>

<?php echo $view['translator']->trans('other-domain-test-params-long-array', ['foo' => 'bar'], 'not_messages'); ?>

<?php echo $view['translator']->trans('typecast', ['a' => (int) '123'], 'not_messages'); ?>

<?php echo $view['translator']->trans('default domain', [], null); ?>

<?php echo $view['translator']->trans(id: 'ordered-named-arguments-in-trans-method', parameters: [], domain: 'not_messages'); ?>
<?php echo $view['translator']->trans(domain: 'not_messages', id: 'disordered-named-arguments-in-trans-method', parameters: []); ?>

<?php echo $view['translator']->trans($key = 'variable-assignation-inlined-in-trans-method-call1', $parameters = [], $domain = 'not_messages'); ?>
<?php echo $view['translator']->trans('variable-assignation-inlined-in-trans-method-call2', $parameters = [], $domain = 'not_messages'); ?>
<?php echo $view['translator']->trans('variable-assignation-inlined-in-trans-method-call3', [], $domain = 'not_messages'); ?>

<?php echo $view['translator']->trans(domain: $domain = 'not_messages', id: $key = 'variable-assignation-inlined-with-named-arguments-in-trans-method', parameters: $parameters = []); ?>

<?php echo $view['translator']->trans('mix-named-arguments', parameters: ['foo' => 'bar']); ?>
<?php echo $view['translator']->trans('mix-named-arguments-locale', parameters: ['foo' => 'bar'], locale: 'de'); ?>
<?php echo $view['translator']->trans('mix-named-arguments-without-domain', parameters: ['foo' => 'bar']); ?>
<?php echo $view['translator']->trans('mix-named-arguments-without-parameters', domain: 'not_messages'); ?>
<?php echo $view['translator']->trans('mix-named-arguments-disordered', domain: 'not_messages', parameters: []); ?>

<?php echo $view['translator']->trans(...); // should not fail ?>

<?php
use Symfony\Component\Translation\Tests\Extractor\PhpAstExtractorTest;
echo $view['translator']->trans('const-domain', [], PhpAstExtractorTest::OTHER_DOMAIN);
?>

<?php echo $view['translator']->trans($amended ? 'ternary-if' : 'ternary-else'); ?>
<?php echo $view['translator']->trans($amended ? 'ternary-other-domain-if' : 'ternary-other-domain-else', [], 'not_messages'); ?>
<?php echo $view['translator']->trans('ternary-'.($amended ? 'concatenated-if' : 'concatenated-else')); ?>
<?php echo $view['translator']->trans($amended ? 'nested-ternary-1' : ($cancelled ? 'nested-ternary-2' : 'nested-ternary-3')); ?>
<?php echo $view['translator']->trans($amended ? 'ternary-with-dynamic-else' : $customTitle); ?>
<?php echo $view['translator']->trans($customTitle ?: 'short-ternary-fallback'); ?>
<?php echo $view['translator']->trans($amended ? 'ternary-duplicate' : 'ternary-duplicate'); ?>
<?php echo $view['translator']->trans($customTitle ?? 'coalesce-fallback'); ?>
<?php echo $view['translator']->trans('ternary-domain-key', [], $amended ? 'ternary_domain_a' : 'ternary_domain_b'); ?>
<?php echo $view['translator']->trans(($amended ? 'concat-dup' : 'concat-du').($cancelled ? '' : 'p')); ?>
<?php echo $view['translator']->trans($key = 'variable-assignation-'.'concatenated'); ?>
<?php echo $view['translator']->trans($key = $amended ? 'variable-assignation-ternary-if' : 'variable-assignation-ternary-else', [], $domain = PhpAstExtractorTest::OTHER_DOMAIN); ?>
<?php echo $view['translator']->trans(match ($status) { 'draft' => 'match-inline-draft', default => 'match-inline-default' }); ?>
<?php echo $view['translator']->trans(match (true) { $amended => 'match-with-dynamic-arm', default => $customTitle }); ?>
<?php
$matchLabel = match ($status) {
    'draft' => 'match-variable-draft',
    'published', 'archived' => 'match-variable-published',
    default => throw new \LogicException(),
};
echo $view['translator']->trans($matchLabel);

$branchLabel = 'variable-default';
if ($amended) {
    $branchLabel = 'variable-overridden';
}
echo $view['translator']->trans($branchLabel);

$chainedLabel = 'variable-chained';
$copiedLabel = $chainedLabel;
echo $view['translator']->trans($copiedLabel);

$prefix = 'variable-prefix-';
echo $view['translator']->trans($prefix.($amended ? 'if' : 'else'));

$variableDomain = 'variable_domain';
echo $view['translator']->trans('variable-domain-key', [], $variableDomain);
echo $view['translator']->trans(domain: $variableDomain, id: 'variable-named-domain-key');

$lateLabel = 'variable-assigned-before-call';
echo $view['translator']->trans($lateLabel);
$lateLabel = 'variable-assigned-after-call';

$closureLabel = 'variable-used-by-closure';
$notUsedLabel = 'variable-not-used-by-closure';
$callback = function () use ($view, $closureLabel) {
    echo $view['translator']->trans($closureLabel);
    echo $view['translator']->trans($notUsedLabel);
};

$arrowLabel = 'variable-captured-by-arrow-function';
$shadowedLabel = 'variable-shadowed-by-parameter';
$callback = fn () => $view['translator']->trans($arrowLabel);
$callback = fn ($shadowedLabel) => $view['translator']->trans($shadowedLabel);

$scopedLabel = 'variable-from-another-scope';
function translation_fixture_scope($translator)
{
    echo $translator->trans($scopedLabel);
}
?>
<?php
$suffixed = 'compound-';
$suffixed .= $amended ? 'if' : 'else';
echo $view['translator']->trans($suffixed);

$fallbackLabel = $customTitle;
$fallbackLabel ??= 'compound-coalesce-fallback';
echo $view['translator']->trans($fallbackLabel);

$numeric = 'compound-numeric';
$numeric += 1;
echo $view['translator']->trans($numeric);
?>
<?php
$selfReferencing = 'self-referencing-';
$selfReferencing = $selfReferencing.($amended ? 'if' : 'else');
echo $view['translator']->trans($selfReferencing);

$interpolatedSuffix = $amended ? 'if' : 'else';
echo $view['translator']->trans("interpolated-{$interpolatedSuffix}");
echo $view['translator']->trans("interpolated-$interpolatedSuffix-simple-syntax");
echo $view['translator']->trans(<<<EOF
    interpolated-heredoc-{$interpolatedSuffix}
    EOF);
echo $view['translator']->trans("interpolated-{$customTitle}");

switch ($status) {
    case 'draft':
        $switchLabel = 'switch-draft';
        break;
    case 'published':
    case 'archived':
        $switchLabel = 'switch-published';
        break;
    default:
        $switchLabel = 'switch-default';
}
echo $view['translator']->trans($switchLabel);

use Symfony\Component\Translation\Tests\Fixtures\ReturnedMessages\Article;
use Symfony\Component\Translation\Tests\Fixtures\ReturnedMessages\ArticleStatus;

echo $view['translator']->trans(ArticleStatus::Draft->label());
echo $view['translator']->trans(ArticleStatus::fromSwitch($status));
$article = new Article(ArticleStatus::Draft);
echo $view['translator']->trans($article->status->label());
echo $view['translator']->trans($article->getStatus()?->label());
echo $view['translator']->trans($article->getTitle());
echo $view['translator']->trans($article->getSummary());
echo $view['translator']->trans(Article::create()->getTitle());
echo $view['translator']->trans($article->getRecursive(false));

function article_status_label(ArticleStatus $status, $translator)
{
    return $translator->trans($status->label());
}
?>
