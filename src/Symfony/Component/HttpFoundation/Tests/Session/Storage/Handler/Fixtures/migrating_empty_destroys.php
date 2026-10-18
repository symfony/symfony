<?php

require __DIR__.'/common.inc';

use Symfony\Component\HttpFoundation\Session\Storage\Handler\MigratingSessionHandler;

session_set_save_handler(new MigratingSessionHandler(new TestSessionHandler('abc|i:123;'), new TestSessionHandler('abc|i:123;')), false);
session_start();

unset($_SESSION['abc']);
