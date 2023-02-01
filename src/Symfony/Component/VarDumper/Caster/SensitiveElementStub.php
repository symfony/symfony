<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\VarDumper\Caster;

/**
 * @author Alexandre Daubois <alex.daubois@gmail.com>
 */
class SensitiveElementStub extends ConstStub
{
    public function __construct(string $name)
    {
        parent::__construct($name.' (🔒 Sensitive element)');
    }
}
