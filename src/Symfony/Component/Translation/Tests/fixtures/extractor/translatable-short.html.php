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

<?php t('translatable-short test-no-params-short-array', []); ?>

<?php t('translatable-short test-no-params-long-array', array()); ?>

<?php t('translatable-short test-params-short-array', ['foo' => 'bar']); ?>

<?php t('translatable-short test-params-long-array', array('foo' => 'bar')); ?>

<?php t('translatable-short test-multiple-params-short-array', ['foo' => 'bar', 'foz' => 'baz']); ?>

<?php t('translatable-short test-multiple-params-long-array', array('foo' => 'bar', 'foz' => 'baz')); ?>

<?php t('translatable-short test-params-trailing-comma-short-array', ['foo' => 'bar',]); ?>

<?php t('translatable-short test-params-trailing-comma-long-array', array('foo' => 'bar',)); ?>

<?php t('translatable-short typecast-short-array', ['a' => (int) '123']); ?>

<?php t('translatable-short typecast-long-array', array('a' => (int) '123')); ?>

<?php t('translatable-short other-domain-test-no-params-short-array', [], 'not_messages'); ?>

<?php t('translatable-short other-domain-test-no-params-long-array', array(), 'not_messages'); ?>

<?php t('translatable-short other-domain-test-params-short-array', ['foo' => 'bar'], 'not_messages'); ?>

<?php t('translatable-short other-domain-test-params-long-array', array('foo' => 'bar'), 'not_messages'); ?>

<?php t('translatable-short other-domain-typecast-short-array', ['a' => (int) '123'], 'not_messages'); ?>

<?php t('translatable-short other-domain-typecast-long-array', array('a' => (int) '123'), 'not_messages'); ?>

<?php t('translatable-short default-domain-short-array', [], null); ?>

<?php t('translatable-short default-domain-long-array', array(), null); ?>
