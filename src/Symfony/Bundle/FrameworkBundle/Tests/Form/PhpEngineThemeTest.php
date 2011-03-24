<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\FrameworkBundle\Tests\Form;

use Symfony\Bundle\FrameworkBundle\Form\PhpEngineThemeFactory;
use Symfony\Component\Templating\TemplateNameParser;
use Symfony\Component\Templating\Loader\FilesystemLoader;
use Symfony\Component\Templating\PhpEngine;
use Symfony\Tests\Component\Form\Renderer\Theme\AbstractThemeTest;

class PhpEngineThemeTest extends AbstractThemeTest
{
    protected function createThemeFactory()
    {
        $parser = new TemplateNameParser();
        $loader = new FilesystemLoader(__DIR__ . '/../../Resources/views/Form/%name%');
        $engine = new PhpEngine($parser, $loader, array());
        return new PhpEngineThemeFactory($engine);
    }
}