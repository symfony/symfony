<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\FrameworkBundle\Templating;

use Psr\Container\ContainerInterface;
use Symfony\Component\Templating\PhpEngine as BasePhpEngine;
use Symfony\Component\Templating\Loader\LoaderInterface;
use Symfony\Component\Templating\TemplateNameParserInterface;
use Symfony\Component\HttpFoundation\Response;

/**
 * This engine knows how to render Symfony templates.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
class PhpEngine extends BasePhpEngine implements EngineInterface
{
    protected $container;

    public function __construct(TemplateNameParserInterface $parser, ContainerInterface $container, LoaderInterface $loader, GlobalVariables $globals = null)
    {
        $this->container = $container;

        parent::__construct($parser, $loader);

        if (null !== $globals) {
            $this->addGlobal('app', $globals);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function get($name)
    {
        if (!isset($this->helpers[$name])) {
            throw new \InvalidArgumentException(sprintf('The helper "%s" is not defined.', $name));
        }

        if (is_string($this->helpers[$name])) {
            $this->helpers[$name] = $this->container->get($this->helpers[$name]);
            $this->helpers[$name]->setCharset($this->charset);
        }

        return $this->helpers[$name];
    }

    /**
     * {@inheritdoc}
     */
    public function setHelpers(array $helpers)
    {
        $this->helpers = $helpers;
    }

    /**
     * {@inheritdoc}
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
