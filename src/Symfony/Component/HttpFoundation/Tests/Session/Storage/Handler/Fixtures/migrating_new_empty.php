<?php

require __DIR__.'/common.inc';

use Symfony\Component\HttpFoundation\Session\Storage\Handler\MigratingSessionHandler;

ini_set('session.name', 'new_sid');
ini_set('session.lazy_write', 0);

session_set_save_handler(new MigratingSessionHandler(new TestSessionHandler(), new TestSessionHandler()), false);
session_start();
