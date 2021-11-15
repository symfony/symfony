<?php

namespace Symfony\Component\Serializer\Tests\Normalizer;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Serializer\Normalizer\TranslatableNormalizer;
use Symfony\Component\Translation\Loader\ArrayLoader;
use Symfony\Component\Translation\TranslatableMessage;
use Symfony\Component\Translation\Translator;
use Symfony\Contracts\Translation\TranslatableInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

class TranslatableNormalizerTest extends TestCase
{
    public function testNormalize()
    {
        $enResource = [
            'foo' => 'Hello rambo',
            'bar' => 'Hello rambo: %masterpiece%',
        ];

        $cnResource = [
            'foo' => '你好兰博',
            'bar' => '你好兰博：%masterpiece%',
        ];

        $translator = new Translator('en');
        $translator->addLoader('array', new ArrayLoader());
        $translator->addResource('array', $enResource, 'en');
        $translator->addResource('array', $cnResource, 'zh_CN');

        $enNormalizer = new TranslatableNormalizer([], $translator);
        static::assertSame('Hello rambo', $enNormalizer->normalize(new TranslatableMessage('foo')));
        static::assertSame('Hello rambo', $enNormalizer->normalize(new TestTranslatableMessage('foo')));
        static::assertSame('Hello rambo: First Blood', $enNormalizer->normalize(new TranslatableMessage('bar', ['%masterpiece%' => 'First Blood'])));
        static::assertSame('Hello rambo: First Blood', $enNormalizer->normalize(new TestTranslatableMessage('bar', ['%masterpiece%' => 'First Blood'])));

        $cnNormalizer = new TranslatableNormalizer([TranslatableNormalizer::LOCALE_KEY => 'zh_CN'], $translator);
        static::assertSame('你好兰博', $cnNormalizer->normalize(new TranslatableMessage('foo')));
        static::assertSame('你好兰博', $cnNormalizer->normalize(new TestTranslatableMessage('foo')));
        static::assertSame('你好兰博：第一滴血', $cnNormalizer->normalize(new TranslatableMessage('bar', ['%masterpiece%' => '第一滴血'])));
        static::assertSame('你好兰博：第一滴血', $cnNormalizer->normalize(new TestTranslatableMessage('bar', ['%masterpiece%' => '第一滴血'])));
    }

    public function testSupportsNormalization()
    {
        $normalizer = new TranslatableNormalizer([], new Translator('en'));

        static::assertTrue($normalizer->supportsNormalization(new TranslatableMessage('foo')));
        static::assertTrue($normalizer->supportsNormalization(new TestTranslatableMessage('foo')));
    }

    public function testSupportsNormalizationWithoutTranslator()
    {
        $normalizer = new TranslatableNormalizer();

        static::assertFalse($normalizer->supportsNormalization(null));
        static::assertFalse($normalizer->supportsNormalization(new \stdClass()));
        static::assertFalse($normalizer->supportsNormalization(new TranslatableMessage('foo')));
        static::assertFalse($normalizer->supportsNormalization(new TestTranslatableMessage('foo')));
    }
}

class TestTranslatableMessage implements TranslatableInterface
{
    private $message;
    private $parameters;
    private $domain;

    public function __construct(string $message, array $parameters = [], string $domain = null)
    {
        $this->message = $message;
        $this->parameters = $parameters;
        $this->domain = $domain;
    }

    public function trans(TranslatorInterface $translator, string $locale = null): string
    {
        return $translator->trans($this->message, $this->parameters, $this->domain, $locale);
    }
}
