<?php

require __DIR__.'/common.inc';

use Symfony\Component\HttpFoundation\Session\Flash\FlashBag;
use Symfony\Component\HttpFoundation\Session\Storage\NativeSessionStorage;

$storage = new NativeSessionStorage();
// Change sessionId so the test value looks invalid.
$storage->setSaveHandler(new TestSessionHandler('', 'abc123'));
$flash = new FlashBag();
$storage->registerBag($flash);
$storage->start();

// Add something to the session, so it isn't pruned.
$flash->add('foo', 'bar');
echo empty($_SESSION) ? '$_SESSION is empty' : '$_SESSION is not empty';
echo "\n";

ob_start(fn ($buffer) => preg_replace('~_sf2_meta.*$~m', '', str_replace(session_id(), 'random_session_id', $buffer)));
