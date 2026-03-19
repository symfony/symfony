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
final class PgpEncryptedMessagePart extends AbstractPart
{
    public function __construct(private readonly string $body)
    {
        parent::__construct();
        $this->getHeaders()->addParameterizedHeader('Content-Disposition', 'inline', [
            'filename' => 'msg.asc',
        ]);
    }

    public function bodyToString(): string
    {
        return $this->body;
    }

    public function bodyToIterable(): iterable
    {
        yield $this->body;
    }

    public function getMediaType(): string
    {
        return 'application';
    }

    public function getMediaSubtype(): string
    {
        return 'octet-stream';
    }
}
