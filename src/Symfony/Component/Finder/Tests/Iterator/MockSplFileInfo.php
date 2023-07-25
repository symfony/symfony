<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Finder\Tests\Iterator;

class MockSplFileInfo extends \SplFileInfo
{
    public const TYPE_DIRECTORY = 1;
    public const TYPE_FILE = 2;
    public const TYPE_UNKNOWN = 3;

    private ?string $contents = null;
    private ?string $mode = null;
    private ?int $type = null;
    private ?string $relativePath = null;
    private ?string $relativePathname = null;

    public function __construct($param)
    {
        if (\is_string($param)) {
            parent::__construct($param);
        } elseif (\is_array($param)) {
            $defaults = [
                'name' => 'file.txt',
                'contents' => null,
                'mode' => null,
                'type' => null,
                'relativePath' => null,
                'relativePathname' => null,
            ];
            $defaults = array_merge($defaults, $param);
            parent::__construct($defaults['name']);
            $this->setContents($defaults['contents']);
            $this->setMode($defaults['mode']);
            $this->setType($defaults['type']);
            $this->setRelativePath($defaults['relativePath']);
            $this->setRelativePathname($defaults['relativePathname']);
        } else {
            throw new \RuntimeException(sprintf('Incorrect parameter "%s"', $param));
        }
    }

    public function isFile(): bool
    {
        if (null === $this->type) {
            return str_contains($this->getFilename(), 'file');
        }

        return self::TYPE_FILE === $this->type;
    }

    public function isDir(): bool
    {
        if (null === $this->type) {
            return str_contains($this->getFilename(), 'directory');
        }

        return self::TYPE_DIRECTORY === $this->type;
    }

    public function isReadable(): bool
    {
        return (bool) preg_match('/r\+/', $this->mode ?? $this->getFilename());
    }

    public function getContents()
    {
        return $this->contents;
    }

    public function setContents($contents)
    {
        $this->contents = $contents;
    }

    public function setMode($mode)
    {
        $this->mode = $mode;
    }

    public function setType($type)
    {
        if (\is_string($type)) {
            $this->type = match ($type) {
                'directory',
                'd' => self::TYPE_DIRECTORY,
                'file',
                'f' => self::TYPE_FILE,
                default => self::TYPE_UNKNOWN,
            };
        } else {
            $this->type = $type;
        }
    }

    public function setRelativePath($relativePath)
    {
        $this->relativePath = $relativePath;
    }

    public function setRelativePathname($relativePathname)
    {
        $this->relativePathname = $relativePathname;
    }

    public function getRelativePath()
    {
        return $this->relativePath;
    }

    public function getRelativePathname()
    {
        return $this->relativePathname;
    }
}
