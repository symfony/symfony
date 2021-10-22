<?php

abstract class __TwigTemplate_VarDumperFixture_abstractBug extends \Twig\Template
{
    private function createError()
    {
        return new \RuntimeException('Manually triggered error.');
    }

    public function provideError()
    {
        return $this->createError();
    }
}

/* foo_abstract.twig */
class __TwigTemplate_VarDumperFixture_concreteBug extends __TwigTemplate_VarDumperFixture_abstractBug
{
    public function __construct(Twig\Environment $env = null, $path = null)
    {
        if (null !== $env) {
            parent::__construct($env);
        }
        $this->parent = false;
        $this->blocks = [];
        $this->path = $path;
    }
    
    /**
     * @inheritDoc
     */
    public function getTemplateName()
    {
    }

    /**
     * @inheritDoc
     */
    public function getDebugInfo()
    {
    }

    /**
     * @inheritDoc
     */
    public function getSourceContext()
    {
        return new Twig\Source("   foo bar\n     twig source\n\n", 'foo_abstract.twig', $this->path ?: __FILE__);
    }

    /**
     * @inheritDoc
     */
    protected function doDisplay(array $context, array $blocks = [])
    {
    }
}
