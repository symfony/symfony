<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Mime\Part\Multipart;

use Symfony\Component\Mime\Part\AbstractMultipartPart;
use Symfony\Component\Mime\Part\AbstractPart;

/**
 * @author Florent Morselli <florent.morselli@spomky-labs.com>
 *
 * Original idea by PuLLi <the@pulli.dev>
 *
 * @internal
 */
final class PgpEncryptedPart extends AbstractMultipartPart
{
    public function __construct(AbstractPart ...$parts)
    {
        parent::__construct(...$parts);
        $this->getHeaders()->addParameterizedHeader('Content-Type', 'multipart/encrypted', [
            'protocol' => 'application/pgp-encrypted',
        ]);
    }

    public function getMediaSubtype(): string
    {
        return 'encrypted';
    }
}
