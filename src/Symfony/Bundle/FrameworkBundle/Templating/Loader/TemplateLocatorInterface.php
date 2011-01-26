<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\FrameworkBundle\Templating\Loader;

/**
 * Interfaces for classes that locates templates on disk
 *
 * @author Fabien Potencier <fabien.potencier@symfony-project.com>
 */
interface TemplateLocatorInterface
{
    /**
     * Locates a template on the filesystem.
     *
     * @param array $template The template name as an array
     *
     * @return string An absolute file name
     */
    function locate($template);
}
