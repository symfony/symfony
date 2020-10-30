This template is used for translation message extraction tests
<?php new \Symfony\Component\Translation\TranslatableMessage('translatable-fqn single-quoted key'); ?>
<?php new \Symfony\Component\Translation\TranslatableMessage('translatable-fqn double-quoted key'); ?>
<?php new \Symfony\Component\Translation\TranslatableMessage(<<<EOF
translatable-fqn heredoc key
EOF
); ?>
<?php new \Symfony\Component\Translation\TranslatableMessage(<<<'EOF'
translatable-fqn nowdoc key
EOF
); ?>
<?php new \Symfony\Component\Translation\TranslatableMessage(
    "translatable-fqn double-quoted key with whitespace and escaped \$\n\" sequences"
); ?>
<?php new \Symfony\Component\Translation\TranslatableMessage(
    'translatable-fqn single-quoted key with whitespace and nonescaped \$\n\' sequences'
); ?>
<?php new \Symfony\Component\Translation\TranslatableMessage(<<<EOF
translatable-fqn heredoc key with whitespace and escaped \$\n sequences
EOF
); ?>
<?php new \Symfony\Component\Translation\TranslatableMessage(<<<'EOF'
translatable-fqn nowdoc key with whitespace and nonescaped \$\n sequences
EOF
); ?>

<?php new \Symfony\Component\Translation\TranslatableMessage('translatable-fqn single-quoted key with "quote mark at the end"'); ?>

<?php new \Symfony\Component\Translation\TranslatableMessage('translatable-fqn concatenated'.' message'.<<<EOF
 with heredoc
EOF
.<<<'EOF'
 and nowdoc
EOF
); ?>

<?php new \Symfony\Component\Translation\TranslatableMessage('translatable-fqn test-no-params-short-array', []); ?>

<?php new \Symfony\Component\Translation\TranslatableMessage('translatable-fqn test-no-params-long-array', array()); ?>

<?php new \Symfony\Component\Translation\TranslatableMessage('translatable-fqn test-params-short-array', ['foo' => 'bar']); ?>

<?php new \Symfony\Component\Translation\TranslatableMessage('translatable-fqn test-params-long-array', array('foo' => 'bar')); ?>

<?php new \Symfony\Component\Translation\TranslatableMessage('translatable-fqn test-multiple-params-short-array', ['foo' => 'bar', 'foz' => 'baz']); ?>

<?php new \Symfony\Component\Translation\TranslatableMessage('translatable-fqn test-multiple-params-long-array', array('foo' => 'bar', 'foz' => 'baz')); ?>

<?php new \Symfony\Component\Translation\TranslatableMessage('translatable-fqn test-params-trailing-comma-short-array', ['foo' => 'bar',]); ?>

<?php new \Symfony\Component\Translation\TranslatableMessage('translatable-fqn test-params-trailing-comma-long-array', array('foo' => 'bar',)); ?>

<?php new \Symfony\Component\Translation\TranslatableMessage('translatable-fqn typecast-short-array', ['a' => (int) '123']); ?>

<?php new \Symfony\Component\Translation\TranslatableMessage('translatable-fqn typecast-long-array', array('a' => (int) '123')); ?>

<?php new \Symfony\Component\Translation\TranslatableMessage('translatable-fqn other-domain-test-no-params-short-array', [], 'not_messages'); ?>

<?php new \Symfony\Component\Translation\TranslatableMessage('translatable-fqn other-domain-test-no-params-long-array', array(), 'not_messages'); ?>

<?php new \Symfony\Component\Translation\TranslatableMessage('translatable-fqn other-domain-test-params-short-array', ['foo' => 'bar'], 'not_messages'); ?>

<?php new \Symfony\Component\Translation\TranslatableMessage('translatable-fqn other-domain-test-params-long-array', array('foo' => 'bar'), 'not_messages'); ?>

<?php new \Symfony\Component\Translation\TranslatableMessage('translatable-fqn other-domain-typecast-short-array', ['a' => (int) '123'], 'not_messages'); ?>

<?php new \Symfony\Component\Translation\TranslatableMessage('translatable-fqn other-domain-typecast-long-array', array('a' => (int) '123'), 'not_messages'); ?>

<?php new \Symfony\Component\Translation\TranslatableMessage('translatable-fqn default-domain-short-array', [], null); ?>

<?php new \Symfony\Component\Translation\TranslatableMessage('translatable-fqn default-domain-long-array', array(), null); ?>
