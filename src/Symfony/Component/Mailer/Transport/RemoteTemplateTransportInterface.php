<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Mailer\Transport;

use Symfony\Component\Mailer\RemoteTemplateEmail;

/**
 * Implemented by transports that can send a {@see RemoteTemplateEmail} by
 * delegating the rendering of the email to a template hosted by the mail provider.
 *
 * @author Florent Blaison <florent.blaison@gmail.com>
 */
interface RemoteTemplateTransportInterface
{
}
