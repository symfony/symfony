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

class DayChoiceList extends PaddedChoiceList
{
    public function __construct(array $days = array(), array $preferredChoices = array())
    {
        if (count($days) === 0) {
            $days = range(1, 31);
        }

        parent::__construct($days, 2, '0', STR_PAD_LEFT, $preferredChoices);
    }
}