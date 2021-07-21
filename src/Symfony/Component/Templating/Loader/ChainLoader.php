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
 * ChainLoader is a loader that calls other loaders to load templates.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
class ChainLoader extends Loader
{
    protected $loaders = [];

    /**
     * @param LoaderInterface[] $loaders
     */
    public function __construct(array $loaders = [])
    {
        foreach ($loaders as $loader) {
            $this->addLoader($loader);
        }
    }

    public function addLoader(LoaderInterface $loader)
    {
        $this->loaders[] = $loader;
    }

    /**
     * {@inheritdoc}
     */
    public function load(TemplateReferenceInterface $template)
    {
        foreach ($this->loaders as $loader) {
            if (false !== $storage = $loader->load($template)) {
                return $storage;
            }
        }

        return false;
    }

    /**
     * {@inheritdoc}
     */
    public function isFresh(TemplateReferenceInterface $template, $time)
    {
        foreach ($this->loaders as $loader) {
            return $loader->isFresh($template, $time);
        }

        return false;
    }
}
