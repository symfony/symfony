<?php

use Symfony\Component\RemoteEvent\RemoteEvent;

return new RemoteEvent('inbound_cap_reached', '38f4dfea-6b7d-4e8f-8a9b-1c2d3e4f5a6b', json_decode(file_get_contents(str_replace('.php', '.json', __FILE__)), true));
