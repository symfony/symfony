<?php

/* foo.twig */
class __TwigTemplate_VarDumperFixture_u75a09 extends Twig_Template
{
    private $path;

    public function __construct(Twig_Environment $env = null, $path = null)
    {
        if (null !== $env) {
            parent::__construct($env);
        }
        $this->parent = false;
        $this->blocks = array();
        $this->path = $path;
    }

    protected function doDisplay(array $context, array $blocks = array())
    {
        // line 2
        throw new \Exception('Foobar');
    }

    public function getTemplateName()
    {
        return 'foo.twig';
    }

    public function getDebugInfo()
    {
        return array(20 => 1, 21 => 2);
    }

    public function getSourceContext()
    {
        return new Twig_Source("   foo bar\n     twig source\n\n", 'foo.twig', $this->path ?: __FILE__);
    }
}
