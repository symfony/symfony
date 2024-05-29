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

<?php new \Symfony\Component\Translation\TranslatableMessage('translatable-fqn other-domain-test-no-params-short-array', [], 'not_messages'); ?>

<?php new \Symfony\Component\Translation\TranslatableMessage('translatable-fqn other-domain-test-no-params-long-array', [], 'not_messages'); ?>

<?php new \Symfony\Component\Translation\TranslatableMessage('translatable-fqn other-domain-test-params-short-array', ['foo' => 'bar'], 'not_messages'); ?>

<?php new \Symfony\Component\Translation\TranslatableMessage('translatable-fqn other-domain-test-params-long-array', ['foo' => 'bar'], 'not_messages'); ?>

<?php new \Symfony\Component\Translation\TranslatableMessage('translatable-fqn typecast', ['a' => (int) '123'], 'not_messages'); ?>

<?php new \Symfony\Component\Translation\TranslatableMessage('translatable-fqn default domain', [], null); ?>
