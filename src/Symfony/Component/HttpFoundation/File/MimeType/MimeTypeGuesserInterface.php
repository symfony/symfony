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

/**
 * Guesses the mime type of a file
 *
 * @author Bernhard Schussek <bernhard.schussek@symfony.com>
 */
interface MimeTypeGuesserInterface
{
    /**
     * Guesses the mime type of the file with the given path
     *
     * @param  string $path   The path to the file
     * 
     * @return string|null    The mime type or null, if none could be guessed
     * 
     * @throws Symfony\Component\HttpFoundation\File\Exception\FileNotFoundException if the file does not exist
     * @throws Symfony\Component\HttpFoundation\File\Exception\AccessDeniedException if the file is not readable
     */
    function guess($path);
}