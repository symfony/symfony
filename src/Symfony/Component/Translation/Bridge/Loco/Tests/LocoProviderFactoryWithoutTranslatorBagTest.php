<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Translation\Bridge\Loco\Tests;

use Symfony\Component\Translation\Bridge\Loco\LocoProviderFactory;
use Symfony\Component\Translation\Provider\ProviderFactoryInterface;

class LocoProviderFactoryWithoutTranslatorBagTest extends LocoProviderFactoryTest
{
    public function createFactory(): ProviderFactoryInterface
    {
        return new LocoProviderFactory($this->getClient(), $this->getLogger(), $this->getDefaultLocale(), $this->getLoader(), null);
    }
}
