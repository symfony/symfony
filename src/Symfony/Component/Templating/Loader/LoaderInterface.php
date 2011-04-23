<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Templating\Loader;

use Symfony\Component\Templating\TemplateReferenceInterface;

/**
 * LoaderInterface is the interface all loaders must implement.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
interface LoaderInterface
{
    /**
     * Loads a template.
     *
     * @param TemplateReferenceInterface $template A template
     *
     * @return Storage|Boolean false if the template cannot be loaded, a Storage instance otherwise
     */
    function load(TemplateReferenceInterface $template);

    /**
     * Returns true if the template is still fresh.
     *
     * @param TemplateReferenceInterface $template A template
     * @param integer                    $time     The last modification time of the cached template (timestamp)
     */
    function isFresh(TemplateReferenceInterface $template, $time);
}
