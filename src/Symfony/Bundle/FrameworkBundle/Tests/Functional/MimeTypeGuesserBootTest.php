<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\FrameworkBundle\Tests\Functional;

use PHPUnit\Framework\Attributes\Group;
use Symfony\Bundle\FrameworkBundle\Tests\Functional\Bundle\TestBundle\MimeType\CustomMimeTypeGuesser;
use Symfony\Component\Mime\MimeTypes;

#[Group('functional')]
class MimeTypeGuesserBootTest extends AbstractWebTestCase
{
    /**
     * Verifies that booting the kernel eagerly instantiates the "mime_types" service,
     * so that MimeTypes::setDefault() fires before any code calls MimeTypes::getDefault().
     *
     * Without this, File::getMimeType() (and any other static call site) silently
     * falls back to a fresh, default-only MimeTypes instance, dropping every guesser
     * tagged "mime.mime_type_guesser" in the container.
     */
    public function testCustomGuesserIsAppliedAfterKernelBootEvenWithoutExplicitContainerLookup()
    {
        // Reset the MimeTypes static default so any prior test cannot leak its instance
        // into this one. Replace it with a fresh, default-only MimeTypes — mimicking the
        // "no DI container has touched it yet" runtime state described in the bug report.
        $defaultProperty = new \ReflectionProperty(MimeTypes::class, 'default');
        $defaultProperty->setValue(null, new MimeTypes());

        $kernel = static::createKernel(['test_case' => 'MimeType', 'root_config' => 'config.yml']);
        $kernel->boot();

        // Intentionally do NOT call $kernel->getContainer()->get('mime_types') —
        // the bug being covered is that nothing in the request lifecycle fetches
        // it, leaving MimeTypes::getDefault() pointing at a fresh, default-only
        // instance that ignores every container-tagged guesser.
        $this->assertSame(CustomMimeTypeGuesser::FAKE_MIME_TYPE, MimeTypes::getDefault()->guessMimeType(__FILE__));
    }
}
