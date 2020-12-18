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

<?php $view['translator']->trans('test-no-params-short-array', []); ?>

<?php $view['translator']->trans('test-no-params-long-array', array()); ?>

<?php $view['translator']->trans('test-params-short-array', ['foo' => 'bar']); ?>

<?php $view['translator']->trans('test-params-long-array', array('foo' => 'bar')); ?>

<?php $view['translator']->trans('test-multiple-params-short-array', ['foo' => 'bar', 'foz' => 'baz']); ?>

<?php $view['translator']->trans('test-multiple-params-long-array', array('foo' => 'bar', 'foz' => 'baz')); ?>

<?php $view['translator']->trans('test-params-trailing-comma-short-array', ['foo' => 'bar',]); ?>

<?php $view['translator']->trans('test-params-trailing-comma-long-array', array('foo' => 'bar',)); ?>

<?php $view['translator']->trans('typecast-short-array', ['a' => (int) '123']); ?>

<?php $view['translator']->trans('typecast-long-array', array('a' => (int) '123')); ?>

<?php $view['translator']->trans('other-domain-test-no-params-short-array', [], 'not_messages'); ?>

<?php $view['translator']->trans('other-domain-test-no-params-long-array', array(), 'not_messages'); ?>

<?php $view['translator']->trans('other-domain-test-params-short-array', ['foo' => 'bar'], 'not_messages'); ?>

<?php $view['translator']->trans('other-domain-test-params-long-array', array('foo' => 'bar'), 'not_messages'); ?>

<?php $view['translator']->trans('other-domain-typecast-short-array', ['a' => (int) '123'], 'not_messages'); ?>

<?php $view['translator']->trans('other-domain-typecast-long-array', array('a' => (int) '123'), 'not_messages'); ?>

<?php $view['translator']->trans('default-domain-short-array', [], null); ?>

<?php $view['translator']->trans('default-domain-long-array', array(), null); ?>

Check behavior when the same key is used multiple times (no duplicate variables notes, keep higher variables count)
<?php $view['translator']->trans('message-used-multiple-times', ['var1' => 'val1', 'var2' => 'val2']); ?>
<?php $view['translator']->trans('message-used-multiple-times', ['var1' => 'val1', 'var2' => 'val2', 'var3' => 'val3']); ?>
<?php $view['translator']->trans('message-used-multiple-times', ['var1' => 'val1']); ?>
