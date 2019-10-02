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

@trigger_error('The '.TwigEngine::class.' class is deprecated since version 4.3 and will be removed in 5.0; use \Twig\Environment instead.', E_USER_DEPRECATED);

use Symfony\Component\Templating\EngineInterface;
use Symfony\Component\Templating\StreamingEngineInterface;
use Symfony\Component\Templating\TemplateNameParserInterface;
use Symfony\Component\Templating\TemplateReferenceInterface;
use Twig\Environment;
use Twig\Error\Error;
use Twig\Error\LoaderError;
use Twig\Loader\ExistsLoaderInterface;
use Twig\Loader\SourceContextLoaderInterface;
use Twig\Template;

/**
 * This engine knows how to render Twig templates.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 *
 * @deprecated since version 4.3, to be removed in 5.0; use Twig instead.
 */
class TwigEngine implements EngineInterface, StreamingEngineInterface
{
    protected $environment;
    protected $parser;

    public function __construct(Environment $environment, TemplateNameParserInterface $parser)
    {
        $this->environment = $environment;
        $this->parser = $parser;
    }

    /**
     * {@inheritdoc}
     *
     * It also supports Template as name parameter.
     *
     * @throws Error if something went wrong like a thrown exception while rendering the template
     */
    public function render($name, array $parameters = [])
    {
        return $this->load($name)->render($parameters);
    }

    /**
     * {@inheritdoc}
     *
     * It also supports Template as name parameter.
     *
     * @throws Error if something went wrong like a thrown exception while rendering the template
     */
    public function stream($name, array $parameters = [])
    {
        $this->load($name)->display($parameters);
    }

    /**
     * {@inheritdoc}
     *
     * It also supports Template as name parameter.
     */
    public function exists($name)
    {
        if ($name instanceof Template) {
            return true;
        }

        $loader = $this->environment->getLoader();

        if (1 === Environment::MAJOR_VERSION && !$loader instanceof ExistsLoaderInterface) {
            try {
                // cast possible TemplateReferenceInterface to string because the
                // EngineInterface supports them but LoaderInterface does not
                if ($loader instanceof SourceContextLoaderInterface) {
                    $loader->getSourceContext((string) $name);
                } else {
                    $loader->getSource((string) $name);
                }

                return true;
            } catch (LoaderError $e) {
            }

            return false;
        }

        return $loader->exists((string) $name);
    }

    /**
     * {@inheritdoc}
     *
     * It also supports Template as name parameter.
     */
    public function supports($name)
    {
        if ($name instanceof Template) {
            return true;
        }

        $template = $this->parser->parse($name);

        return 'twig' === $template->get('engine');
    }

    /**
     * Loads the given template.
     *
     * @param string|TemplateReferenceInterface|Template $name A template name or an instance of
     *                                                         TemplateReferenceInterface or Template
     *
     * @return Template
     *
     * @throws \InvalidArgumentException if the template does not exist
     */
    protected function load($name)
    {
        if ($name instanceof Template) {
            return $name;
        }

        try {
            return $this->environment->load((string) $name)->unwrap();
        } catch (LoaderError $e) {
            throw new \InvalidArgumentException($e->getMessage(), $e->getCode(), $e);
        }
    }
}
