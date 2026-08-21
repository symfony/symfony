<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Notifier;

use Symfony\Component\Notifier\Recipient\RecipientInterface;

/**
 * Provides the recipients that administrative notifications are sent to.
 *
 * @author Nicolas Grekas <p@tchwork.com>
 */
interface AdminRecipientsProviderInterface
{
    /**
     * @return RecipientInterface[]
     */
    public function getAdminRecipients(): array;
}
