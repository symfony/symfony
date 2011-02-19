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

class HourChoiceList extends PaddedChoiceList
{
    public function __construct(array $hours = array(), array $preferredChoices = array())
    {
        if (count($hours) === 0) {
            $hours = range(0, 23);
        }

        parent::__construct($hours, 2, '0', STR_PAD_LEFT, $preferredChoices);
    }
}