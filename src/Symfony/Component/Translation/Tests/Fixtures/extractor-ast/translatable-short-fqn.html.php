This template is used for translation message extraction tests
<?php \Symfony\Component\Translation\t('translatable-short-fqn single-quoted key'); ?>
<?php \Symfony\Component\Translation\t('translatable-short-fqn double-quoted key'); ?>
<?php \Symfony\Component\Translation\t(<<<EOF
translatable-short-fqn heredoc key
EOF
); ?>
<?php \Symfony\Component\Translation\t(<<<'EOF'
translatable-short-fqn nowdoc key
EOF
); ?>
<?php \Symfony\Component\Translation\t(
    "translatable-short-fqn double-quoted key with whitespace and escaped \$\n\" sequences"
); ?>
<?php \Symfony\Component\Translation\t(
    'translatable-short-fqn single-quoted key with whitespace and nonescaped \$\n\' sequences'
); ?>
<?php \Symfony\Component\Translation\t(<<<EOF
translatable-short-fqn heredoc key with whitespace and escaped \$\n sequences
EOF
); ?>
<?php \Symfony\Component\Translation\t(<<<'EOF'
translatable-short-fqn nowdoc key with whitespace and nonescaped \$\n sequences
EOF
); ?>

<?php \Symfony\Component\Translation\t('translatable-short-fqn single-quoted key with "quote mark at the end"'); ?>

<?php \Symfony\Component\Translation\t('translatable-short-fqn concatenated'.' message'.<<<EOF
 with heredoc
EOF
.<<<'EOF'
 and nowdoc
EOF
); ?>

<?php \Symfony\Component\Translation\t('translatable-short-fqn other-domain-test-no-params-short-array', [], 'not_messages'); ?>

<?php \Symfony\Component\Translation\t('translatable-short-fqn other-domain-test-no-params-long-array', [], 'not_messages'); ?>

<?php \Symfony\Component\Translation\t('translatable-short-fqn other-domain-test-params-short-array', ['foo' => 'bar'], 'not_messages'); ?>

<?php \Symfony\Component\Translation\t('translatable-short-fqn other-domain-test-params-long-array', ['foo' => 'bar'], 'not_messages'); ?>

<?php \Symfony\Component\Translation\t('translatable-short-fqn typecast', ['a' => (int) '123'], 'not_messages'); ?>

<?php \Symfony\Component\Translation\t('translatable-short-fqn default domain', [], null); ?>
