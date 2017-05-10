<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HttpFoundation\File\MimeType;

use Symfony\Component\HttpFoundation\File\Exception\FileNotFoundException;
use Symfony\Component\HttpFoundation\File\Exception\AccessDeniedException;

/**
 * Guesses the mime type based on a custom magic file
 * 
 * This should only be used by advanced users. For more information please
 * read the man pages for file(1) and magic(5). You can either use a compiled
 * mgc file or a magic file that has not been compiled yet.
 *
 * <code>
 * $guesser  = new FileinfoMagicFileMimeTypeGuesser('/path/to/magic');
 * $mimetype = $guess->guess('/path/to/file/to/guess/mimetype');
 * </code>
 *
 * @see http://www.darwinsys.com/file/
 *
 * @author Joshua Estes <f1gm3nt@gmail.com>
 */
class FileinfoMagicFileMimeTypeGuesser implements MimeTypeGuesserInterface
{

    /**
     * Path to magic file
     *
     * @var string
     */
    private static $magicFile;

    /**
     * @param null|string $magicFile
     */
    public function __construct($magicFile = null)
    {
        $this->setMagicfile($magicFile);
    }

    /**
     * @param string $magicFile
     */
    public function setMagicfile($magicFile)
    {
        self::$magicFile = realpath($magicFile);
    }

    /**
     * @return Boolean
     */
    public static function isSupported()
    {
        return function_exists('finfo_open') && is_file(self::$magicFile) && is_readable(self::$magicFile);
    }

    /**
     * {@inheritdoc}
     */
    public function guess($path)
    {
        if (!is_file($path)) {
            throw new FileNotFoundException($path);
        }

        if (!is_readable($path)) {
            throw new AccessDeniedException($path);
        }

        if (!self::isSupported()) {
            return null;
        }

        if (!$finfo = new \finfo(FILEINFO_MIME_TYPE, self::$magicFile)) {
            return null;
        }

        return $finfo->file($path);
    }
}
