<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\TwigBundle\Tests;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase as BaseWebTestCase;

class NoTemplatingEntryTest extends BaseWebTestCase
{
    public function test()
    {
        static::bootKernel(array('environment' => 'dev', 'config_dir' => __DIR__."/app", 'test_case' => 'NoTemplating'));

        $container = static::$kernel->getContainer();
        $content = $container->get('twig')->render('index.html.twig');
        $this->assertContains('{ a: b }', $content);
    }
}
