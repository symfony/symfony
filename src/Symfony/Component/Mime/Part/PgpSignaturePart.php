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
final class PgpSignaturePart extends AbstractPgpPart
{
    public function __construct(string $signature)
    {
        parent::__construct($signature);
        $headers = $this->getHeaders();
        $headers->addParameterizedHeader('Content-Type', 'application/pgp-signature', [
            'name' => 'OpenPGP_signature.asc',
        ]);
        $headers->addParameterizedHeader('Content-Disposition', 'attachment', [
            'filename' => 'OpenPGP_signature',
        ]);
        $headers->addTextHeader('Content-Description', 'OpenPGP digital signature');
    }

    public function getMediaSubtype(): string
    {
        return 'pgp-signature';
    }
}
