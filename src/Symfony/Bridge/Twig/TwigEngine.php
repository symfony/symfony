<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bridge\Twig;

use Symfony\Component\Templating\EngineInterface;
use Symfony\Component\Templating\StreamingEngineInterface;
use Symfony\Component\Templating\TemplateNameParserInterface;
use Symfony\Component\Templating\TemplateReferenceInterface;

/**
 * This engine knows how to render Twig templates.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
class TwigEngine implements EngineInterface, StreamingEngineInterface
{
    protected $environment;
    protected $parser;

    /**
     * Constructor.
     *
     * @param \Twig_Environment           $environment A \Twig_Environment instance
     * @param TemplateNameParserInterface $parser      A TemplateNameParserInterface instance
     */
    public function __construct(\Twig_Environment $environment, TemplateNameParserInterface $parser)
    {
        $this->environment = $environment;
        $this->parser = $parser;
    }

    /**
     * {@inheritdoc}
     *
     * It also supports \Twig_Template as name parameter.
     *
     * @throws \Twig_Error if something went wrong like a thrown exception while rendering the template
     */
    public function render($name, array $parameters = array())
    {
        return $this->load($name)->render($parameters);
    }

    /**
     * {@inheritdoc}
     *
     * It also supports \Twig_Template as name parameter.
     *
     * @throws \Twig_Error if something went wrong like a thrown exception while rendering the template
     */
    public function stream($name, array $parameters = array())
    {
        $this->load($name)->display($parameters);
    }

    /**
     * {@inheritdoc}
     *
     * It also supports \Twig_Template as name parameter.
     */
    public function exists($name)
    {
        if ($name instanceof \Twig_Template) {
            return true;
        }

        $loader = $this->environment->getLoader();

        if ($loader instanceof \Twig_ExistsLoaderInterface || method_exists($loader, 'exists')) {
            return $loader->exists((string) $name);
        }

        try {
            // cast possible TemplateReferenceInterface to string because the
            // EngineInterface supports them but Twig_LoaderInterface does not
            $loader->getSourceContext((string) $name)->getCode();
        } catch (\Twig_Error_Loader $e) {
            return false;
        }

        return true;
    }

    /**
     * {@inheritdoc}
     *
     * It also supports \Twig_Template as name parameter.
     */
    public function supports($name)
    {
        if ($name instanceof \Twig_Template) {
            return true;
        }

        $template = $this->parser->parse($name);

        return 'twig' === $template->get('engine');
    }

    /**
     * Loads the given template.
     *
     * @param string|TemplateReferenceInterface|\Twig_Template $name A template name or an instance of
     *                                                               TemplateReferenceInterface or \Twig_Template
     *
     * @return \Twig_TemplateInterface A \Twig_TemplateInterface instance
     *
     * @throws \InvalidArgumentException if the template does not exist
     */
    protected function load($name)
    {
        if ($name instanceof \Twig_Template) {
            return $name;
        }

        try {
            return $this->environment->loadTemplate((string) $name);
        } catch (\Twig_Error_Loader $e) {
            throw new \InvalidArgumentException($e->getMessage(), $e->getCode(), $e);
        }
    }
}
