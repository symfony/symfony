<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Mime\Tests;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Mime\ExternalTemplatedEmail;

class ExternalTemplatedEmailTest extends TestCase
{
    public function testTemplateId()
    {
        $e = new ExternalTemplatedEmail();
        $e->templateId('template_id');
        $this->assertSame('template_id', $e->getTemplateId());
    }

    public function testContext()
    {
        $e = new ExternalTemplatedEmail();
        $e->context(['foo' => 'bar']);
        $this->assertSame(['foo' => 'bar'], $e->getContext());
    }

    public function testLocale()
    {
        $e = new ExternalTemplatedEmail();
        $e->locale('fr');
        $this->assertSame('fr', $e->getLocale());
    }

    public function testInvalidTemplateIdExternalTemplatedEmail()
    {
        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('The template ID is required.');

        (new ExternalTemplatedEmail())->ensureValidity();
    }
}
