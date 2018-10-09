<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

$symbol = $symbol !== false ? (!empty($symbol) ? ' ' . $symbol : ' %') : '';
echo $view['form']->block($form, 'form_widget_simple', ['type' => isset($type) ? $type : 'text']) . $symbol; ?>
