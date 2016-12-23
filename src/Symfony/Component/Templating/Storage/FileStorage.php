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

@trigger_error('The '.FileStorage::class.' class is deprecated since version 3.3 and will be removed in 4.0. Use Twig instead.', E_USER_DEPRECATED);

/**
 * FileStorage represents a template stored on the filesystem.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 *
 * @deprecated The FileStorage class will be removed in Symfony 4.0. You should use Twig instead.
 */
class FileStorage extends Storage
{
    /**
     * Returns the content of the template.
     *
     * @return string The template content
     */
    public function getContent()
    {
        return file_get_contents($this->template);
    }
}
