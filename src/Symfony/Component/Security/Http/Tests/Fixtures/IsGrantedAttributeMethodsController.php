<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Security\Http\Tests\Fixtures;

use Symfony\Component\Security\Http\Attribute\IsGranted;

class IsGrantedAttributeMethodsController
{
    public function noAttribute()
    {
    }

    #[IsGranted(attribute: 'ROLE_ADMIN')]
    public function admin()
    {
    }

    #[IsGranted(attribute: 'ROLE_ADMIN', subject: 'arg2Name')]
    public function withSubject($arg1Name, $arg2Name)
    {
    }

    #[IsGranted(attribute: 'ROLE_ADMIN', subject: ['arg1Name', 'arg2Name'])]
    public function withSubjectArray($arg1Name, $arg2Name)
    {
    }

    #[IsGranted(attribute: 'ROLE_ADMIN', subject: 'non_existent')]
    public function withMissingSubject()
    {
    }

    #[IsGranted(attribute: 'ROLE_ADMIN', message: 'Not found', statusCode: 404)]
    public function notFound()
    {
    }
}
