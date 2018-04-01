<?php

/*
 * This file is part of the Symphony package.
 *
 * (c) Fabien Potencier <fabien@symphony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symphony\Bundle\TwigBundle;

use Symphony\Bridge\Twig\TwigEngine as BaseEngine;
use Symphony\Bundle\FrameworkBundle\Templating\EngineInterface;
use Symphony\Bundle\FrameworkBundle\Templating\TemplateReference;
use Symphony\Component\Templating\TemplateNameParserInterface;
use Symphony\Component\HttpFoundation\Response;
use Symphony\Component\Config\FileLocatorInterface;
use Twig\Environment;
use Twig\Error\Error;

/**
 * This engine renders Twig templates.
 *
 * @author Fabien Potencier <fabien@symphony.com>
 */
class TwigEngine extends BaseEngine implements EngineInterface
{
    protected $locator;

    public function __construct(Environment $environment, TemplateNameParserInterface $parser, FileLocatorInterface $locator)
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
        } catch (Error $e) {
            if ($name instanceof TemplateReference && !method_exists($e, 'setSourceContext')) {
                try {
                    // try to get the real name of the template where the error occurred
                    $name = $e->getTemplateName();
                    $path = (string) $this->locator->locate($this->parser->parse($name));
                    $e->setTemplateName($path);
                } catch (\Exception $e2) {
                }
            }

            throw $e;
        }
    }

    /**
     * {@inheritdoc}
     *
     * @throws Error if something went wrong like a thrown exception while rendering the template
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
