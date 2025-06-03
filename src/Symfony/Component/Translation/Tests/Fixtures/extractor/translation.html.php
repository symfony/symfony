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

