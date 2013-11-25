<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\TwigBundle;

use Symfony\Bridge\Twig\TwigEngine as BaseEngine;
use Symfony\Bundle\FrameworkBundle\Templating\EngineInterface;
use Symfony\Bundle\FrameworkBundle\Templating\TemplateReference;
use Symfony\Component\Templating\TemplateNameParserInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Config\FileLocatorInterface;

/**
 * This engine renders Twig templates.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
class TwigEngine extends BaseEngine implements EngineInterface
{
    protected $locator;

    /**
     * Constructor.
     *
     * @param \Twig_Environment           $environment A \Twig_Environment instance
     * @param TemplateNameParserInterface $parser      A TemplateNameParserInterface instance
     * @param FileLocatorInterface        $locator     A FileLocatorInterface instance
     */
    public function __construct(\Twig_Environment $environment, TemplateNameParserInterface $parser, FileLocatorInterface $locator)
    {
        parent::__construct($environment, $parser);

        $this->locator = $locator;
    }

    public function setDefaultEscapingStrategy($strategy)
    {
        $this->environment->getExtension('escaper')->setDefaultStrategy($strategy);
    }

    public function guessDefaultEscapingStrategy($filename)
    {
        // remove .twig
        $filename = substr($filename, 0, -5);

        // get the format
        $format = substr($filename, strrpos($filename, '.') + 1);

        if ('js' === $format) {
            return 'js';
        }
        
        if ('txt' === $format) {
            return false;
        }

        return 'html';
    }

    /**
     * Renders a template.
     *
     * @param mixed $name       A template name
     * @param array $parameters An array of parameters to pass to the template
     *
     * @return string The evaluated template as a string
     *
     * @throws \InvalidArgumentException if the template does not exist
     * @throws \RuntimeException         if the template cannot be rendered
     * @throws \Twig_Error
     */
    public function render($name, array $parameters = array())
    {
        try {
            return parent::render($name, $parameters);
        } catch (\Twig_Error $e) {
            if ($name instanceof TemplateReference) {
                try {
                    // try to get the real file name of the template where the error occurred
                    $e->setTemplateFile(sprintf('%s', $this->locator->locate($this->parser->parse($e->getTemplateFile()))));
                } catch (\Exception $ex) {
                }
            }

            throw $e;
        }
    }

    /**
     * Renders a view and returns a Response.
     *
     * @param string   $view       The view name
     * @param array    $parameters An array of parameters to pass to the view
     * @param Response $response   A Response instance
     *
     * @return Response A Response instance
     */
    public function renderResponse($view, array $parameters = array(), Response $response = null)
    {
        if (null === $response) {
            $response = new Response();
        }

        $response->setContent($this->render($view, $parameters));

        return $response;
    }
}
