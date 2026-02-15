<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Console\Input\File;

use Symfony\Component\Console\Exception\InvalidFileException;
use Symfony\Component\Mime\MimeTypes;

/**
 * Represents a file provided through console input.
 *
 * Inspired by HttpFoundation's UploadedFile, this class wraps a file provided
 * through console input (either pasted via terminal image protocols or typed as a path).
 *
 * @author Robin Chalas <robin.chalas@gmail.com>
 */
final class InputFile extends \SplFileInfo
{
    /** @var string[] */
    private static array $tempFiles = [];
    private static bool $shutdownRegistered = false;

    private ?string $mimeType = null;
    private bool $isTempFile;

    public function __construct(
        string $path,
        bool $isTempFile = false,
        ?string $mimeType = null,
    ) {
        parent::__construct($path);

        $this->isTempFile = $isTempFile;
        $this->mimeType = $mimeType;

        if ($isTempFile) {
            if (!self::$shutdownRegistered) {
                register_shutdown_function(self::cleanupAll(...));
                self::$shutdownRegistered = true;
            }
            self::$tempFiles[$path] = $path;
        }
    }

    /**
     * @throws InvalidFileException when the temporary file cannot be created
     */
    public static function fromData(string $data, ?string $format = null): self
    {
        $extension = $format ? '.'.$format : '';
        $tempPath = sys_get_temp_dir().'/symfony_input_'.bin2hex(random_bytes(8)).$extension;

        if (false === @file_put_contents($tempPath, $data)) {
            throw new InvalidFileException(\sprintf('Failed to create temporary file at "%s".', $tempPath));
        }

        return new self($tempPath, true);
    }

    /**
     * @throws InvalidFileException when the file does not exist
     */
    public static function fromPath(string $path): self
    {
        $path = self::normalizePath($path);

        if (!file_exists($path)) {
            throw new InvalidFileException(\sprintf('File "%s" does not exist.', $path));
        }

        return new self($path, false);
    }

    private static function normalizePath(string $path): string
    {
        $path = trim($path);

        if (
            (str_starts_with($path, '"') && str_ends_with($path, '"'))
            || (str_starts_with($path, "'") && str_ends_with($path, "'"))
        ) {
            $path = substr($path, 1, -1);
        }

        if (str_starts_with($path, 'file://')) {
            $path = urldecode(substr($path, 7));

            if ('\\' === \DIRECTORY_SEPARATOR && preg_match('#^/[a-zA-Z]:/#', $path)) {
                $path = substr($path, 1);
            }
        }

        // Remove backslash escapes (e.g., "\ " for escaped spaces) on non-Windows systems
        if ('\\' !== \DIRECTORY_SEPARATOR) {
            $path = preg_replace('/\\\\(.)/', '$1', $path) ?? $path;
        }

        return $path;
    }

    public function getMimeType(): ?string
    {
        if (null !== $this->mimeType) {
            return $this->mimeType;
        }

        if (!$this->isValid()) {
            return null;
        }

        if (class_exists(MimeTypes::class)) {
            return $this->mimeType = MimeTypes::getDefault()->guessMimeType($this->getPathname());
        }

        $finfo = new \finfo(\FILEINFO_MIME_TYPE);

        return $this->mimeType = $finfo->file($this->getPathname()) ?: null;
    }

    public function guessExtension(): ?string
    {
        $mimeType = $this->getMimeType();

        if (null === $mimeType) {
            return null;
        }

        if (class_exists(MimeTypes::class)) {
            $extensions = MimeTypes::getDefault()->getExtensions($mimeType);

            return $extensions[0] ?? null;
        }

        return match ($mimeType) {
            'image/png' => 'png',
            'image/jpeg' => 'jpg',
            'image/gif' => 'gif',
            'image/webp' => 'webp',
            'image/svg+xml' => 'svg',
            'application/pdf' => 'pdf',
            'text/plain' => 'txt',
            default => null,
        };
    }

    /**
     * @throws InvalidFileException when the file is invalid or the move/copy operation fails
     */
    public function move(string $directory, ?string $name = null): self
    {
        if (!$this->isValid()) {
            throw new InvalidFileException('Cannot move an invalid file.');
        }

        $name ??= $this->getFilename();
        $target = rtrim($directory, '/\\').\DIRECTORY_SEPARATOR.$name;

        if (!is_dir($directory)) {
            if (false === @mkdir($directory, 0o777, true) && !is_dir($directory)) {
                throw new InvalidFileException(\sprintf('Unable to create the "%s" directory.', $directory));
            }
        } elseif (!is_writable($directory)) {
            throw new InvalidFileException(\sprintf('Unable to write in the "%s" directory.', $directory));
        }

        if ($this->isTempFile) {
            if (!@rename($this->getPathname(), $target)) {
                throw new InvalidFileException(\sprintf('Could not move the file "%s" to "%s".', $this->getPathname(), $target));
            }
            unset(self::$tempFiles[$this->getPathname()]);
        } else {
            if (!@copy($this->getPathname(), $target)) {
                throw new InvalidFileException(\sprintf('Could not copy the file "%s" to "%s".', $this->getPathname(), $target));
            }
        }

        @chmod($target, 0o666 & ~umask());

        return new self($target, false, $this->mimeType);
    }

    public function cleanup(): void
    {
        if (!$this->isTempFile) {
            return;
        }

        $path = $this->getPathname();

        if (file_exists($path)) {
            @unlink($path);
        }

        unset(self::$tempFiles[$path]);
    }

    /**
     * @internal
     */
    public static function cleanupAll(): void
    {
        foreach (self::$tempFiles as $path) {
            if (file_exists($path)) {
                @unlink($path);
            }
        }

        self::$tempFiles = [];
    }

    public function isValid(): bool
    {
        return is_file($this->getPathname()) && is_readable($this->getPathname());
    }

    public function isTempFile(): bool
    {
        return $this->isTempFile;
    }

    /**
     * @throws InvalidFileException when the file is invalid or cannot be read
     */
    public function getContents(): string
    {
        if (!$this->isValid()) {
            throw new InvalidFileException('Cannot read an invalid file.');
        }

        $contents = @file_get_contents($this->getPathname());

        if (false === $contents) {
            throw new InvalidFileException(\sprintf('Could not read file "%s".', $this->getPathname()));
        }

        return $contents;
    }

    public function getHumanReadableSize(): string
    {
        $size = $this->getSize();
        $units = ['B', 'KB', 'MB', 'GB'];
        $power = $size > 0 ? floor(log($size, 1024)) : 0;
        $power = min($power, \count($units) - 1);

        return \sprintf('%.1f %s', $size / (1024 ** $power), $units[$power]);
    }
}
