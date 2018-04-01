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

class TemplateReferenceTest extends TestCase
{
    public function testGetPathWorksWithNamespacedControllers()
    {
        $reference = new TemplateReference('AcmeBlogBundle', 'Admin\Post', 'index', 'html', 'twig');

        $this->assertSame(
            '@AcmeBlogBundle/Resources/views/Admin/Post/index.html.twig',
            $reference->getPath()
        );
    }
}
