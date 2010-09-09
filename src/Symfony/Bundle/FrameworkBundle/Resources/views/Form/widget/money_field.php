<?php echo str_replace('{{ widget }}', $view->render('FrameworkBundle:Form:widget/input_field.php', array(
    'field'      => $field,
    'origin'     => $origin,
    'attributes' => $attributes,
    'generator'  => $generator,
)), $origin->getPattern()) ?>
