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
use Symfony\Component\Mime\DraftEmail;
use Symfony\Component\Mime\Exception\LogicException;

/**
 * @author Kevin Bond <kevinbond@gmail.com>
 */
final class DraftEmailTest extends TestCase
{
    public function testCanHaveJustBody()
    {
        $email = (new DraftEmail())->text('some text')->toString();

        $this->assertStringContainsString('some text', $email);
        $this->assertStringContainsString('MIME-Version: 1.0', $email);
        $this->assertStringContainsString('X-Unsent: 1', $email);
    }

    public function testBccIsRemoved()
    {
        $email = (new DraftEmail())->text('some text')->bcc('sam@example.com')->toString();

        $this->assertStringNotContainsString('sam@example.com', $email);
    }

    public function testMustHaveBody()
    {
        $this->expectException(LogicException::class);

        (new DraftEmail())->toString();
    }

    public function testEnsureValidityAlwaysFails()
    {
        $email = (new DraftEmail())
            ->to('alice@example.com')
            ->from('webmaster@example.com')
            ->text('some text')
        ;

        $this->expectException(LogicException::class);

        $email->ensureValidity();
    }
}
