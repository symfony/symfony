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
use Symfony\Component\Templating\TemplateReferenceInterface;

class ProjectTemplateLoaderVar extends Loader
{
    public function getIndexTemplate()
    {
        return 'Hello World';
    }

    public function getSpecialTemplate()
    {
        return 'Hello {{ name }}';
    }

    public function load(TemplateReferenceInterface $template): Storage|false
    {
        if (method_exists($this, $method = 'get'.ucfirst($template->get('name')).'Template')) {
            return new StringStorage($this->$method());
        }

        return false;
    }

    public function isFresh(TemplateReferenceInterface $template, int $time): bool
    {
        return false;
    }
}
