<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bridge\Twig\Form;

use Symfony\Component\Form\FormRenderer;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;
use Twig\Environment;

@trigger_error(sprintf('The %s class is deprecated since Symfony 3.4 and will be removed in 4.0. Use %s instead.', TwigRenderer::class, FormRenderer::class), E_USER_DEPRECATED);

/**
 * @author Bernhard Schussek <bschussek@gmail.com>
 *
 * @deprecated since version 3.4, to be removed in 4.0. Use Symfony\Component\Form\FormRenderer instead.
 */
class TwigRenderer extends FormRenderer implements TwigRendererInterface
{
    public function __construct(TwigRendererEngineInterface $engine, CsrfTokenManagerInterface $csrfTokenManager = null)
    {
        parent::__construct($engine, $csrfTokenManager);
    }

    /**
     * Returns the engine used by this renderer.
     *
     * @return TwigRendererEngineInterface The renderer engine
     */
    public function getEngine()
    {
        return parent::getEngine();
    }

    /**
     * {@inheritdoc}
     */
    public function setEnvironment(Environment $environment)
    {
        $this->getEngine()->setEnvironment($environment);
    }
}
