<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Form\ChoiceList\Loader;

/**
 * @author Yonel Ceruto <yonelceruto@gmail.com>
 */
interface ChoiceFilterInterface
{
    /**
     * @param callable $choiceFilter The callable returning a filtered array of choices
     */
    public function setChoiceFilter(callable $choiceFilter);
}
