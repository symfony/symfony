<?php echo str_replace('{{ widget }}',
    $renderer->getTheme()->render('number', 'widget', $renderer->getVars()),
    $money_pattern
) ?>
