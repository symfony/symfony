<?php

namespace Symfony\Component\HttpFoundation\File\MimeType;

use Symfony\Component\HttpFoundation\File\Exception\FileNotFoundException;
use Symfony\Component\HttpFoundation\File\Exception\AccessDeniedException;

/*
 * This file is part of the symfony package.
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

/**
 * Guesses the mime type with the binary "file" (only available on *nix)
 *
 * @author Bernhard Schussek <bernhard.schussek@symfony-project.com>
 */
class FileBinaryMimeTypeGuesser implements MimeTypeGuesserInterface
{
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

        ob_start();

        // need to use --mime instead of -i. see #6641
        passthru(sprintf('file -b --mime %s 2>/dev/null', escapeshellarg($path)), $return);
        if ($return > 0) {
            ob_end_clean();

            return null;
        }

        $type = trim(ob_get_clean());

        if (!preg_match('#^([a-z0-9\-]+/[a-z0-9\-]+)#i', $type, $match)) {
            // it's not a type, but an error message
            return null;
        }

        return $match[1];
    }
}