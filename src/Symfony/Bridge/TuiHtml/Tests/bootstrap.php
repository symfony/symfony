<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

$loader = require dirname(__DIR__).'/vendor/autoload.php';
$loader->addPsr4('Symfony\\Bridge\\TuiHtml\\', dirname(__DIR__));
if (is_dir($core = dirname(__DIR__, 3).'/Component/HtmlRenderer')) {
    $loader->addPsr4('Symfony\\Component\\HtmlRenderer\\', $core, true);
}
