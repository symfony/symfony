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
    /**
     * Creates a new choice view.
     *
     * @param mixed                              $data                       The original choice
     * @param string                             $value                      The view representation of the choice
     * @param string|TranslatableInterface|false $label                      The label displayed to humans; pass false to discard the label
     * @param array                              $attr                       Additional attributes for the HTML tag
     * @param array                              $labelTranslationParameters Additional parameters used to translate the label
     */
    public function __construct(
        public mixed $data,
        public string $value,
        public string|TranslatableInterface|false $label,
        public array $attr = [],
        public array $labelTranslationParameters = [],
    ) {
    }
}
