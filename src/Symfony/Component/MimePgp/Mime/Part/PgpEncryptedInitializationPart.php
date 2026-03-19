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
final class PgpEncryptedInitializationPart extends AbstractPart
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
