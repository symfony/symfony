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
use Twig\Environment;

/**
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class TwigRenderer extends FormRenderer implements TwigRendererInterface
{
    /**
     * @var TwigRendererEngineInterface
     */
    private $engine;

    public function __construct(TwigRendererEngineInterface $engine, $csrfTokenManager = null)
    {
        parent::__construct($engine, $csrfTokenManager);

        $this->engine = $engine;
    }

    /**
     * {@inheritdoc}
     */
    public function setEnvironment(Environment $environment)
    {
        $this->engine->setEnvironment($environment);
    }
}
