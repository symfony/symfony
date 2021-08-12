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

use Symfony\Component\Templating\Storage\Storage;
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
     * @return Storage|false
     */
    public function load(TemplateReferenceInterface $template);

    /**
     * Returns true if the template is still fresh.
     *
     * @param int $time The last modification time of the cached template (timestamp)
     *
     * @return bool
     */
    public function isFresh(TemplateReferenceInterface $template, int $time);
}
