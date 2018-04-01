<?php

/*
 * This file is part of the Symphony package.
 *
 * (c) Fabien Potencier <fabien@symphony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

return array(
    new Symphony\Bundle\FrameworkBundle\FrameworkBundle(),
    new Symphony\Bundle\SecurityBundle\SecurityBundle(),
    new Symphony\Bundle\TwigBundle\TwigBundle(),
    new Symphony\Bundle\SecurityBundle\Tests\Functional\Bundle\CsrfFormLoginBundle\CsrfFormLoginBundle(),
);
