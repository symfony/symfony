<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Tui\Html\Element;

/**
 * The paths a file field can be completed to, like a shell: the entries of the directory being typed in that start
 * with what follows its last slash, in its case, the directories ending with one, the files of the types accept names.
 *
 * @experimental
 *
 * @internal
 */
final class PathCompleter
{
    /** The extensions of the MIME types accept can name */
    private const TYPES = [
        'image/*' => ['png', 'jpg', 'jpeg', 'gif', 'webp', 'avif', 'svg', 'bmp', 'ico', 'tif', 'tiff', 'heic'],
        'video/*' => ['mp4', 'm4v', 'webm', 'mov', 'mkv', 'avi', 'ogv'],
        'audio/*' => ['mp3', 'wav', 'ogg', 'oga', 'flac', 'm4a', 'aac', 'opus', 'weba'],
        'text/*' => ['txt', 'csv', 'tsv', 'md', 'html', 'htm', 'css', 'xml', 'log'],
        'image/png' => ['png'],
        'image/jpeg' => ['jpg', 'jpeg'],
        'image/gif' => ['gif'],
        'image/webp' => ['webp'],
        'image/svg+xml' => ['svg'],
        'text/plain' => ['txt', 'text', 'log'],
        'text/csv' => ['csv'],
        'text/html' => ['html', 'htm'],
        'text/markdown' => ['md', 'markdown'],
        'application/pdf' => ['pdf'],
        'application/json' => ['json'],
        'application/xml' => ['xml'],
        'application/zip' => ['zip'],
    ];

    /** The most entries of a directory read */
    private const MAX_ENTRIES = 2_000;

    /** @var list<string>|null The extensions of the files offered, null for any */
    private readonly ?array $extensions;

    public function __construct(?string $accept = null, private readonly bool $multiple = false)
    {
        $extensions = [];
        $any = null === $accept || '' === trim($accept);
        foreach (explode(',', $accept ?? '') as $token) {
            $token = strtolower(trim($token));
            if (str_starts_with($token, '.') && \strlen($token) > 1) {
                $extensions[] = substr($token, 1);
            } elseif (isset(self::TYPES[$token])) {
                array_push($extensions, ...self::TYPES[$token]);
            } elseif ('' !== $token) {
                // A type not known here: any file may be one
                $any = true;
            }
        }
        $this->extensions = $any ? null : array_values(array_unique($extensions));
    }

    /**
     * The values the field can be completed to, those of the last path of a multiple one, after its last comma.
     *
     * @return list<string>
     */
    public function complete(string $value): array
    {
        $before = '';
        if ($this->multiple && false !== $comma = strrpos($value, ',')) {
            // The paths before as typed, with the spaces after the comma
            $last = ltrim(substr($value, $comma + 1));
            $before = substr($value, 0, \strlen($value) - \strlen($last));
            $value = $last;
        }
        if ('' === $value) {
            return [];
        }
        if ('~' === $value) {
            return [$before.'~/'];
        }
        // ~ is the home directory, kept as typed
        $home = getenv('HOME') ?: null;
        $path = null !== $home && ('~' === $value || str_starts_with($value, '~/')) ? $home.substr($value, 1) : $value;
        $slash = strrpos($path, '/');
        $directory = false === $slash ? '.' : ('' === substr($path, 0, $slash) ? '/' : substr($path, 0, $slash));
        $typed = false === ($at = strrpos($value, '/')) ? '' : substr($value, 0, $at + 1);
        $prefix = false === $slash ? $path : substr($path, $slash + 1);
        if (!is_dir($directory) || false === $handle = @opendir($directory)) {
            return [];
        }

        $entries = [];
        for ($read = 0; $read < self::MAX_ENTRIES && false !== $name = readdir($handle); ++$read) {
            // Hidden entries when their dot is typed; in their case, which completes the value as typed; not names a
            // field cannot hold, like with a line break
            if ('.' === $name || '..' === $name || (str_starts_with($name, '.') && !str_starts_with($prefix, '.')) || !str_starts_with($name, $prefix) || preg_match('/[\x00-\x1F\x7F]/', $name)) {
                continue;
            }
            // A link to nothing is not offered
            if (!file_exists($directory.'/'.$name)) {
                continue;
            }
            if (is_dir($directory.'/'.$name)) {
                $entries[] = $name.'/';
            } elseif (null === $this->extensions || \in_array(strtolower(pathinfo($name, \PATHINFO_EXTENSION)), $this->extensions, true)) {
                $entries[] = $name;
            }
        }
        closedir($handle);
        sort($entries);

        return array_map(static fn (string $entry) => $before.$typed.$entry, $entries);
    }
}
