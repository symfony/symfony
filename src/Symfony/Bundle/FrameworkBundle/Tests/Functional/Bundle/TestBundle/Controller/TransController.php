<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\FrameworkBundle\Tests\Functional\Bundle\TestBundle\Controller;

use Symfony\Contracts\Translation\TranslatorInterface;

class TransController
{
    public function index(TranslatorInterface $translator)
    {
        $translator->trans('hello_from_controller');
    }
}
