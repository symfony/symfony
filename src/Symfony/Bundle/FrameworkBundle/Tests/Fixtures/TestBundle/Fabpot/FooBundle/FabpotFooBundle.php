<?php

namespace TestBundle\Fabpot\FooBundle;

use Symfony\Component\HttpKernel\Bundle\Bundle;

/*
 * This file is part of the Symfony framework.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

/**
 * Bundle.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
class FabpotFooBundle extends Bundle
{
    /**
     * {@inheritdoc}
     */
    public function getParent()
    {
        return 'SensioFooBundle';
    }
}
