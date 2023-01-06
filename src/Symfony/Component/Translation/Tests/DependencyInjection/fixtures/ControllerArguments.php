<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Translation\Tests\DependencyInjection\fixtures;

use Symfony\Contracts\Translation\TranslatorInterface;

class ControllerArguments
{
    public function __invoke(TranslatorInterface $translator)
    {
    }

    public function index(TranslatorInterface $translator)
    {
    }
}
