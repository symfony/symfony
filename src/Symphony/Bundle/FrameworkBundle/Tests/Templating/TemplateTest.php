<?php

/*
 * This file is part of the Symphony package.
 *
 * (c) Fabien Potencier <fabien@symphony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symphony\Bundle\FrameworkBundle\Tests\Templating;

use Symphony\Bundle\FrameworkBundle\Tests\TestCase;
use Symphony\Bundle\FrameworkBundle\Templating\TemplateReference;

class TemplateTest extends TestCase
{
    /**
     * @dataProvider getTemplateToPathProvider
     */
    public function testGetPathForTemplate($template, $path)
    {
        $this->assertSame($template->getPath(), $path);
    }

    public function getTemplateToPathProvider()
    {
        return array(
            array(new TemplateReference('FooBundle', 'Post', 'index', 'html', 'php'), '@FooBundle/Resources/views/Post/index.html.php'),
            array(new TemplateReference('FooBundle', '', 'index', 'html', 'twig'), '@FooBundle/Resources/views/index.html.twig'),
            array(new TemplateReference('', 'Post', 'index', 'html', 'php'), 'views/Post/index.html.php'),
            array(new TemplateReference('', '', 'index', 'html', 'php'), 'views/index.html.php'),
        );
    }
}
