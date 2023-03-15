<?php

require __DIR__.'/common.inc';

use Symfony\Component\HttpFoundation\Session\Storage\NativeSessionStorage;

$storage = new NativeSessionStorage(['cookie_samesite' => 'lax']);
$storage->setSaveHandler(new TestSessionHandler());
$storage->start();

$_SESSION = ['foo' => 'bar'];

$storage->regenerate(true);

ob_start(fn ($buffer) => preg_replace('~_sf2_meta.*$~m', '', str_replace(session_id(), 'random_session_id', $buffer)));
