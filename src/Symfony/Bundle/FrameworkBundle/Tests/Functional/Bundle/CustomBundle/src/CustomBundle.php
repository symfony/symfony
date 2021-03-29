<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\FrameworkBundle\Tests\Functional\Bundle\CustomBundle\src;

use Symfony\Component\HttpKernel\Bundle\Bundle;

class CustomBundle extends Bundle
{
    public function getPath(): string
    {
        return \dirname(__DIR__);
    }

    public function getTemplatesPath()
    {
        return $this->getPath() . '/custom/templates';
    }

    public function getTranslationsPath()
    {
        return $this->getPath() . '/custom/translations';
    }
}
