<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Notifier\FlashMessage;

use Symfony\Component\Notifier\Notification\Notification;

/**
 * @author Ben Roberts <ben@headsnet.com>
 */
class DefaultFlashMessageImportanceMapper extends AbstractFlashMessageImportanceMapper implements FlashMessageImportanceMapperInterface
{
    protected const IMPORTANCE_MAP = [
        Notification::IMPORTANCE_URGENT => 'notification',
        Notification::IMPORTANCE_HIGH => 'notification',
        Notification::IMPORTANCE_MEDIUM => 'notification',
        Notification::IMPORTANCE_LOW => 'notification',
    ];
}
