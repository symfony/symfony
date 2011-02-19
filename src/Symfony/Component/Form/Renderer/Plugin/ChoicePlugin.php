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

use Symfony\Component\Form\Renderer\RendererInterface;
use Symfony\Component\Form\ChoiceList\ChoiceListInterface;

class ChoicePlugin implements PluginInterface
{
    private $choiceList;

    public function __construct(ChoiceListInterface $choiceList)
    {
        $this->choiceList = $choiceList;
    }

    public function setUp(RendererInterface $renderer)
    {
        $choiceList = $this->choiceList;

        $renderer->setParameter('choices', $this->choiceList->getOtherChoices());
        $renderer->setParameter('preferred_choices', $this->choiceList->getPreferredChoices());
        $renderer->setParameter('separator', '-------------------');
        $renderer->setParameter('choice_list', $this->choiceList);
        $renderer->setParameter('empty_value', '');
    }
}