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

use Symfony\Component\Mime\Header\Headers;

/**
 * @author Fabien Potencier <fabien@symfony.com>
 */
abstract class AbstractPart
{
    private Headers $headers;

    public function __construct()
    {
        $this->headers = new Headers();
    }

    public function getHeaders(): Headers
    {
        return $this->headers;
    }

    /**
     * Sets a parameter of the "Content-Type" header, e.g. "method" for "text/calendar".
     *
     * @return $this
     */
    public function setContentTypeParameter(string $name, string $value): static
    {
        if (!$this->headers->has('Content-Type')) {
            $this->headers->addParameterizedHeader('Content-Type', $this->getMediaType().'/'.$this->getMediaSubtype());
        }

        $this->headers->setHeaderParameter('Content-Type', $name, $value);

        return $this;
    }

    /**
     * @return Headers A copy of the headers; changes made to it are not persisted on the part
     */
    public function getPreparedHeaders(): Headers
    {
        $headers = clone $this->headers;
        $headers->setHeaderBody('Parameterized', 'Content-Type', $this->getMediaType().'/'.$this->getMediaSubtype());

        return $headers;
    }

    public function toString(): string
    {
        return $this->getPreparedHeaders()->toString()."\r\n".$this->bodyToString();
    }

    public function toIterable(): iterable
    {
        yield $this->getPreparedHeaders()->toString();
        yield "\r\n";
        yield from $this->bodyToIterable();
    }

    public function asDebugString(): string
    {
        return $this->getMediaType().'/'.$this->getMediaSubtype();
    }

    abstract public function bodyToString(): string;

    abstract public function bodyToIterable(): iterable;

    abstract public function getMediaType(): string;

    abstract public function getMediaSubtype(): string;

    public function __serialize(): array
    {
        return ['headers' => $this->headers];
    }

    public function __unserialize(array $data): void
    {
        $this->headers = $data['headers'];
    }
}
