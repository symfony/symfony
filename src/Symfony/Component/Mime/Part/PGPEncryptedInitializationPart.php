<?php

namespace Symfony\Component\Mime\Part;

use Symfony\Component\Mime\Part\AbstractPart;

/*
 * @author PuLLi <the@pulli.dev>
 */
class PGPEncryptedInitializationPart extends AbstractPart
{
    public function __construct()
    {
        parent::__construct();
        $this->getHeaders()->addTextHeader('Content-Disposition', 'attachment');
    }
    public function bodyToString(): string
    {
        return "Version: 1\r\n";
    }

    public function bodyToIterable(): iterable
    {
        yield $this->bodyToString();
    }

    public function getMediaType(): string
    {
        return 'application';
    }

    public function getMediaSubtype(): string
    {
        return 'pgp-encrypted';
    }
}
