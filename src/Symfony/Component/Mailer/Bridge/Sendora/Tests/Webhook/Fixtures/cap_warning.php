<?php

use Symfony\Component\RemoteEvent\RemoteEvent;

return new RemoteEvent('cap_warning', 'f4b09bac-2d3f-4a4b-8c5d-7e8f9a0b1c2d', json_decode(file_get_contents(str_replace('.php', '.json', __FILE__)), true));
