<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\FrameworkBundle\Tests\Templating;

use Symfony\Bundle\FrameworkBundle\Tests\TestCase;
use Symfony\Bundle\FrameworkBundle\Templating\TemplateReference;

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

    public function testGetPathWorksWithOverridenDirectory()
    {
        $reference = new TemplateReference('AcmeBlogBundle', 'Post', 'index', 'html', 'twig', array(
            'AcmeBlogBundle' => '/Views/',
        ));

        $this->assertSame(
            '@AcmeBlogBundle/Views/Post/index.html.twig',
            $reference->getPath()
        );
    }

    public function testGetPathWorksWithOtherOverridenDirectory()
    {
        $reference = new TemplateReference('AcmeBlogBundle', 'Post', 'index', 'html', 'twig', array(
            'OtherBundle' => '/Views/',
        ));

        $this->assertSame(
            '@AcmeBlogBundle/Resources/views/Post/index.html.twig',
            $reference->getPath()
        );
    }
}
