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

trigger_deprecation('symfony/templating', '6.4', '"%s" is deprecated since version 6.4 and will be removed in 7.0. Use Twig instead.', LoaderInterface::class);

/**
 * LoaderInterface is the interface all loaders must implement.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 *
 * @deprecated since Symfony 6.4, use Twig instead
 */
interface LoaderInterface
{
    /**
     * Loads a template.
     */
    public function load(TemplateReferenceInterface $template): Storage|false;

    /**
     * Returns true if the template is still fresh.
     *
     * @param int $time The last modification time of the cached template (timestamp)
     */
    public function isFresh(TemplateReferenceInterface $template, int $time): bool;
}
