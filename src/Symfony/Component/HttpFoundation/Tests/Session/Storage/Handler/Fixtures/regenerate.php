<?php

require __DIR__.'/common.inc';

session_set_save_handler(new TestSessionHandler('abc|i:123;'), false);
session_start();

session_regenerate_id(true);

ob_start(fn ($buffer) => str_replace(session_id(), 'random_session_id', $buffer));
