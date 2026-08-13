<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Symfony\Component\Translation\TranslatableMessage;

new App\Model\TranslatableMessage('message from an unrelated class');
new \TranslatableMessage\Message('message from an unrelated namespace');
new TranslatableMessage('message from the Symfony class');
