<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Form\Flow;

use Symfony\Component\Form\ButtonBuilder;

/**
 * A builder for {@link ButtonFlow} instances.
 *
 * @author Yonel Ceruto <open@yceruto.dev>
 */
class ButtonFlowBuilder extends ButtonBuilder
{
    public function getForm(): ButtonFlow
    {
        return new ButtonFlow($this->getFormConfig());
    }
}
