<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\WebProfilerBundle\Controller;

use Symfony\Component\ErrorRenderer\ErrorRenderer\HtmlErrorRenderer;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Debug\FileLinkFormatter;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Profiler\Profiler;
use Twig\Environment;

@trigger_error(sprintf('The "%s" class is deprecated since Symfony 4.4, use "%s" instead.', ExceptionController::class, ExceptionErrorController::class), E_USER_DEPRECATED);

/**
 * ExceptionController.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 *
 * @deprecated since Symfony 4.4, use the ExceptionErrorController instead.
 * @internal since Symfony 4.4
 */
class ExceptionController
{
    protected $twig;
    protected $debug;
    protected $profiler;
    private $errorRenderer;

    public function __construct(Profiler $profiler = null, Environment $twig, bool $debug, FileLinkFormatter $fileLinkFormat = null, HtmlErrorRenderer $errorRenderer = null)
    {
        $this->profiler = $profiler;
        $this->twig = $twig;
        $this->debug = $debug;
        $this->errorRenderer = $errorRenderer;

        if (null === $errorRenderer) {
            $this->errorRenderer = new HtmlErrorRenderer($debug, $this->twig->getCharset(), $fileLinkFormat);
        }
    }

    /**
     * Renders the exception panel for the given token.
     *
     * @return Response A Response instance
     *
     * @throws NotFoundHttpException
     */
    public function showAction(string $token)
    {
        if (null === $this->profiler) {
            throw new NotFoundHttpException('The profiler must be enabled.');
        }

        $this->profiler->disable();

        $exception = $this->profiler->loadProfile($token)->getCollector('exception')->getException();

        return new Response($this->errorRenderer->getBody($exception), 200, ['Content-Type' => 'text/html']);
    }

    /**
     * Renders the exception panel stylesheet for the given token.
     *
     * @return Response A Response instance
     *
     * @throws NotFoundHttpException
     */
    public function cssAction(string $token)
    {
        if (null === $this->profiler) {
            throw new NotFoundHttpException('The profiler must be enabled.');
        }

        $this->profiler->disable();

        return new Response($this->errorRenderer->getStylesheet(), 200, ['Content-Type' => 'text/css']);
    }
}
