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
use Symfony\Component\Config\FileLocatorInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Templating\TemplateNameParserInterface;
use Twig\Environment;
use Twig\Error\Error;
use Twig\FileExtensionEscapingStrategy;

/**
 * This engine renders Twig templates.
 *
 * @author Fabien Potencier <fabien@symfony.com>
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
     * @deprecated since version 2.7, to be removed in 3.0.
     *             Inject the escaping strategy on Twig instead.
     */
    public function setDefaultEscapingStrategy($strategy)
    {
        @trigger_error('The '.__METHOD__.' method is deprecated since Symfony 2.7 and will be removed in 3.0. Inject the escaping strategy in the Twig\Environment object instead.', E_USER_DEPRECATED);

        $this->environment->getExtension('Twig\Extension\EscaperExtension')->setDefaultStrategy($strategy);
    }

    /**
     * @deprecated since version 2.7, to be removed in 3.0.
     *             Use the 'name' strategy instead.
     */
    public function guessDefaultEscapingStrategy($name)
    {
        @trigger_error('The '.__METHOD__.' method is deprecated since Symfony 2.7 and will be removed in 3.0. Use the Twig\FileExtensionEscapingStrategy::guess method instead.', E_USER_DEPRECATED);

        return FileExtensionEscapingStrategy::guess($name);
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
