<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\FrameworkBundle\Controller;

use Symfony\Component\DependencyInjection\ContainerAwareInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Templating\EngineInterface;
use Twig\Environment;

/**
 * TemplateController.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 *
 * @final since version 3.4
 */
class TemplateController implements ContainerAwareInterface
{
    /**
     * @deprecated since version 3.4, to be removed in 4.0
     */
    protected $container;

    private $twig;
    private $templating;

    public function __construct(Environment $twig = null, EngineInterface $templating = null)
    {
        $this->twig = $twig;
        $this->templating = $templating;
    }

    /**
     * @deprecated since version 3.4, to be removed in 4.0 alongside with the ContainerAwareInterface type.
     */
    public function setContainer(ContainerInterface $container = null)
    {
        @trigger_error(sprintf('The "%s()" method is deprecated since Symfony 3.4 and will be removed in 4.0. Inject a Twig Environment or an EngineInterface using the constructor instead.', __METHOD__), E_USER_DEPRECATED);

        if ($container->has('templating')) {
            $this->templating = $container->get('templating');
        } elseif ($container->has('twig')) {
            $this->twig = $container->get('twig');
        }
        $this->container = $container;
    }

    /**
     * Renders a template.
     *
     * @param string    $template  The template name
     * @param int|null  $maxAge    Max age for client caching
     * @param int|null  $sharedAge Max age for shared (proxy) caching
     * @param bool|null $private   Whether or not caching should apply for client caches only
     *
     * @return Response A Response instance
     */
    public function templateAction($template, $maxAge = null, $sharedAge = null, $private = null)
    {
        if ($this->templating) {
            $response = new Response($this->templating->render($template));
        } elseif ($this->twig) {
            $response = new Response($this->twig->render($template));
        } else {
            throw new \LogicException('You can not use the TemplateController if the Templating Component or the Twig Bundle are not available.');
        }

        if ($maxAge) {
            $response->setMaxAge($maxAge);
        }

        if ($sharedAge) {
            $response->setSharedMaxAge($sharedAge);
        }

        if ($private) {
            $response->setPrivate();
        } elseif (false === $private || (null === $private && ($maxAge || $sharedAge))) {
            $response->setPublic();
        }

        return $response;
    }
}
