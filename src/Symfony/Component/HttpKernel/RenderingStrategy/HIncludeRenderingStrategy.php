<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HttpKernel\RenderingStrategy;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Templating\EngineInterface;
use Symfony\Component\HttpKernel\Controller\ControllerReference;
use Symfony\Component\HttpKernel\UriSigner;

/**
 * Implements the Hinclude rendering strategy.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
class HIncludeRenderingStrategy extends GeneratorAwareRenderingStrategy
{
    private $templating;
    private $globalDefaultTemplate;
    private $signer;

    /**
     * Constructor.
     *
     * @param EngineInterface|\Twig_Environment $templating            An EngineInterface or a \Twig_Environment instance
     * @param UriSigner                         $signer                A UriSigner instance
     * @param string                            $globalDefaultTemplate The content of the global default template
     */
    public function __construct($templating, UriSigner $signer = null, $globalDefaultTemplate = null)
    {
        if (!$templating instanceof EngineInterface && !$templating instanceof \Twig_Environment) {
            throw new \InvalidArgumentException('The hinclude rendering strategy needs an instance of \Twig_Environment or Symfony\Component\Templating\EngineInterface');
        }

        $this->templating = $templating;
        $this->globalDefaultTemplate = $globalDefaultTemplate;
        $this->signer = $signer;
    }

    /**
     * {@inheritdoc}
     */
    public function render($uri, Request $request = null, array $options = array())
    {
        if ($uri instanceof ControllerReference) {
            if (null === $this->signer) {
                throw new \LogicException('You must use a proper URI when using the Hinclude rendering strategy or set a URL signer.');
            }

            $uri = $this->signer->sign($this->generateProxyUri($uri, $request));
        }

        $defaultTemplate = isset($options['default']) ? $options['default'] : null;
        $defaultContent = null;

        if (null !== $defaultTemplate) {
            if ($this->templateExists($defaultTemplate)) {
                $defaultContent = $this->templating->render($defaultContent);
            } else {
                $defaultContent = $defaultTemplate;
            }
        } elseif ($this->globalDefaultTemplate) {
            $defaultContent = $this->templating->render($this->globalDefaultTemplate);
        }

        return $this->renderHIncludeTag($uri, $defaultContent);
    }

    /**
     * Renders an HInclude tag.
     *
     * @param string $uri            A URI
     * @param string $defaultContent Default content
     */
    protected function renderHIncludeTag($uri, $defaultContent = null)
    {
        return sprintf('<hx:include src="%s">%s</hx:include>', $uri, $defaultContent);
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
