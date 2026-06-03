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
