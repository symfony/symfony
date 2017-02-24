<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\SecurityBundle\Tests\Functional\Bundle\JsonLoginBundle\Controller;

/**
 * @author Kévin Dunglas <dunglas@gmail.com>
 */
class TestController
{
    public function loginCheckAction()
    {
        throw new \RuntimeException(sprintf('%s should never be called.', __FUNCTION__));
    }
}
