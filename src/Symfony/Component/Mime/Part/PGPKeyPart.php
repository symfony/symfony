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
