<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bridge\Twig\Tests\Extension;

use Symfony\Bridge\Twig\Extension\ExpressionExtension;

class ExpressionExtensionTest extends \PHPUnit_Framework_TestCase
{
    protected $helper;

    public function testExpressionCreation()
    {
        $template = "{{ expression('1 == 1') }}";
        $twig = new \Twig_Environment(new \Twig_Loader_Array(array('template' => $template)), array('debug' => true, 'cache' => false, 'autoescape' => 'html', 'optimizations' => 0));
        $twig->addExtension(new ExpressionExtension());

        $output = $twig->render('template');
        $this->assertEquals('1 == 1', $output);
    }
}
