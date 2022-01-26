<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Security\Http\Tests\Fixtures\Controller;

use Symfony\Component\Security\Http\Attribute\IsGranted;

class IsGrantedAttributeMethodsController
{
    public function noAttribute()
    {
    }

    #[IsGranted()]
    public function emptyAttribute()
    {
    }

    #[IsGranted(attributes: 'ROLE_ADMIN')]
    public function admin()
    {
    }

    #[IsGranted(attributes: ['ROLE_ADMIN', 'ROLE_USER'])]
    public function adminOrUser()
    {
    }

    #[IsGranted(attributes: ['ROLE_ADMIN', 'ROLE_USER'], subject: 'product')]
    public function adminOrUserWithSubject()
    {
    }

    #[IsGranted(attributes: 'ROLE_ADMIN', subject: 'arg2Name')]
    public function withSubject()
    {
    }

    #[IsGranted(attributes: 'ROLE_ADMIN', subject: ['arg1Name', 'arg2Name'])]
    public function withSubjectArray()
    {
    }

    #[IsGranted(attributes: 'ROLE_ADMIN', subject: 'non_existent')]
    public function withMissingSubject()
    {
    }

    #[IsGranted(attributes: 'ROLE_ADMIN', statusCode: 404, message: 'Not found')]
    public function notFound()
    {
    }
}
