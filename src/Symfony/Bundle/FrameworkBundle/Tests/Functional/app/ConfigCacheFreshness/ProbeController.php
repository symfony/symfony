<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\FrameworkBundle\Tests\Functional\app\ConfigCacheFreshness;

use Symfony\Component\HttpFoundation\Response;

class ProbeController
{
    public function __construct(private string $value)
    {
    }

    public function __invoke(): Response
    {
        return new Response($this->value);
    }
}
