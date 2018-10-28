<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Stream;

use Symfony\Component\Stream\Wrapper\StreamWrapperInterface;
use Symfony\Component\Stream\Wrapper\UrlStreamWrapperInterface;

/**
 * Represents a stream protocol (i.e. protocol://).
 *
 * @author Roland Franssen <franssen.roland@gmail.com>
 */
final class Protocol
{
    private $name;
    private $wrapperClass;
    private $remote;

    public static function local(string $name, string $wrapperClass): self
    {
        return new self($name, $wrapperClass);
    }

    public static function remote(string $name, string $wrapperClass): self
    {
        return new self($name, $wrapperClass, true);
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getWrapperClass(): string
    {
        return $this->wrapperClass;
    }

    public function isRemote(): bool
    {
        return $this->remote;
    }

    public function toAbsolutePath(string $path): string
    {
        return $this->name.'://'.$path;
    }

    private function __construct(string $name, string $wrapperClass, bool $remote = false)
    {
        $this->name = $name;
        $this->wrapperClass = $wrapperClass;
        $this->remote = $remote;
    }
}
