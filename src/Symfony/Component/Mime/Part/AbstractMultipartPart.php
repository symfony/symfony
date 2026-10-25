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
abstract class AbstractMultipartPart extends AbstractPart
{
    private ?string $boundary = null;
    private array $parts = [];

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

    public function asDebugString(): string
    {
        $str = parent::asDebugString();
        foreach ($this->getParts() as $part) {
            $lines = explode("\n", $part->asDebugString());
            $str .= "\n  └ ".array_shift($lines);
            foreach ($lines as $line) {
                $str .= "\n  |".$line;
            }
        }

        return $str;
    }

    public function __serialize(): array
    {
        return parent::__serialize() + ['boundary' => $this->boundary, 'parts' => $this->parts];
    }

    public function __unserialize(array $data): void
    {
        foreach (['', "\0".self::class."\0"] as $prefix) {
            if (!\array_key_exists($prefix.'parts', $data)) {
                continue;
            }

            $boundary = $data[$prefix.'boundary'] ?? null;
            $parts = $data[$prefix.'parts'];
            unset($data[$prefix.'boundary'], $data[$prefix.'parts']);

            if ((null !== $boundary && !\is_string($boundary)) || !\is_array($parts)) {
                throw new \BadMethodCallException('Cannot unserialize '.__CLASS__);
            }

            foreach ($parts as $part) {
                if (!$part instanceof AbstractPart) {
                    throw new \BadMethodCallException('Cannot unserialize '.__CLASS__);
                }
            }

            $this->boundary = $boundary;
            $this->parts = $parts;
            break;
        }

        parent::__unserialize($data);
    }

    private function getBoundary(): string
    {
        return $this->boundary ??= strtr(base64_encode(random_bytes(6)), '+/', '-_');
    }
}
