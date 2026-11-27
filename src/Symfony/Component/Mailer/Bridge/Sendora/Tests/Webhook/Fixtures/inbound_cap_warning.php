<?php

use Symfony\Component\RemoteEvent\RemoteEvent;

return new RemoteEvent('inbound_cap_warning', '27e3cedf-5a6c-4d7e-9f8a-0b1c2d3e4f5a', json_decode(file_get_contents(str_replace('.php', '.json', __FILE__)), true));
