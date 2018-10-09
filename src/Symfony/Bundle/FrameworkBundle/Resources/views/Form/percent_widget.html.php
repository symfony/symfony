<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

echo $view['form']->block($form, 'form_widget_simple', array('type' => isset($type) ? $type : 'text')) . (isset($symbol) && $symbol ? ' %': '') ?>
