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

use Assetic\Factory\Resource\DirectoryResource as BaseDirectoryResource;
use Symfony\Bundle\FrameworkBundle\Templating\TemplateNameParser;
use Symfony\Component\Templating\Loader\LoaderInterface;

/**
 * A directory resource that creates Symfony2 resources.
 *
 * @author Kris Wallsmith <kris.wallsmith@symfony-project.com>
 */
class DirectoryResource extends BaseDirectoryResource
{
    protected $parser;
    protected $loader;
    protected $bundle;
    protected $baseDirLength;

    public function __construct(TemplateNameParser $parser, LoaderInterface $loader, $bundle, $baseDir, $pattern = null)
    {
        $this->parser = $parser;
        $this->loader = $loader;
        $this->bundle = $bundle;

        $this->baseDirLength = strlen(rtrim($baseDir, '/')) + 1;

        parent::__construct($baseDir, $pattern);
    }

    protected function createResource($path)
    {
        $template = $this->parser->parseFromFilename(substr($path, $this->baseDirLength));
        $template->set('bundle', $this->bundle);

        return new FileResource($this->loader, $template);
    }
}
