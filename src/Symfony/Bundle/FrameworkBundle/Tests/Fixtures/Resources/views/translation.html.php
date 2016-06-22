This template is used for translation message extraction tests
<?php echo $view['translator']->trans('single-quoted key') ?>
<?php echo $view['translator']->trans('double-quoted key') ?>
<?php echo $view['translator']->trans(<<<EOF
heredoc key
EOF
) ?>
<?php echo $view['translator']->trans(<<<'EOF'
nowdoc key
EOF
) ?>
<?php echo $view['translator']->trans(
    "double-quoted key with whitespace and escaped \$\n\" sequences"
) ?>
<?php echo $view['translator']->trans(
    'single-quoted key with whitespace and nonescaped \$\n\' sequences'
) ?>
<?php echo $view['translator']->trans(<<<EOF
heredoc key with whitespace and escaped \$\n sequences
EOF
) ?>
<?php echo $view['translator']->trans(<<<'EOF'
nowdoc key with whitespace and nonescaped \$\n sequences
EOF
) ?>

<?php echo $view['translator']->trans('single-quoted key with "quote mark at the end"') ?>

<?php echo $view['translator']->transChoice(
    '{0} There is no apples|{1} There is one apple|]1,Inf[ There are %count% apples',
    10,
    array('%count%' => 10)
) ?>
