<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Console\Tests\Fixtures\Helper;

use Symfony\Component\Console\Helper\InputAwareHelper;

class SomeInputAwareHelper extends InputAwareHelper
{
    /**
     * {@inheritdoc}
     */
    public function getName()
    {
        return 'some input aware helper';
    }
}
