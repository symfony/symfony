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

use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Templating\EngineInterface;
use Twig\Environment;

/**
 * TemplateController.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 *
 * @final
 */
class TemplateController
{
    private $twig;
    private $templating;

    public function __construct(Environment $twig = null, EngineInterface $templating = null)
    {
        if (null !== $templating) {
            @trigger_error(sprintf('Using a "%s" instance for "%s" is deprecated since version 4.4; use a \Twig\Environment instance instead.', EngineInterface::class, __CLASS__), \E_USER_DEPRECATED);
        }

        $this->twig = $twig;
        $this->templating = $templating;
    }

    /**
     * Renders a template.
     *
     * @param string    $template  The template name
     * @param int|null  $maxAge    Max age for client caching
     * @param int|null  $sharedAge Max age for shared (proxy) caching
     * @param bool|null $private   Whether or not caching should apply for client caches only
     */
    public function templateAction(string $template, int $maxAge = null, int $sharedAge = null, bool $private = null): Response
    {
        if ($this->templating) {
            $response = new Response($this->templating->render($template));
        } elseif ($this->twig) {
            $response = new Response($this->twig->render($template));
        } else {
            throw new \LogicException('You can not use the TemplateController if the Templating Component or the Twig Bundle are not available.');
        }

        if (null !== $maxAge) {
            $response->setMaxAge($maxAge);
        }

        if (null !== $sharedAge) {
            $response->setSharedMaxAge($sharedAge);
        }

        if ($private) {
            $response->setPrivate();
        } elseif (false === $private || (null === $private && (null !== $maxAge || null !== $sharedAge))) {
            $response->setPublic();
        }

        return $response;
    }

    public function __invoke(string $template, int $maxAge = null, int $sharedAge = null, bool $private = null): Response
    {
        return $this->templateAction($template, $maxAge, $sharedAge, $private);
    }
}
