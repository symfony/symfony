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
final class PgpKeyPart extends AbstractPgpPart
{
    public function __construct(string $key, string $keyName = 'public-key.asc')
    {
        parent::__construct($key);
        $this->getHeaders()->addParameterizedHeader('Content-Disposition', 'attachment', [
            'filename' => $keyName,
        ]);
    }

    public function getMediaSubtype(): string
    {
        return 'pgp-keys';
    }
}
