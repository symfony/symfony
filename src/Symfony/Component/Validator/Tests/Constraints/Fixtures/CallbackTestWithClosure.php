<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Validator\Tests\Constraints\Fixtures;

use Symfony\Component\Validator\Constraints\Callback;

class CallbackTestWithClosure
{
    #[Callback(static function ($value) {
        return 'valid' === $value;
    })]
    private $a;

    #[Callback(static function ($value) {
        return 'valid' === $value;
    }, message: 'myMessage', groups: ['my_group'], payload: 'some attached data')]
    private $b;
}
