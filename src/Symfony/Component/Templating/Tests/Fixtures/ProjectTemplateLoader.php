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
use Symfony\Component\Templating\TemplateReferenceInterface;

class ProjectTemplateLoader extends Loader
{
    public function load(TemplateReferenceInterface $template): Storage|false
    {
        return false;
    }

    public function getLogger()
    {
        return $this->logger;
    }

    public function isFresh(TemplateReferenceInterface $template, int $time): bool
    {
        return false;
    }
}
