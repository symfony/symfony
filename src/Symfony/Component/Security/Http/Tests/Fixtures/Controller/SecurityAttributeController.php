<?php

namespace Symfony\Component\Security\Http\Tests\Fixtures\Controller;

use Symfony\Component\Security\Http\Attribute\Security;

#[Security(expression: 'is_granted("ROLE_ADMIN") or is_granted("FOO")')]
class SecurityAttributeController
{
    public function accessDenied()
    {
    }

    #[Security(expression: 'is_granted("ROLE_ADMIN") or is_granted("FOO")', statusCode: 404, message: 'Not found')]
    public function notFound()
    {
    }
}
