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

class YearChoiceList extends PaddedChoiceList
{
    public function __construct(array $years = array(), array $preferredChoices = array())
    {
        if (count($years) === 0) {
            $years = range(date('Y') - 5, date('Y') + 5);
        }

        parent::__construct($years, 4, '0', STR_PAD_LEFT, $preferredChoices);
    }
}