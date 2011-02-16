<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Form\ChoiceList;

class SecondChoiceList extends PaddedChoiceList
{
    public function __construct(array $seconds = array(), array $preferredChoices = array(), $emptyValue = '', $required = false)
    {
        if (count($seconds) === 0) {
            $seconds = range(0, 59);
        }

        parent::__construct($seconds, 2, '0', STR_PAD_LEFT, $preferredChoices, $emptyValue, $required);
    }
}