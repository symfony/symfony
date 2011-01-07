<?php

namespace Symfony\Bundle\TwigBundle\Renderer;

use Symfony\Component\Templating\Renderer\Renderer as BaseRenderer;
use Symfony\Component\Templating\Storage\Storage;
use Symfony\Component\Templating\Engine;

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

/**
 *
 * @author Fabien Potencier <fabien.potencier@symfony-project.com>
 */
class Renderer extends BaseRenderer
{
    protected $environment;

    public function __construct(\Twig_Environment $environment)
    {
        $this->environment = $environment;
    }

    /**
     * {@inheritdoc}
     */
    public function setEngine(Engine $engine)
    {
        parent::setEngine($engine);

        $container = $engine->getContainer();
        if ($container->has('security.context')) {
            $security = $container->get('security.context');
            $this->environment->addGlobal('security', $security);

            if ($user = $security->getUser()) {
                $this->environment->addGlobal('user', $user);
            }
        }
    }

    /**
     * Evaluates a template.
     *
     * @param Storage $template   The template to render
     * @param array   $parameters An array of parameters to pass to the template
     *
     * @return string|false The evaluated template, or false if the renderer is unable to render the template
     */
    public function evaluate(Storage $template, array $parameters = array())
    {
        $container = $this->engine->getContainer();

        // cannot be set in the constructor as we need the current request
        if ($container->has('request') && ($request = $container->get('request'))) {
            $this->environment->addGlobal('request', $request);
            $this->environment->addGlobal('session', $request->getSession());
        }

        return $this->environment->loadTemplate($template)->render($parameters);
    }
}
