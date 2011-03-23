<?php echo str_replace('{{ widget }}',
    $renderer->getEngine()->render('number', 'widget', $renderer->getVars()),
    $money_pattern
) ?>
