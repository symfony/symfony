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

/**
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
class HIncludeRenderingStrategy implements RenderingStrategyInterface
{
    private $templating;
    private $globalDefaultTemplate;

    public function __construct($templating, $globalDefaultTemplate = null)
    {
        if (!$templating instanceof EngineInterface && !$templating instanceof \Twig_Environment) {
            throw new \InvalidArgumentException('The hinclude rendering strategy needs an instance of \Twig_Environment or Symfony\Component\Templating\EngineInterface');
        }

        $this->templating = $templating;
        $this->globalDefaultTemplate = $globalDefaultTemplate;
    }

    public function render($uri, Request $request = null, array $options = array())
    {
        if ($uri instanceof ControllerReference) {
            // FIXME: can we sign the proxy URL instead?
            throw new \LogicException('You must use a proper URI when using the Hinclude rendering strategy.');
        }

        $defaultTemplate = $options['default'] ?: null;
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

    public function getName()
    {
        return 'hinclude';
    }
}
