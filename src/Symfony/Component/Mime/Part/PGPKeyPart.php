<?php

namespace Symfony\Component\Mime\Part;

use Symfony\Component\Mime\Part\AbstractPart;

/*
 * @author PuLLi <the@pulli.dev>
 */
class PGPKeyPart extends AbstractPart
{
    private string $key;

    public function __construct(string $key, string $keyName = 'public-key.asc')
    {
        parent::__construct();
        $this->key = $key;
        $headers = $this->getHeaders();
        $headers->addParameterizedHeader('Content-Disposition', 'attachment', [
            'filename' => $keyName,
        ]);
        $headers->addTextHeader('Content-Transfer-Encoding', 'base64');
        $headers->addTextHeader('MIME-Version', '1.0');
    }

    public function bodyToString(): string
    {
        return $this->key;
    }

    public function bodyToIterable(): iterable
    {
        yield $this->key;
    }

    public function getMediaType(): string
    {
        return 'application';
    }

    public function getMediaSubtype(): string
    {
        return 'pgp-keys';
    }
}
