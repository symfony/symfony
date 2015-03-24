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

    /**
     * {@inheritdoc}
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
     * {@inheritdoc}
     *
     * @throws \Twig_Error if something went wrong like a thrown exception while rendering the template
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
