<?php

/*
 * This file is part of the Symphony package.
 *
 * (c) Fabien Potencier <fabien@symphony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symphony\Component\HttpKernel\Fragment;

/**
 * Implements the ESI rendering strategy.
 *
 * @author Fabien Potencier <fabien@symphony.com>
 */
class EsiFragmentRenderer extends AbstractSurrogateFragmentRenderer
{
    /**
     * {@inheritdoc}
     */
    public function getName()
    {
        return 'esi';
    }
}
