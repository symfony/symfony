<?php

use Symfony\Config\NodeInitialValuesConfig;

return static function (NodeInitialValuesConfig $config) {
    $config->someCleverName(['second'=>'foo'])->first('bar');
    $config->messenger()
        ->transports('fast_queue', ['dsn'=>'sync://'])
        ->serializer('acme');

    $config->messenger()
        ->transports('slow_queue')
        ->dsn('doctrine://')
        ->options(['table'=>'my_messages']);
};
