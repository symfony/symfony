<?php

/*
 * This file is part of the Symfony framework.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Symfony\Bundle\AsseticBundle\Factory;

use Assetic\Factory\Resource\ResourceInterface;
use Symfony\Component\Templating\Loader\LoaderInterface;
use Symfony\Component\Templating\TemplateReferenceInterface;

/**
 * A file resource.
 *
 * @author Kris Wallsmith <kris.wallsmith@symfony-project.com>
 */
class FileResource implements ResourceInterface
{
    protected $loader;
    protected $template;

    public function __construct(LoaderInterface $loader, TemplateReferenceInterface $template)
    {
        $this->loader = $loader;
        $this->template = $template;
    }

    public function isFresh($timestamp)
    {
        return $this->loader->isFresh($this->template, $timestamp);
    }

    public function getContent()
    {
        return $this->loader->load($this->template)->getContent();
    }
}
