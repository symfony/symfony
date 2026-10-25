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
 * Base class for the raw "application/*" parts used by PGP/MIME.
 *
 * @author Florent Morselli <florent.morselli@spomky-labs.com>
 *
 * Original idea by PuLLi <the@pulli.dev>
 *
 * @internal
 */
abstract class AbstractPgpPart extends AbstractPart
{
    public function __construct(private readonly string $body)
    {
        parent::__construct();
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

    public function __serialize(): array
    {
        return parent::__serialize() + ['body' => $this->body];
    }

    public function __unserialize(array $data): void
    {
        if (!\is_string($data['body'] ?? null)) {
            throw new \BadMethodCallException('Cannot unserialize '.self::class);
        }

        $this->body = $data['body'];
        unset($data['body']);
        parent::__unserialize($data);
    }
}
