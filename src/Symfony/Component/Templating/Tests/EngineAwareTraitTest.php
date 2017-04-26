<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Templating\Tests;

use Symfony\Component\Templating\EngineAwareInterface;
use Symfony\Component\Templating\EngineAwareTrait;

class EngineAwareTraitTest extends \PHPUnit_Framework_TestCase
{
    public function testCanSetAndRetrieveEngineFromEngineAwareClass()
    {
        $engineAware = new TemplateEngineAwareClass();
        $engineAware->setTemplateEngine($this->getMock('Symfony\Component\Templating\EngineInterface'));

        $this->assertInstanceOf('Symfony\Component\Templating\EngineInterface', $engineAware->getTemplateEngine());
    }
}

class TemplateEngineAwareClass implements EngineAwareInterface
{
    use EngineAwareTrait;

    public function getTemplateEngine()
    {
        return $this->templateEngine;
    }
}
