This template is used for translation message extraction tests
<?php new TranslatableMessage('translatable single-quoted key'); ?>
<?php new TranslatableMessage('translatable double-quoted key'); ?>
<?php new TranslatableMessage(<<<EOF
translatable heredoc key
EOF
); ?>
<?php new TranslatableMessage(<<<'EOF'
translatable nowdoc key
EOF
); ?>
<?php new TranslatableMessage(
    "translatable double-quoted key with whitespace and escaped \$\n\" sequences"
); ?>
<?php new TranslatableMessage(
    'translatable single-quoted key with whitespace and nonescaped \$\n\' sequences'
); ?>
<?php new TranslatableMessage(<<<EOF
translatable heredoc key with whitespace and escaped \$\n sequences
EOF
); ?>
<?php new TranslatableMessage(<<<'EOF'
translatable nowdoc key with whitespace and nonescaped \$\n sequences
EOF
); ?>

<?php new TranslatableMessage('translatable single-quoted key with "quote mark at the end"'); ?>

<?php new TranslatableMessage('translatable concatenated'.' message'.<<<EOF
 with heredoc
EOF
.<<<'EOF'
 and nowdoc
EOF
); ?>

<?php new TranslatableMessage('translatable test-no-params-short-array', []); ?>

<?php new TranslatableMessage('translatable test-no-params-long-array', array()); ?>

<?php new TranslatableMessage('translatable test-params-short-array', ['foo' => 'bar']); ?>

<?php new TranslatableMessage('translatable test-params-long-array', array('foo' => 'bar')); ?>

<?php new TranslatableMessage('translatable test-multiple-params-short-array', ['foo' => 'bar', 'foz' => 'baz']); ?>

<?php new TranslatableMessage('translatable test-multiple-params-long-array', array('foo' => 'bar', 'foz' => 'baz')); ?>

<?php new TranslatableMessage('translatable test-params-trailing-comma-short-array', ['foo' => 'bar',]); ?>

<?php new TranslatableMessage('translatable test-params-trailing-comma-long-array', array('foo' => 'bar',)); ?>

<?php new TranslatableMessage('translatable typecast-short-array', ['a' => (int) '123']); ?>

<?php new TranslatableMessage('translatable typecast-long-array', array('a' => (int) '123')); ?>

<?php new TranslatableMessage('translatable other-domain-test-no-params-short-array', [], 'not_messages'); ?>

<?php new TranslatableMessage('translatable other-domain-test-no-params-long-array', array(), 'not_messages'); ?>

<?php new TranslatableMessage('translatable other-domain-test-params-short-array', ['foo' => 'bar'], 'not_messages'); ?>

<?php new TranslatableMessage('translatable other-domain-test-params-long-array', array('foo' => 'bar'), 'not_messages'); ?>

<?php new TranslatableMessage('translatable other-domain-typecast-short-array', ['a' => (int) '123'], 'not_messages'); ?>

<?php new TranslatableMessage('translatable other-domain-typecast-long-array', array('a' => (int) '123'), 'not_messages'); ?>

<?php new TranslatableMessage('translatable default-domain-short-array', [], null); ?>

<?php new TranslatableMessage('translatable default-domain-long-array', array(), null); ?>
