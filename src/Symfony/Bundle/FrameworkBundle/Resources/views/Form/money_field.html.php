<?php echo str_replace('{{ widget }}',
    $view['form']->render($field, array(), array(), 'FrameworkBundle:Form:number_field.html.php'),
    $field->getPattern()
) ?>
