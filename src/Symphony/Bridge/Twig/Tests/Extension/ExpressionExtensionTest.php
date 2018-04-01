<?php

/*
 * This file is part of the Symphony package.
 *
 * (c) Fabien Potencier <fabien@symphony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symphony\Bridge\Twig\Tests\Extension;

use PHPUnit\Framework\TestCase;
use Symphony\Bridge\Twig\Extension\ExpressionExtension;
use Twig\Environment;
use Twig\Loader\ArrayLoader;

class ExpressionExtensionTest extends TestCase
{
    public function testExpressionCreation()
    {
        $template = "{{ expression('1 == 1') }}";
        $twig = new Environment(new ArrayLoader(array('template' => $template)), array('debug' => true, 'cache' => false, 'autoescape' => 'html', 'optimizations' => 0));
        $twig->addExtension(new ExpressionExtension());

        $output = $twig->render('template');
        $this->assertEquals('1 == 1', $output);
    }
}
