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

use Symfony\Component\Form\ChoiceList\View\ChoiceView;
use Symfony\Component\Form\FormRenderer;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;

/**
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class TwigRenderer extends FormRenderer implements TwigRendererInterface
{
    /**
     * @var TwigRendererEngineInterface
     */
    private $engine;

    public function __construct(TwigRendererEngineInterface $engine, CsrfTokenManagerInterface $csrfTokenManager = null)
    {
        parent::__construct($engine, $csrfTokenManager);

        $this->engine = $engine;
    }

    /**
     * {@inheritdoc}
     */
    public function setEnvironment(\Twig_Environment $environment)
    {
        $this->engine->setEnvironment($environment);
    }

    /**
     * Returns whether a choice is selected for a given form value.
     *
     * Unfortunately Twig does not support an efficient way to execute the
     * "is_selected" closure passed to the template by ChoiceType. It is faster
     * to implement the logic here (around 65ms for a specific form).
     *
     * Directly implementing the logic here is also faster than doing so in
     * ChoiceView (around 30ms).
     *
     * The worst option tested so far is to implement the logic in ChoiceView
     * and access the ChoiceView method directly in the template. Doing so is
     * around 220ms slower than doing the method call here in the filter. Twig
     * seems to be much more efficient at executing filters than at executing
     * methods of an object.
     *
     * @param ChoiceView   $choice        The choice to check
     * @param string|array $selectedValue The selected value to compare
     *
     * @return bool Whether the choice is selected
     *
     * @see ChoiceView::isSelected()
     */
    public function isSelectedChoice(ChoiceView $choice, $selectedValue)
    {
        if (is_array($selectedValue)) {
            return in_array($choice->value, $selectedValue, true);
        }

        return $choice->value === $selectedValue;
    }
}
