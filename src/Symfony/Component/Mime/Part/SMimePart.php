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
 * @author Sebastiaan Stok <s.stok@rollerscapes.net>
 */
class SMimePart extends AbstractPart
{
    public function __construct(
        private iterable|string $body,
        private string $type,
        private string $subtype,
        private array $parameters,
    ) {
        parent::__construct();
    }

    public function getMediaType(): string
    {
        return $this->type;
    }

    public function getMediaSubtype(): string
    {
        return $this->subtype;
    }

    public function bodyToString(): string
    {
        if (\is_string($this->body)) {
            return $this->body;
        }

        $body = '';
        foreach ($this->body as $chunk) {
            $body .= $chunk;
        }
        $this->body = $body;

        return $body;
    }

    public function bodyToIterable(): iterable
    {
        if (\is_string($this->body)) {
            yield $this->body;

            return;
        }

        $body = '';
        foreach ($this->body as $chunk) {
            $body .= $chunk;
            yield $chunk;
        }
        $this->body = $body;
    }

    public function getPreparedHeaders(): Headers
    {
        $headers = clone parent::getHeaders();

        $headers->setHeaderBody('Parameterized', 'Content-Type', $this->getMediaType().'/'.$this->getMediaSubtype());

        foreach ($this->parameters as $name => $value) {
            $headers->setHeaderParameter('Content-Type', $name, $value);
        }

        return $headers;
    }

    public function __serialize(): array
    {
        // convert iterables to strings for serialization
        if (is_iterable($this->body)) {
            $this->body = $this->bodyToString();
        }

        return [
            '_headers' => $this->getHeaders(),
            'body' => $this->body,
            'type' => $this->type,
            'subtype' => $this->subtype,
            'parameters' => $this->parameters,
        ];
    }

    public function __unserialize(array $data): void
    {
        parent::__unserialize(['headers' => $data['_headers'] ?? $data["\0*\0_headers"]]);
        $this->body = $data['body'] ?? $data["\0".self::class."\0body"];
        $this->type = $data['type'] ?? $data["\0".self::class."\0type"];
        $this->subtype = $data['subtype'] ?? $data["\0".self::class."\0subtype"];
        $this->parameters = $data['parameters'] ?? $data["\0".self::class."\0parameters"];
    }
}
