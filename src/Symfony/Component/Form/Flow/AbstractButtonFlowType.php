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

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Flow\Type\ButtonFlowType;

/**
 * @author Yonel Ceruto <open@yceruto.dev>
 */
abstract class AbstractButtonFlowType extends AbstractType implements ButtonFlowTypeInterface
{
    public function getParent(): string
    {
        return ButtonFlowType::class;
    }
}
