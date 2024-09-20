<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Translation\Tests\DependencyInjection\Fixtures;

use Symfony\Contracts\Translation\TranslatorInterface;

class ServiceMethodCalls
{
    public function setTranslator(TranslatorInterface $translator)
    {
    }
}
