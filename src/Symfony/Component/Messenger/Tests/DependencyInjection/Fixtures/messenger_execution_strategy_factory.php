<?php

$container->loadFromExtension('messenger', [
    'execution_strategy_factory' => 'app.execution_strategy_factory',
]);
