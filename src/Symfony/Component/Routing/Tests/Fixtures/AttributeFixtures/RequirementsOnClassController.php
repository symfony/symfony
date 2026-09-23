<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Routing\Tests\Fixtures\AttributeFixtures;

use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;

#[Route('/api/v1/applications', name: 'api_v1_applications_', requirements: ['id' => Requirement::DIGITS])]
class RequirementsOnClassController
{
    #[Route('', name: 'create', methods: 'POST')]
    public function create()
    {
    }

    #[Route('/{id}', name: 'read', methods: 'GET')]
    public function read()
    {
    }
}
