<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Symfony\Bundle\FrameworkBundle\FrameworkBundle;
use Symfony\Bundle\SecurityBundle\SecurityBundle;
use Symfony\Bundle\SecurityBundle\Tests\Functional\Bundle\JsonLoginBundle\JsonLoginBundle;
use Symfony\Bundle\SecurityBundle\Tests\Functional\Bundle\TestBundle;

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

return [
    new SecurityBundle(),
    new FrameworkBundle(),
    new JsonLoginBundle(),
    new TestBundle(),
];
