<?php echo str_replace('{{ widget }}',
    $view['form']->render($field, array(), array(), 'Framework:Form:number_field.html.php'),
    $field->getPattern()
) ?>
