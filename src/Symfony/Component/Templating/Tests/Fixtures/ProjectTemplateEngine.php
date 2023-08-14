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

use Symfony\Component\Templating\Loader\LoaderInterface;
use Symfony\Component\Templating\PhpEngine;

class ProjectTemplateEngine extends PhpEngine
{
    public function getLoader(): LoaderInterface
    {
        return $this->loader;
    }
}
