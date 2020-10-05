This template is used for translation message extraction tests
<?php new Translatable('translatable single-quoted key'); ?>
<?php new Translatable('translatable double-quoted key'); ?>
<?php new Translatable(<<<EOF
translatable heredoc key
EOF
); ?>
<?php new Translatable(<<<'EOF'
translatable nowdoc key
EOF
); ?>
<?php new Translatable(
    "translatable double-quoted key with whitespace and escaped \$\n\" sequences"
); ?>
<?php new Translatable(
    'translatable single-quoted key with whitespace and nonescaped \$\n\' sequences'
); ?>
<?php new Translatable(<<<EOF
translatable heredoc key with whitespace and escaped \$\n sequences
EOF
); ?>
<?php new Translatable(<<<'EOF'
translatable nowdoc key with whitespace and nonescaped \$\n sequences
EOF
); ?>

<?php new Translatable('translatable single-quoted key with "quote mark at the end"'); ?>

<?php new Translatable('translatable concatenated'.' message'.<<<EOF
 with heredoc
EOF
.<<<'EOF'
 and nowdoc
EOF
); ?>

<?php new Translatable('translatable other-domain-test-no-params-short-array', [], 'not_messages'); ?>

<?php new Translatable('translatable other-domain-test-no-params-long-array', [], 'not_messages'); ?>

<?php new Translatable('translatable other-domain-test-params-short-array', ['foo' => 'bar'], 'not_messages'); ?>

<?php new Translatable('translatable other-domain-test-params-long-array', ['foo' => 'bar'], 'not_messages'); ?>

<?php new Translatable('translatable typecast', ['a' => (int) '123'], 'not_messages'); ?>

<?php new Translatable('translatable default domain', [], null); ?>
