<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bridge\Twig\Tests\Extension\Fixtures;

use Twig\Loader\FilesystemLoader;

class StubFilesystemLoader extends FilesystemLoader
{
    protected function findTemplate($name, $throw = true)
    {
        // strip away bundle name
        $parts = explode(':', $name);

        return parent::findTemplate(end($parts), $throw);
    }
}
