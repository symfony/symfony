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
final class PgpKeyPart extends AbstractPart
{
    public function __construct(private readonly string $key, string $keyName = 'public-key.asc')
    {
        parent::__construct();
        $this->getHeaders()->addParameterizedHeader('Content-Disposition', 'attachment', [
            'filename' => $keyName,
        ]);
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
