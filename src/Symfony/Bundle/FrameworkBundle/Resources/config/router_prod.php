<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

/*
 * This file implements rewrite rules for PHP built-in web server.
 *
 * See: http://www.php.net/manual/en/features.commandline.webserver.php
 *
 * If you have custom directory layout, then you have to write your own router
 * and pass it as a value to 'router' option of server:run command.
 *
 * @author: Michał Pipa <michal.pipa.xsolve@gmail.com>
 * @author: Albert Jessurum <ajessu@gmail.com>
 */

// Workaround https://bugs.php.net/64566
if (ini_get('auto_prepend_file') && !in_array(realpath(ini_get('auto_prepend_file')), get_included_files(), true)) {
    require ini_get('auto_prepend_file');
}

if (is_file($_SERVER['DOCUMENT_ROOT'].DIRECTORY_SEPARATOR.$_SERVER['SCRIPT_NAME'])) {
    return false;
}

$_SERVER = array_merge($_SERVER, $_ENV);
$_SERVER['SCRIPT_FILENAME'] = $_SERVER['DOCUMENT_ROOT'].DIRECTORY_SEPARATOR.'app.php';

// Since we are rewriting to app.php, adjust SCRIPT_NAME and PHP_SELF accordingly
$_SERVER['SCRIPT_NAME'] = DIRECTORY_SEPARATOR.'app.php';
$_SERVER['PHP_SELF'] = DIRECTORY_SEPARATOR.'app.php';

require 'app.php';

error_log(sprintf('%s:%d [%d]: %s', $_SERVER['REMOTE_ADDR'], $_SERVER['REMOTE_PORT'], http_response_code(), $_SERVER['REQUEST_URI']), 4);
