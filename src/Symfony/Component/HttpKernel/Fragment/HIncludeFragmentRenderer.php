<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HttpKernel\Fragment;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Templating\EngineInterface;
use Symfony\Component\HttpKernel\Controller\ControllerReference;
use Symfony\Component\HttpKernel\UriSigner;

/**
 * Implements the Hinclude rendering strategy.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
class HIncludeFragmentRenderer extends RoutableFragmentRenderer
{
    protected $templating;

    private $globalDefaultTemplate;
    private $signer;

    /**
     * Constructor.
     *
     * @param EngineInterface|\Twig_Environment $templating            An EngineInterface or a \Twig_Environment instance
     * @param UriSigner                         $signer                A UriSigner instance
     * @param string                            $globalDefaultTemplate The global default content (it can be a template name or the content)
     */
    public function __construct($templating = null, UriSigner $signer = null, $globalDefaultTemplate = null)
    {
        if (null !== $templating && !$templating instanceof EngineInterface && !$templating instanceof \Twig_Environment) {
            throw new \InvalidArgumentException('The hinclude rendering strategy needs an instance of \Twig_Environment or Symfony\Component\Templating\EngineInterface');
        }

        $this->templating = $templating;
        $this->globalDefaultTemplate = $globalDefaultTemplate;
        $this->signer = $signer;
    }

    /**
     * {@inheritdoc}
     *
     * Additional available options:
     *
     *  * default: The default content (it can be a template name or the content)
     */
    public function render($uri, Request $request, array $options = array())
    {
        if ($uri instanceof ControllerReference) {
            if (null === $this->signer) {
                throw new \LogicException('You must use a proper URI when using the Hinclude rendering strategy or set a URL signer.');
            }

            $uri = $this->signer->sign($this->generateFragmentUri($uri, $request));
        }

        $template = isset($options['default']) ? $options['default'] : $this->globalDefaultTemplate;
        if (null !== $this->templating && $template && $this->templateExists($template)) {
            $content = $this->templating->render($template);
        } else {
            $content = $template;
        }

        return new Response(sprintf('<hx:include src="%s">%s</hx:include>', $uri, $content));
    }

    private function templateExists($template)
    {
        if ($this->templating instanceof EngineInterface) {
            return $this->templating->exists($template);
        }

        $loader = $this->templating->getLoader();
        if ($loader instanceof \Twig_ExistsLoaderInterface) {
            return $loader->exists($template);
        }

        try {
            $loader->getSource($template);

            return true;
        } catch (\Twig_Error_Loader $e) {
        }

        return false;
    }

    /**
     * {@inheritdoc}
     */
    public function getName()
    {
        return 'hinclude';
    }
}
