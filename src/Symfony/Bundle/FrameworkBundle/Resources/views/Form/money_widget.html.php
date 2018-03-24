<?php echo str_replace('{{ widget }}', $view['form']->block($form, 'form_widget_simple'), htmlentities($money_pattern, ENT_QUOTES | (defined('ENT_SUBSTITUTE') ? ENT_SUBSTITUTE : 0), 'UTF-8')) ?>
