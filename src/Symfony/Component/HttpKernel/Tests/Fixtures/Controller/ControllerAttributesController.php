<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HttpKernel\Tests\Fixtures\Controller;

use Symfony\Component\HttpKernel\Tests\Fixtures\Attribute\Buz;
use Symfony\Component\HttpKernel\Tests\Fixtures\Attribute\Qux;
use Symfony\Component\HttpKernel\Tests\Fixtures\Attribute\SubBuz;

#[Buz]
class ControllerAttributesController
{
    #[Buz]
    #[Qux]
    public function buzQuxAction()
    {
    }

    #[Buz]
    public function buzAction()
    {
    }

    #[SubBuz]
    public function subBuzAction()
    {
    }

    public function noAttributeAction()
    {
    }
}
