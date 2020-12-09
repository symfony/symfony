<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Notifier\Recipient;

/**
 * @author Tomas Norkūnas <norkunas.tom@gmail.com>
 */
trait PushRecipientTrait
{
    private $pushId;

    public function getPushId(): string
    {
        return $this->pushId;
    }
}
