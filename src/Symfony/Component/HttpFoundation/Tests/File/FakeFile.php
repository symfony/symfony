<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HttpFoundation\Tests\File;

use Symfony\Component\HttpFoundation\File\File as OrigFile;

class FakeFile extends OrigFile
{
    private $realpath;

    public function __construct(string $realpath, string $path)
    {
        $this->realpath = $realpath;
        parent::__construct($path, false);
    }

    public function isReadable(): bool
    {
        return true;
    }

    public function getRealpath(): string
    {
        return $this->realpath;
    }

    public function getSize(): int
    {
        return 42;
    }

    public function getMTime(): int
    {
        return time();
    }
}
