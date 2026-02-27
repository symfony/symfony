<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Form\Tests\Fixtures;

use Symfony\Component\Form\ChoiceList\Loader\CallbackChoiceLoader;

class ArrayChoiceLoader extends CallbackChoiceLoader
{
    public function __construct(array $choices = [])
    {
        parent::__construct(static fn (): array => $choices);
    }
}
