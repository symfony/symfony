This template is used for translation message extraction tests
<?php echo $view['translator']->trans(<<<EOF
    heredoc
    indented
      further
    EOF
); ?>
<?php echo $view['translator']->trans(<<<'EOF'
    nowdoc
    indented
      further
    EOF
); ?>
