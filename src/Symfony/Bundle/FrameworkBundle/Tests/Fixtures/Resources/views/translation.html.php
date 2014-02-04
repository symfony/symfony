This template is used for translation message extraction tests
<?php echo $view['translator']->trans('single-quoted key') ?>
<?php echo $view['translator']->trans("double-quoted key") ?>
<?php echo $view['translator']->trans( "double-quoted key with whitespace" ) ?>
<?php echo $view['translator']->trans("double-quoted key with \"escaped\" quotes") ?>
<?php echo $view['translator']->trans(
	'single-quoted key with whitespace'
) ?>
<?php echo $view['translator']->trans(<<<EOF
heredoc key
EOF
) ?>

<?php echo $view['translator']->trans( <<<EOF
heredoc key with whitespace
EOF
   ) ?>

<?php echo $view['translator']->trans('single-quoted key with "quote"') ?>
