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
 *
 * @experimental in 4.3
 */
abstract class AbstractMultipartPart extends AbstractPart
{
    private $boundary;
    private $parts = [];

    public function __construct(AbstractPart ...$parts)
    {
        parent::__construct();

        foreach ($parts as $part) {
            $this->parts[] = $part;
        }
    }

    /**
     * @return AbstractPart[]
     */
    public function getParts(): array
    {
        return $this->parts;
    }

    public function getMediaType(): string
    {
        return 'multipart';
    }

    public function getPreparedHeaders(): Headers
    {
        $headers = parent::getPreparedHeaders();
        $headers->setHeaderParameter('Content-Type', 'boundary', $this->getBoundary());

        return $headers;
    }

    public function bodyToString(): string
    {
        $parts = $this->getParts();
        $string = '';
        foreach ($parts as $part) {
            $string .= '--'.$this->getBoundary()."\r\n".$part->toString()."\r\n";
        }
        $string .= '--'.$this->getBoundary()."--\r\n";

        return $string;
    }

    public function bodyToIterable(): iterable
    {
        $parts = $this->getParts();
        foreach ($parts as $part) {
            yield '--'.$this->getBoundary()."\r\n";
            yield from $part->toIterable();
            yield "\r\n";
        }
        yield '--'.$this->getBoundary()."--\r\n";
    }

    private function getBoundary(): string
    {
        if (null === $this->boundary) {
            $this->boundary = '_=_symfony_'.time().'_'.bin2hex(random_bytes(16)).'_=_';
        }

        return $this->boundary;
    }
}
