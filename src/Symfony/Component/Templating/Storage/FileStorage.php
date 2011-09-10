<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Templating\Storage;

/**
 * FileStorage represents a template stored on the filesystem.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 *
 * @api
 */
class FileStorage extends Storage
{
    /**
     * Returns the content of the template.
     *
     * @return string The template content
     *
     * @api
     */
    public function getContent()
    {
        return file_get_contents($this->template);
    }
}
