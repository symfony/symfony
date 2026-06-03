<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Serializer\Tests\Normalizer;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Serializer\Normalizer\TranslatableNormalizer;
use Symfony\Contracts\Translation\TranslatableInterface;
use Symfony\Contracts\Translation\TranslatorInterface;
use Symfony\Contracts\Translation\TranslatorTrait;

class TranslatableNormalizerTest extends TestCase
{
    private readonly TranslatableNormalizer $normalizer;

    protected function setUp(): void
    {
        $this->normalizer = new TranslatableNormalizer(new IdentityTranslator());
    }

    public function testSupportsNormalization()
    {
        $this->assertTrue($this->normalizer->supportsNormalization(new TestMessage()));
        $this->assertFalse($this->normalizer->supportsNormalization(new \stdClass()));
    }

    public function testNormalize()
    {
        $message = new TestMessage();

        $this->assertSame('key_null', $this->normalizer->normalize($message));
        $this->assertSame('key_fr', $this->normalizer->normalize($message, context: ['translatable_normalization_locale' => 'fr']));
        $this->assertSame('key_en', $this->normalizer->normalize($message, context: ['translatable_normalization_locale' => 'en']));
    }

    public function testNormalizeWithNormalizationLocalePassedInConstructor()
    {
        $normalizer = new TranslatableNormalizer(
            new IdentityTranslator(),
            ['translatable_normalization_locale' => 'es'],
        );
        $message = new TestMessage();

        $this->assertSame('key_es', $normalizer->normalize($message));
        $this->assertSame('key_fr', $normalizer->normalize($message, context: ['translatable_normalization_locale' => 'fr']));
        $this->assertSame('key_en', $normalizer->normalize($message, context: ['translatable_normalization_locale' => 'en']));
    }
}

class TestMessage implements TranslatableInterface
{
    public function trans(TranslatorInterface $translator, ?string $locale = null): string
    {
        return 'key_'.($locale ?? 'null');
    }
}

class IdentityTranslator implements TranslatorInterface
{
    use TranslatorTrait;
}
