<?php

use Symfony\Component\RemoteEvent\RemoteEvent;

return new RemoteEvent('tracking_opted_out', 'e3af8a9b-1c2e-4f3a-9b4c-6d7e8f9a0b1c', json_decode(file_get_contents(str_replace('.php', '.json', __FILE__)), true));
