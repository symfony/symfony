<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HtmlSanitizer\Tests;

use PHPUnit\Framework\TestCase;
use Symfony\Component\HtmlSanitizer\HtmlSanitizerConfig;
use Symfony\Component\HtmlSanitizer\Visitor\AttributeSanitizer\AttributeSanitizerInterface;

class HtmlSanitizerConfigTest extends TestCase
{
    public function testCreateEmpty()
    {
        $config = new HtmlSanitizerConfig();
        $this->assertSame([], $config->getAllowedElements());
        $this->assertSame([], $config->getBlockedElements());
        $this->assertSame(['http', 'https', 'mailto', 'tel'], $config->getAllowedLinkSchemes());
        $this->assertNull($config->getAllowedLinkHosts());
        $this->assertSame(['http', 'https', 'data'], $config->getAllowedMediaSchemes());
        $this->assertNull($config->getAllowedMediaHosts());
        $this->assertFalse($config->getForceHttpsUrls());
    }

    public function testSimpleOptions()
    {
        $config = new HtmlSanitizerConfig();
        $this->assertSame(['http', 'https', 'mailto', 'tel'], $config->getAllowedLinkSchemes());
        $this->assertNull($config->getAllowedLinkHosts());
        $this->assertSame(['http', 'https', 'data'], $config->getAllowedMediaSchemes());
        $this->assertNull($config->getAllowedMediaHosts());
        $this->assertFalse($config->getForceHttpsUrls());

        $config = $config->allowLinkSchemes(['http', 'ftp']);
        $this->assertSame(['http', 'ftp'], $config->getAllowedLinkSchemes());

        $config = $config->allowLinkHosts(['symfony.com', 'example.com']);
        $this->assertSame(['symfony.com', 'example.com'], $config->getAllowedLinkHosts());

        $config = $config->allowRelativeLinks();
        $this->assertTrue($config->getAllowRelativeLinks());

        $config = $config->allowMediaSchemes(['https']);
        $this->assertSame(['https'], $config->getAllowedMediaSchemes());

        $config = $config->allowMediaHosts(['symfony.com']);
        $this->assertSame(['symfony.com'], $config->getAllowedMediaHosts());

        $config = $config->allowRelativeMedias();
        $this->assertTrue($config->getAllowRelativeMedias());

        $config = $config->forceHttpsUrls();
        $this->assertTrue($config->getForceHttpsUrls());
    }

    public function testAllowElement()
    {
        $config = new HtmlSanitizerConfig();
        $config = $config->allowElement('div', ['style']);
        $this->assertSame(['div' => ['style' => true]], $config->getAllowedElements());
        $this->assertSame([], $config->getBlockedElements());
    }

    public function testAllowElementTwiceOverridesIt()
    {
        $config = new HtmlSanitizerConfig();
        $config = $config->allowElement('div', ['style']);
        $config = $config->allowElement('div', ['width']);
        $this->assertSame(['div' => ['width' => true]], $config->getAllowedElements());
        $this->assertSame([], $config->getBlockedElements());

        // Allowing a blocked element should remove it from blocked
        $config = $config->blockElement('div');
        $this->assertSame(['div' => true], $config->getBlockedElements());

        $config = $config->allowElement('div', ['width']);
        $this->assertSame(['div' => ['width' => true]], $config->getAllowedElements());
        $this->assertSame([], $config->getBlockedElements());
    }

    public function testAllowBlockedElementUnblocksIt()
    {
        $config = new HtmlSanitizerConfig();
        $config = $config->blockElement('div');
        $this->assertSame(['div' => true], $config->getBlockedElements());

        $config = $config->allowElement('div', ['width']);
        $this->assertSame(['div' => ['width' => true]], $config->getAllowedElements());
        $this->assertSame([], $config->getBlockedElements());
    }

    public function testAllowElementNoAttributes()
    {
        $config = new HtmlSanitizerConfig();
        $config = $config->allowElement('div', []);
        $this->assertSame(['div' => []], $config->getAllowedElements());
        $this->assertSame([], $config->getBlockedElements());
    }

    public function testAllowElementStandardAttributes()
    {
        $config = new HtmlSanitizerConfig();
        $config = $config->allowElement('div', '*');
        $this->assertSame(['div'], array_keys($config->getAllowedElements()));
        $this->assertCount(211, $config->getAllowedElements()['div']);
        $this->assertSame([], $config->getBlockedElements());
    }

    public function testAllowElementStringAttribute()
    {
        $config = new HtmlSanitizerConfig();
        $config = $config->allowElement('div', 'width');
        $this->assertSame(['div' => ['width' => true]], $config->getAllowedElements());
        $this->assertSame([], $config->getBlockedElements());
    }

    public function testBlockElement()
    {
        $config = new HtmlSanitizerConfig();
        $config = $config->blockElement('div');
        $this->assertSame(['div' => true], $config->getBlockedElements());
    }

    public function testBlockElementDisallowsIt()
    {
        $config = new HtmlSanitizerConfig();
        $config = $config->allowElement('div', 'width');
        $this->assertSame(['div' => ['width' => true]], $config->getAllowedElements());
        $this->assertSame([], $config->getBlockedElements());

        $config = $config->blockElement('div');
        $this->assertSame([], $config->getAllowedElements());
        $this->assertSame(['div' => true], $config->getBlockedElements());
    }

    public function testDropAllowedElement()
    {
        $config = new HtmlSanitizerConfig();
        $config = $config->allowElement('div', 'width');
        $this->assertSame(['div' => ['width' => true]], $config->getAllowedElements());
        $this->assertSame([], $config->getBlockedElements());

        $config = $config->dropElement('div');
        $this->assertSame([], $config->getAllowedElements());
        $this->assertSame([], $config->getBlockedElements());
    }

    public function testDropBlockedElement()
    {
        $config = new HtmlSanitizerConfig();
        $config = $config->blockElement('div');
        $this->assertSame([], $config->getAllowedElements());
        $this->assertSame(['div' => true], $config->getBlockedElements());

        $config = $config->dropElement('div');
        $this->assertSame([], $config->getAllowedElements());
        $this->assertSame([], $config->getBlockedElements());
    }

    public function testAllowAttributeNoElement()
    {
        $config = new HtmlSanitizerConfig();
        $config = $config->allowAttribute('width', 'div');
        $this->assertSame([], $config->getAllowedElements());
        $this->assertSame([], $config->getBlockedElements());
    }

    public function testAllowAttributeAllowedElement()
    {
        $config = new HtmlSanitizerConfig();
        $config = $config->allowElement('div');
        $config = $config->allowAttribute('width', 'div');
        $this->assertSame(['div' => ['width' => true]], $config->getAllowedElements());
        $this->assertSame([], $config->getBlockedElements());
    }

    public function testAllowAttributeAllElements()
    {
        $config = new HtmlSanitizerConfig();
        $config = $config->allowElement('div');
        $config = $config->allowElement('section');
        $config = $config->allowAttribute('width', '*');
        $this->assertSame(['div' => ['width' => true], 'section' => ['width' => true]], $config->getAllowedElements());
        $this->assertSame([], $config->getBlockedElements());
    }

    public function testAllowAttributeElementsArray()
    {
        $config = new HtmlSanitizerConfig();
        $config = $config->allowElement('div');
        $config = $config->allowElement('section');
        $config = $config->allowAttribute('width', ['section']);
        $this->assertSame(['div' => [], 'section' => ['width' => true]], $config->getAllowedElements());
        $this->assertSame([], $config->getBlockedElements());
    }

    public function testAllowAttributeElementsString()
    {
        $config = new HtmlSanitizerConfig();
        $config = $config->allowElement('div');
        $config = $config->allowElement('section');
        $config = $config->allowAttribute('width', 'section');
        $this->assertSame(['div' => [], 'section' => ['width' => true]], $config->getAllowedElements());
        $this->assertSame([], $config->getBlockedElements());
    }

    public function testAllowAttributeOverridesIt()
    {
        $config = new HtmlSanitizerConfig();
        $config = $config->allowElement('div');
        $config = $config->allowElement('section');

        $config = $config->allowAttribute('width', 'div');
        $this->assertSame(['div' => ['width' => true], 'section' => []], $config->getAllowedElements());
        $this->assertSame([], $config->getBlockedElements());

        $config = $config->allowAttribute('width', 'section');
        $this->assertSame(['div' => [], 'section' => ['width' => true]], $config->getAllowedElements());
        $this->assertSame([], $config->getBlockedElements());
    }

    public function testDropAllowedAttributeAllowedElementsArray()
    {
        $config = new HtmlSanitizerConfig();
        $config = $config->allowElement('div', 'width');
        $config = $config->allowElement('section', 'width');
        $this->assertSame(['div' => ['width' => true], 'section' => ['width' => true]], $config->getAllowedElements());
        $this->assertSame([], $config->getBlockedElements());

        $config = $config->dropAttribute('width', ['div']);
        $this->assertSame(['div' => [], 'section' => ['width' => true]], $config->getAllowedElements());
        $this->assertSame([], $config->getBlockedElements());
    }

    public function testDropAllowedAttributeAllowedElementString()
    {
        $config = new HtmlSanitizerConfig();
        $config = $config->allowElement('div', 'width');
        $config = $config->allowElement('section', 'width');
        $this->assertSame(['div' => ['width' => true], 'section' => ['width' => true]], $config->getAllowedElements());
        $this->assertSame([], $config->getBlockedElements());

        $config = $config->dropAttribute('width', 'section');
        $this->assertSame(['div' => ['width' => true], 'section' => []], $config->getAllowedElements());
        $this->assertSame([], $config->getBlockedElements());
    }

    public function testDropAllowedAttributeAllElements()
    {
        $config = new HtmlSanitizerConfig();
        $config = $config->allowElement('div', 'width');
        $config = $config->allowElement('section', 'width');
        $this->assertSame(['div' => ['width' => true], 'section' => ['width' => true]], $config->getAllowedElements());
        $this->assertSame([], $config->getBlockedElements());

        $config = $config->dropAttribute('width', '*');
        $this->assertSame(['div' => [], 'section' => []], $config->getAllowedElements());
        $this->assertSame([], $config->getBlockedElements());
    }

    public function testWithWithoutAttributeSanitizer()
    {
        $config = new HtmlSanitizerConfig();

        $sanitizer = new class() implements AttributeSanitizerInterface {
            public function getSupportedElements(): ?array
            {
                return null;
            }

            public function getSupportedAttributes(): ?array
            {
                return null;
            }

            public function sanitizeAttribute(string $element, string $attribute, string $value, HtmlSanitizerConfig $config): ?string
            {
                return '';
            }
        };

        $config = $config->withAttributeSanitizer($sanitizer);
        $this->assertContains($sanitizer, $config->getAttributeSanitizers());

        $config = $config->withoutAttributeSanitizer($sanitizer);
        $this->assertNotContains($sanitizer, $config->getAttributeSanitizers());
    }
}
