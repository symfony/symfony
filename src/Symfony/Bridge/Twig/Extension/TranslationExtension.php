<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bridge\Twig\Extension;

use Symfony\Bridge\Twig\NodeVisitor\TransDefaultDomainRegistry;
use Symfony\Bridge\Twig\NodeVisitor\TranslationDefaultDomainNodeVisitor;
use Symfony\Bridge\Twig\NodeVisitor\TranslationNodeVisitor;
use Symfony\Bridge\Twig\TokenParser\TransDefaultDomainTokenParser;
use Symfony\Bridge\Twig\TokenParser\TransTokenParser;
use Symfony\Component\Translation\HtmlTranslator;
use Symfony\Component\Translation\TranslatableMessage;
use Symfony\Contracts\Translation\TranslatableInterface;
use Symfony\Contracts\Translation\TranslatorInterface;
use Symfony\Contracts\Translation\TranslatorTrait;
use Twig\Extension\AbstractExtension;
use Twig\Markup;
use Twig\TwigFilter;
use Twig\TwigFunction;

// Help opcache.preload discover always-needed symbols
class_exists(TranslatorInterface::class);
class_exists(TranslatorTrait::class);

/**
 * Provides integration of the Translation component with Twig.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
final class TranslationExtension extends AbstractExtension
{
    private ?HtmlTranslator $htmlTranslator = null;

    public function __construct(
        private ?TranslatorInterface $translator = null,
        private ?TranslationNodeVisitor $translationNodeVisitor = null,
        private readonly TransDefaultDomainRegistry $transDefaultDomainRegistry = new TransDefaultDomainRegistry(),
    ) {
    }

    public function getTranslator(): TranslatorInterface
    {
        if (null === $this->translator) {
            if (!interface_exists(TranslatorInterface::class)) {
                throw new \LogicException(\sprintf('You cannot use the "%s" if the Translation Contracts are not available. Try running "composer require symfony/translation".', __CLASS__));
            }

            $this->translator = new class implements TranslatorInterface {
                use TranslatorTrait;
            };
        }

        return $this->translator;
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('t', $this->createTranslatable(...)),
        ];
    }

    public function getFilters(): array
    {
        return [
            new TwigFilter('trans', $this->trans(...)),
            new TwigFilter('trans_html', $this->transHtml(...), ['is_safe' => ['html']]),
        ];
    }

    public function getTokenParsers(): array
    {
        return [
            // {% trans %}Symfony is great!{% endtrans %}
            new TransTokenParser(),

            // {% trans_default_domain "foobar" %}
            new TransDefaultDomainTokenParser($this->transDefaultDomainRegistry),
        ];
    }

    public function getNodeVisitors(): array
    {
        return [$this->getTranslationNodeVisitor(), new TranslationDefaultDomainNodeVisitor($this->transDefaultDomainRegistry)];
    }

    public function getTranslationNodeVisitor(): TranslationNodeVisitor
    {
        return $this->translationNodeVisitor ?: $this->translationNodeVisitor = new TranslationNodeVisitor();
    }

    /**
     * @param array|string $arguments Can be the locale as a string when $message is a TranslatableInterface
     */
    public function trans(string|\Stringable|TranslatableInterface|null $message, array|string $arguments = [], ?string $domain = null, ?string $locale = null, ?int $count = null): string
    {
        if ($message instanceof TranslatableInterface) {
            if ([] !== $arguments && !\is_string($arguments)) {
                throw new \TypeError(\sprintf('Argument 2 passed to "%s()" must be a locale passed as a string when the message is a "%s", "%s" given.', __METHOD__, TranslatableInterface::class, get_debug_type($arguments)));
            }

            if ($message instanceof TranslatableMessage && '' === $message->getMessage()) {
                return '';
            }

            return $message->trans($this->getTranslator(), $locale ?? (\is_string($arguments) ? $arguments : null));
        }

        if (!\is_array($arguments)) {
            throw new \TypeError(\sprintf('Unless the message is a "%s", argument 2 passed to "%s()" must be an array of parameters, "%s" given.', TranslatableInterface::class, __METHOD__, get_debug_type($arguments)));
        }

        if ('' === $message = (string) $message) {
            return '';
        }

        if (null !== $count) {
            $arguments['%count%'] = $count;
        }

        return $this->getTranslator()->trans($message, $arguments, $domain, $locale);
    }

    /**
     * @param array|string $arguments Can be the locale as a string when $message is a TranslatableInterface
     *
     * @internal
     */
    public function transHtml(string|\Stringable|TranslatableInterface|null $message, array|string $arguments = [], ?string $domain = null, ?string $locale = null, ?int $count = null, array $tags = []): string
    {
        if ($message instanceof TranslatableMessage && ([] === $arguments || \is_string($arguments))) {
            $locale ??= \is_string($arguments) ? $arguments : null;
            [$message, $arguments, $domain] = [$message->getMessage(), $message->getParameters(), $message->getDomain()];
        } elseif ($message instanceof TranslatableInterface || !\is_array($arguments)) {
            return htmlspecialchars($this->trans($message, $arguments, $domain, $locale, $count), \ENT_QUOTES | \ENT_SUBSTITUTE, 'UTF-8');
        }

        if ('' === $message = (string) $message) {
            return '';
        }

        if (null !== $count) {
            $arguments['%count%'] = $count;
        }

        $placeholders = [];
        foreach ($arguments as $key => $value) {
            if ($value instanceof Markup) {
                $placeholder = self::createMarkupPlaceholder();
                $placeholders[$placeholder] = (string) $value;
                $arguments[$key] = $placeholder;
            }
        }

        return strtr($this->getHtmlTranslator()->trans($message, $arguments, $domain, $locale, $tags), $placeholders);
    }

    public function createTranslatable(string $message, array $parameters = [], ?string $domain = null): TranslatableMessage
    {
        if (!class_exists(TranslatableMessage::class)) {
            throw new \LogicException(\sprintf('You cannot use the "%s" as the Translation Component is not installed. Try running "composer require symfony/translation".', __CLASS__));
        }

        return new TranslatableMessage($message, $parameters, $domain);
    }

    private static function createMarkupPlaceholder(): string
    {
        // private-use characters only, so that translators rewriting ASCII (e.g. pseudo-localization) leave the placeholder intact
        $placeholder = "\u{E000}";
        foreach (str_split(random_bytes(8)) as $byte) {
            $placeholder .= mb_chr(0xE100 + \ord($byte), 'UTF-8');
        }

        return $placeholder."\u{E001}";
    }

    private function getHtmlTranslator(): HtmlTranslator
    {
        if (!class_exists(HtmlTranslator::class)) {
            throw new \LogicException(\sprintf('Using the "trans_html" filter requires symfony/translation >= 8.2. Try running "%s".', class_exists(TranslatableMessage::class) ? 'composer update symfony/translation' : 'composer require symfony/translation'));
        }

        return $this->htmlTranslator ??= new HtmlTranslator($this->getTranslator());
    }
}
