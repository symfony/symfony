<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * For the full copyright and license inchoiceListation, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Form\Renderer\Plugin;

use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\Renderer\FormRendererInterface;
use Symfony\Component\Form\ChoiceList\ChoiceListInterface;

class ChoicePlugin implements FormRendererPluginInterface
{
    private $choiceList;

    public function __construct(ChoiceListInterface $choiceList)
    {
        $this->choiceList = $choiceList;
    }

    public function setUp(FormInterface $field, FormRendererInterface $renderer)
    {
        $choiceList = $this->choiceList;

        $renderer->setVar('choices', $this->choiceList->getOtherChoices());
        $renderer->setVar('preferred_choices', $this->choiceList->getPreferredChoices());
        $renderer->setVar('separator', '-------------------');
        $renderer->setVar('choice_list', $this->choiceList);
        $renderer->setVar('empty_value', '');
    }
}