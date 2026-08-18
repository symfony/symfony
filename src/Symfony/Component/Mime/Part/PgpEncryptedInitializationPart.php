<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Mime\Part;

/**
 * @author Florent Morselli <florent.morselli@spomky-labs.com>
 *
 * Original idea by PuLLi <the@pulli.dev>
 *
 * @internal
 */
final class PgpEncryptedInitializationPart extends AbstractPgpPart
{
    public function __construct()
    {
        parent::__construct("Version: 1\r\n");
        $this->getHeaders()->addTextHeader('Content-Disposition', 'attachment');
    }

    public function getMediaSubtype(): string
    {
        return 'pgp-encrypted';
    }
}
