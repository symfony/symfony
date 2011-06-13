<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Tests\Bridge\Twig\Extension\Fixtures;

// Preventing autoloader throwing E_FATAL when Twig is now available
if (!class_exists('Twig_Environment')) {
    class StubFilesystemLoader {
    }
} else {
    class StubFilesystemLoader extends \Twig_Loader_Filesystem
    {
        protected function findTemplate($name)
        {
            // strip away bundle name
            $parts = explode(':', $name);

            return parent::findTemplate(end($parts));
        }
    }
}
