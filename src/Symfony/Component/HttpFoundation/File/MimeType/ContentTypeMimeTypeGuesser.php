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
 * Guesses the mime type using the PHP function mime_content_type().
 *
 * @author Bernhard Schussek <bernhard.schussek@symfony.com>
 */
class ContentTypeMimeTypeGuesser implements MimeTypeGuesserInterface
{
    /**
     * Returns whether this guesser is supported on the current OS/PHP setup
     *
     * @return Boolean
     */
    static public function isSupported()
    {
        return function_exists('mime_content_type');
    }

    /**
     * Guesses the mime type of the file with the given path
     *
     * @see MimeTypeGuesserInterface::guess()
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

        $type = mime_content_type($path);

        // remove charset (added as of PHP 5.3)
        if (false !== $pos = strpos($type, ';')) {
            $type = substr($type, 0, $pos);
        }

        return $type;
    }
}
