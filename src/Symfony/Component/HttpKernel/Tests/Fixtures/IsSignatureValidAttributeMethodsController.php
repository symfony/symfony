<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HttpKernel\Tests\Fixtures;

use Symfony\Component\HttpKernel\Attribute\IsSignatureValid;

class IsSignatureValidAttributeMethodsController
{
    public function noAttribute()
    {
    }

    #[IsSignatureValid]
    public function withDefaultBehavior()
    {
    }

    #[IsSignatureValid]
    #[IsSignatureValid]
    public function withMultiple()
    {
    }

    #[IsSignatureValid(methods: 'POST')]
    public function withPostOnly()
    {
    }

    #[IsSignatureValid(methods: ['GET', 'POST'])]
    public function withGetAndPost()
    {
    }
}
