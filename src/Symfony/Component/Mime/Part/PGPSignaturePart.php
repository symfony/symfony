<?php

namespace Symfony\Component\Mime\Part;

use Symfony\Component\Mime\Part\AbstractPart;

/*
 * @author PuLLi <the@pulli.dev>
 */
class PGPSignaturePart extends AbstractPart
{
    private string $signature;

    public function __construct(string $signature)
    {
        parent::__construct();
        $this->signature = $signature;
        $headers = $this->getHeaders();
        $headers->addParameterizedHeader('Content-Type', 'application/pgp-signature', [
            'name' => 'OpenPGP_signature.asc',
        ]);
        $headers->addParameterizedHeader('Content-Disposition', 'attachment', [
            'filename' => 'OpenPGP_signature',
        ]);
        $headers->addTextHeader('Content-Description', 'OpenPGP digital signature');
        $headers->addTextHeader('MIME-Version', '1.0');
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
