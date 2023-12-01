<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Form\ChoiceList\View;

use Symfony\Contracts\Translation\TranslatableInterface;

/**
 * Represents a choice in templates.
 *
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class ChoiceView
{
    public string|TranslatableInterface|false $label;
    public string $value;
    public mixed $data;

    /**
     * Additional attributes for the HTML tag.
     */
    public array $attr;

    /**
     * Additional parameters used to translate the label.
     */
    public array $labelTranslationParameters;

    /**
     * Creates a new choice view.
     *
     * @param mixed                              $data                       The original choice
     * @param string                             $value                      The view representation of the choice
     * @param string|TranslatableInterface|false $label                      The label displayed to humans; pass false to discard the label
     * @param array                              $attr                       Additional attributes for the HTML tag
     * @param array                              $labelTranslationParameters Additional parameters used to translate the label
     */
    public function __construct(mixed $data, string $value, string|TranslatableInterface|false $label, array $attr = [], array $labelTranslationParameters = [])
    {
        $this->data = $data;
        $this->value = $value;
        $this->label = $label;
        $this->attr = $attr;
        $this->labelTranslationParameters = $labelTranslationParameters;
    }
}
