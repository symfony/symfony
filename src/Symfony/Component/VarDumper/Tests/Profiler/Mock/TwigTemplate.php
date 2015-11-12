<?php

namespace Symfony\Component\VarDumper\Tests\Profiler\Mock;

use Symfony\Component\VarDumper\Cloner\Data;
use Symfony\Component\VarDumper\VarDumper;

class TwigTemplate extends \Twig_Template
{
    public function __construct(\Twig_Environment $environment)
    {
        parent::__construct($environment);
    }

    /**
     * Returns the template name.
     *
     * @return string The template name
     */
    public function getTemplateName()
    {
    }

    /**
     * {@inheritdoc}
     */
    public function display(array $context, array $blocks = array())
    {
        VarDumper::dump(new Data(array($context)));
    }

    /**
     * Auto-generated method to display the template with the given context.
     *
     * @param array $context An array of parameters to pass to the template
     * @param array $blocks  An array of blocks to pass to the template
     */
    protected function doDisplay(array $context, array $blocks = array())
    {
    }

    public function getZero()
    {
        return 0;
    }

    public function getEmpty()
    {
        return '';
    }

    public function getString()
    {
        return 'some_string';
    }

    public function getTrue()
    {
        return true;
    }

    public function getDebugInfo()
    {
        return array(
            29 => 29,
        );
    }

    protected function doGetParent(array $context)
    {
    }

    public function getAttribute($object, $item, array $arguments = array(), $type = \Twig_Template::ANY_CALL, $isDefinedTest = false, $ignoreStrictCheck = false)
    {
        return parent::getAttribute($object, $item, $arguments, $type, $isDefinedTest, $ignoreStrictCheck);
    }
}
