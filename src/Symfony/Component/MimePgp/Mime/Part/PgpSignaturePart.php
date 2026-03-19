<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\MimePgp\Mime\Part;

use Symfony\Component\Mime\Part\AbstractPart;

/**
 * @author PuLLi <the@pulli.dev>
 *
 * @internal
 *
 * @experimental
 */
final class PgpSignaturePart extends AbstractPart
{
    public function __construct(private readonly string $signature)
    {
        parent::__construct();
        $headers = $this->getHeaders();
        $headers->addParameterizedHeader('Content-Type', 'application/pgp-signature', [
            'name' => 'OpenPGP_signature.asc',
        ]);
        $headers->addParameterizedHeader('Content-Disposition', 'attachment', [
            'filename' => 'OpenPGP_signature',
        ]);
        $headers->addTextHeader('Content-Description', 'OpenPGP digital signature');
    }

    public function bodyToString(): string
    {
        return $this->signature;
    }

    public function bodyToIterable(): iterable
    {
        yield $this->signature;
    }

    public function getMediaType(): string
    {
        return 'application';
    }

    public function getMediaSubtype(): string
    {
        return 'pgp-signature';
    }
}
