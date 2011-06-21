<?php echo str_replace('{{ widget }}',
    $view['form']->render($form, 'FrameworkBundle:Form:number_widget.html.php'),
    $money_pattern
) ?>
