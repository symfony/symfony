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
class BootstrapFlashMessageImportanceMapper extends AbstractFlashMessageImportanceMapper implements FlashMessageImportanceMapperInterface
{
    protected const IMPORTANCE_MAP = [
        Notification::IMPORTANCE_URGENT => 'danger',
        Notification::IMPORTANCE_HIGH => 'warning',
        Notification::IMPORTANCE_MEDIUM => 'info',
        Notification::IMPORTANCE_LOW => 'success',
    ];
}
