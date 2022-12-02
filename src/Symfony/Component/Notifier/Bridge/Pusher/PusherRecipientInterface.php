<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Notifier\Bridge\Pusher;

use Symfony\Component\Notifier\Recipient\RecipientInterface;

/**
 * @author Yasmany Cubela Medina <yasmanycm@gmail.com>
 */
interface PusherRecipientInterface extends RecipientInterface
{
    public function getChannels(): array;
}
