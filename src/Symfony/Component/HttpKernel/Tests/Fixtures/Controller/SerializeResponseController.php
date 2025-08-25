<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HttpKernel\Tests\Fixtures\Controller;

use Symfony\Component\HttpKernel\Attribute\SerializeResponse;

class SerializeResponseController
{
    #[SerializeResponse(201, ['groups' => ['api']], 'json', ['X-Custom' => 'value'])]
    public function createWithCustomSettings()
    {
    }

    #[SerializeResponse]
    public function createWithDefaults()
    {
    }

    #[SerializeResponse(serializationContext: ['ignored_attributes' => ['private']])]
    public function createWithSerializationContext()
    {
    }

    #[SerializeResponse(format: 'xml')]
    public function createWithXmlFormat()
    {
    }

    #[SerializeResponse(202, format: 'yaml')]
    public function createWithYamlFormat()
    {
    }

    #[SerializeResponse(format: 'csv')]
    public function createWithCsvFormat()
    {
    }

    #[SerializeResponse(headers: ['Content-Type' => 'application/vnd.api+json'])]
    public function createWithCustomContentType()
    {
    }

    public function createWithoutAttribute()
    {
    }
}
