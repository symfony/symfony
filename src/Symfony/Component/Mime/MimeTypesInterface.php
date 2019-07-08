<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Mime;

/**
 * @author Fabien Potencier <fabien@symfony.com>
 */
interface MimeTypesInterface extends MimeTypeGuesserInterface
{
    /**
     * Gets the extensions for the given MIME type.
     *
     * @return string[] an array of extensions (first one is the preferred one)
     */
    public function getExtensions(string $mimeType): array;

    /**
     * Gets the MIME types for the given extension.
     *
     * @return string[] an array of MIME types (first one is the preferred one)
     */
    public function getMimeTypes(string $ext): array;
}
