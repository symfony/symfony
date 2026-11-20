<?php

use Symfony\Component\RemoteEvent\RemoteEvent;

return new RemoteEvent('inbound', '16d2bdce-4f5b-4c6d-8e7f-9a0b1c2d3e4f', json_decode(file_get_contents(str_replace('.php', '.json', __FILE__)), true));
