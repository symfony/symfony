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

@trigger_error('The '.DelegatingEngine::class.' class is deprecated since version 4.3 and will be removed in 5.0; use Twig instead.', \E_USER_DEPRECATED);

use Psr\Container\ContainerInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Templating\DelegatingEngine as BaseDelegatingEngine;

/**
 * DelegatingEngine selects an engine for a given template.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 *
 * @deprecated since version 4.3, to be removed in 5.0; use Twig instead.
 */
class DelegatingEngine extends BaseDelegatingEngine implements EngineInterface
{
    protected $container;

    public function __construct(ContainerInterface $container, array $engineIds)
    {
        $this->container = $container;
        $this->engines = $engineIds;
    }

    /**
     * {@inheritdoc}
     */
    public function getEngine($name)
    {
        $this->resolveEngines();

        return parent::getEngine($name);
    }

    /**
     * {@inheritdoc}
     */
    public function renderResponse($view, array $parameters = [], Response $response = null)
    {
        $engine = $this->getEngine($view);

        if ($engine instanceof EngineInterface) {
            return $engine->renderResponse($view, $parameters, $response);
        }

        if (null === $response) {
            $response = new Response();
        }

        $response->setContent($engine->render($view, $parameters));

        return $response;
    }

    /**
     * Resolved engine ids to their real engine instances from the container.
     */
    private function resolveEngines()
    {
        foreach ($this->engines as $i => $engine) {
            if (\is_string($engine)) {
                $this->engines[$i] = $this->container->get($engine);
            }
        }
    }
}
