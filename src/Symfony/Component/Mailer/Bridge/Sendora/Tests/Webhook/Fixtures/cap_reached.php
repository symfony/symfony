<?php

use Symfony\Component\RemoteEvent\RemoteEvent;

return new RemoteEvent('cap_reached', '05c1acbd-3e4a-4b5c-9d6e-8f9a0b1c2d3e', json_decode(file_get_contents(str_replace('.php', '.json', __FILE__)), true));
