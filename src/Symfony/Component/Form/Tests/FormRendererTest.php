<?php

namespace Symfony\Component\Form\Test;

use Symfony\Component\Templating\PhpEngine;
use Symfony\Component\Templating\TemplateNameParser;
use Symfony\Component\Templating\Loader\FilesystemLoader;
use Symfony\Component\Form\Extension\Templating\TemplatingRendererEngine;
use Symfony\Component\Form\FormRenderer;

class FormRendererTest extends \PHPUnit_Framework_TestCase
{
    private $renderer;
    
    public function setUp()
    {
        $parser = new TemplateNameParser();
        $loader = new FilesystemLoader(array());
        $phpEngine = new PhpEngine($parser, $loader);
        $engine = new TemplatingRendererEngine($phpEngine);
        $this->renderer = new FormRenderer($engine);
    }
    
    public function testHumanize()
    {
        $this->assertEquals('Is active', $this->renderer->humanize('is_active'));
        $this->assertEquals('Is active', $this->renderer->humanize('isActive'));
    }
    
    public function tearDown()
    {
        $this->renderer = null;
    }
}