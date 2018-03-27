<?php echo str_replace('{{ widget }}', $view['form']->block($form, 'form_widget_simple'), $view['form']->formEncodeCurrency($money_pattern)) ?>
