<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Templating\Tests\Fixtures;

use Symfony\Component\Templating\Loader\Loader;
use Symfony\Component\Templating\Storage\Storage;
use Symfony\Component\Templating\Storage\StringStorage;
use Symfony\Component\Templating\TemplateReference;
use Symfony\Component\Templating\TemplateReferenceInterface;

class ProjectTemplateEngineLoader extends Loader
{
    public array $templates = [];

    public function setTemplate($name, $content)
    {
        $template = new TemplateReference($name, 'php');
        $this->templates[$template->getLogicalName()] = $content;
    }

    public function load(TemplateReferenceInterface $template): Storage|false
    {
        if (isset($this->templates[$template->getLogicalName()])) {
            return new StringStorage($this->templates[$template->getLogicalName()]);
        }

        return false;
    }

    public function isFresh(TemplateReferenceInterface $template, int $time): bool
    {
        return false;
    }
}
