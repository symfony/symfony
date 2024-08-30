<?php

abstract class AbstractTwigTemplate extends Twig\Template
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

/* foo.twig */
class __TwigTemplate_VarDumperFixture_u75a09 extends AbstractTwigTemplate
{
    private $path;

    public function __construct(?Twig\Environment $env = null, $path = null)
    {
        if (null !== $env) {
            parent::__construct($env);
        }
        $this->parent = false;
        $this->blocks = [];
        $this->path = $path;
    }

    protected function doDisplay(array $context, array $blocks = []): array
    {
        // line 2
        throw new \Exception('Foobar');
    }

    public function getTemplateName(): string
    {
        return 'foo.twig';
    }

    public function getDebugInfo(): array
    {
        return [33 => 1, 34 => 2];
    }

    public function getSourceContext(): Twig\Source
    {
        return new Twig\Source("   foo bar\n     twig source\n\n", 'foo.twig', $this->path ?: __FILE__);
    }
}
