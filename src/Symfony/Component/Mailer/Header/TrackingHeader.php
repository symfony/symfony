<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Mailer\Header;

use Symfony\Component\Mime\Header\UnstructuredHeader;

/**
 * Boolean header that enables/disables both open and click tracking.
 */
final class TrackingHeader extends UnstructuredHeader
{
    public function __construct(bool $enabled)
    {
        parent::__construct('X-Track', $enabled ? 'true' : 'false');
    }
}
