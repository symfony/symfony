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

class MinuteChoiceList extends PaddedChoiceList
{
    public function __construct(array $minutes = array(), array $preferredChoices = array())
    {
        if (count($minutes) === 0) {
            $minutes = range(0, 59);
        }

        parent::__construct($minutes, 2, '0', STR_PAD_LEFT, $preferredChoices);
    }
}