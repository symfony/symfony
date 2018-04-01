<?php

/*
 * This file is part of the Symphony package.
 *
 * (c) Fabien Potencier <fabien@symphony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symphony\Bridge\Twig\Tests\Extension\Fixtures;

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
