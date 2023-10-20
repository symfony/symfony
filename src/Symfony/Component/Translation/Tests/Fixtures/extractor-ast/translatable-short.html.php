This template is used for translation message extraction tests
<?php t('translatable-short single-quoted key'); ?>
<?php t('translatable-short double-quoted key'); ?>
<?php t(<<<EOF
translatable-short heredoc key
EOF
); ?>
<?php t(<<<'EOF'
translatable-short nowdoc key
EOF
); ?>
<?php t(
    "translatable-short double-quoted key with whitespace and escaped \$\n\" sequences"
); ?>
<?php t(
    'translatable-short single-quoted key with whitespace and nonescaped \$\n\' sequences'
); ?>
<?php t(<<<EOF
translatable-short heredoc key with whitespace and escaped \$\n sequences
EOF
); ?>
<?php t(<<<'EOF'
translatable-short nowdoc key with whitespace and nonescaped \$\n sequences
EOF
); ?>

<?php t('translatable-short single-quoted key with "quote mark at the end"'); ?>

<?php t('translatable-short concatenated'.' message'.<<<EOF
 with heredoc
EOF
.<<<'EOF'
 and nowdoc
EOF
); ?>

<?php t('translatable-short other-domain-test-no-params-short-array', [], 'not_messages'); ?>

<?php t('translatable-short other-domain-test-no-params-long-array', [], 'not_messages'); ?>

<?php t('translatable-short other-domain-test-params-short-array', ['foo' => 'bar'], 'not_messages'); ?>

<?php t('translatable-short other-domain-test-params-long-array', ['foo' => 'bar'], 'not_messages'); ?>

<?php t('translatable-short typecast', ['a' => (int) '123'], 'not_messages'); ?>

<?php t('translatable-short default domain', [], null); ?>
