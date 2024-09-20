<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HtmlSanitizer;

use Symfony\Component\HtmlSanitizer\Reference\W3CReference;
use Symfony\Component\HtmlSanitizer\Visitor\AttributeSanitizer\AttributeSanitizerInterface;

/**
 * @author Titouan Galopin <galopintitouan@gmail.com>
 */
class HtmlSanitizerConfig
{
    private HtmlSanitizerAction $defaultAction = HtmlSanitizerAction::Drop;

    /**
     * Elements that should be removed.
     *
     * @var array<string, true>
     */
    private array $droppedElements = [];

    /**
     * Elements that should be removed but their children should be retained.
     *
     * @var array<string, true>
     */
    private array $blockedElements = [];

    /**
     * Elements that should be retained, with their allowed attributes.
     *
     * @var array<string, array<string, true>>
     */
    private array $allowedElements = [];

    /**
     * Attributes that should always be added to certain elements.
     *
     * @var array<string, array<string, string>>
     */
    private array $forcedAttributes = [];

    /**
     * Links schemes that should be retained, other being dropped.
     *
     * @var list<string>
     */
    private array $allowedLinkSchemes = ['http', 'https', 'mailto', 'tel'];

    /**
     * Links hosts that should be retained (by default, all hosts are allowed).
     *
     * @var list<string>|null
     */
    private ?array $allowedLinkHosts = null;

    /**
     * Should the sanitizer allow relative links (by default, they are dropped).
     */
    private bool $allowRelativeLinks = false;

    /**
     * Image/Audio/Video schemes that should be retained, other being dropped.
     *
     * @var list<string>
     */
    private array $allowedMediaSchemes = ['http', 'https', 'data'];

    /**
     * Image/Audio/Video hosts that should be retained (by default, all hosts are allowed).
     *
     * @var list<string>|null
     */
    private ?array $allowedMediaHosts = null;

    /**
     * Should the sanitizer allow relative media URL (by default, they are dropped).
     */
    private bool $allowRelativeMedias = false;

    /**
     * Should the URL in the sanitized document be transformed to HTTPS if they are using HTTP.
     */
    private bool $forceHttpsUrls = false;

    /**
     * Sanitizers that should be applied to specific attributes in addition to standard sanitization.
     *
     * @var list<AttributeSanitizerInterface>
     */
    private array $attributeSanitizers;

    private int $maxInputLength = 20_000;

    public function __construct()
    {
        $this->attributeSanitizers = [
            new Visitor\AttributeSanitizer\UrlAttributeSanitizer(),
        ];
    }

    /**
     * Sets the default action for elements which are not otherwise specifically allowed or blocked.
     *
     * Note that a default action of Allow will allow all tags but they will not have any attributes.
     */
    public function defaultAction(HtmlSanitizerAction $action): static
    {
        $clone = clone $this;
        $clone->defaultAction = $action;

        return $clone;
    }

    /**
     * Allows all static elements and attributes from the W3C Sanitizer API standard.
     *
     * All scripts will be removed but the output may still contain other dangerous
     * behaviors like CSS injection (click-jacking), CSS expressions, ...
     */
    public function allowStaticElements(): static
    {
        $elements = array_merge(
            array_keys(W3CReference::HEAD_ELEMENTS),
            array_keys(W3CReference::BODY_ELEMENTS)
        );

        $clone = clone $this;
        foreach ($elements as $element) {
            $clone = $clone->allowElement($element, '*');
        }

        return $clone;
    }

    /**
     * Allows "safe" elements and attributes.
     *
     * All scripts will be removed, as well as other dangerous behaviors like CSS injection.
     */
    public function allowSafeElements(): static
    {
        $attributes = [];
        foreach (W3CReference::ATTRIBUTES as $attribute => $isSafe) {
            if ($isSafe) {
                $attributes[] = $attribute;
            }
        }

        $clone = clone $this;

        foreach (W3CReference::HEAD_ELEMENTS as $element => $isSafe) {
            if ($isSafe) {
                $clone = $clone->allowElement($element, $attributes);
            }
        }

        foreach (W3CReference::BODY_ELEMENTS as $element => $isSafe) {
            if ($isSafe) {
                $clone = $clone->allowElement($element, $attributes);
            }
        }

        return $clone;
    }

    /**
     * Allows only a given list of schemes to be used in links href attributes.
     *
     * All other schemes will be dropped.
     *
     * @param list<string> $allowLinkSchemes
     */
    public function allowLinkSchemes(array $allowLinkSchemes): static
    {
        $clone = clone $this;
        $clone->allowedLinkSchemes = $allowLinkSchemes;

        return $clone;
    }

    /**
     * Allows only a given list of hosts to be used in links href attributes.
     *
     * All other hosts will be dropped. By default all hosts are allowed
     * ($allowedLinkHosts = null).
     *
     * @param list<string>|null $allowLinkHosts
     */
    public function allowLinkHosts(?array $allowLinkHosts): static
    {
        $clone = clone $this;
        $clone->allowedLinkHosts = $allowLinkHosts;

        return $clone;
    }

    /**
     * Allows relative URLs to be used in links href attributes.
     */
    public function allowRelativeLinks(bool $allowRelativeLinks = true): static
    {
        $clone = clone $this;
        $clone->allowRelativeLinks = $allowRelativeLinks;

        return $clone;
    }

    /**
     * Allows only a given list of schemes to be used in media source attributes (img, audio, video, ...).
     *
     * All other schemes will be dropped.
     *
     * @param list<string> $allowMediaSchemes
     */
    public function allowMediaSchemes(array $allowMediaSchemes): static
    {
        $clone = clone $this;
        $clone->allowedMediaSchemes = $allowMediaSchemes;

        return $clone;
    }

    /**
     * Allows only a given list of hosts to be used in media source attributes (img, audio, video, ...).
     *
     * All other hosts will be dropped. By default all hosts are allowed
     * ($allowMediaHosts = null).
     *
     * @param list<string>|null $allowMediaHosts
     */
    public function allowMediaHosts(?array $allowMediaHosts): static
    {
        $clone = clone $this;
        $clone->allowedMediaHosts = $allowMediaHosts;

        return $clone;
    }

    /**
     * Allows relative URLs to be used in media source attributes (img, audio, video, ...).
     */
    public function allowRelativeMedias(bool $allowRelativeMedias = true): static
    {
        $clone = clone $this;
        $clone->allowRelativeMedias = $allowRelativeMedias;

        return $clone;
    }

    /**
     * Transforms URLs using the HTTP scheme to use the HTTPS scheme instead.
     */
    public function forceHttpsUrls(bool $forceHttpsUrls = true): static
    {
        $clone = clone $this;
        $clone->forceHttpsUrls = $forceHttpsUrls;

        return $clone;
    }

    /**
     * Configures the given element as allowed.
     *
     * Allowed elements are elements the sanitizer should retain from the input.
     *
     * A list of allowed attributes for this element can be passed as a second argument.
     * Passing "*" will allow all standard attributes on this element. By default, no
     * attributes are allowed on the element.
     *
     * @param list<string>|string $allowedAttributes
     */
    public function allowElement(string $element, array|string $allowedAttributes = []): static
    {
        $clone = clone $this;

        // Unblock/undrop the element if necessary
        unset($clone->blockedElements[$element], $clone->droppedElements[$element]);

        $clone->allowedElements[$element] = [];

        $attrs = ('*' === $allowedAttributes) ? array_keys(W3CReference::ATTRIBUTES) : (array) $allowedAttributes;
        foreach ($attrs as $allowedAttr) {
            $clone->allowedElements[$element][$allowedAttr] = true;
        }

        return $clone;
    }

    /**
     * Configures the given element as blocked.
     *
     * Blocked elements are elements the sanitizer should remove from the input, but retain
     * their children.
     */
    public function blockElement(string $element): static
    {
        $clone = clone $this;

        // Disallow/undrop the element if necessary
        unset($clone->allowedElements[$element], $clone->droppedElements[$element]);

        $clone->blockedElements[$element] = true;

        return $clone;
    }

    /**
     * Configures the given element as dropped.
     *
     * Dropped elements are elements the sanitizer should remove from the input, including
     * their children.
     *
     * Note: when using an empty configuration, all unknown elements are dropped
     * automatically. This method let you drop elements that were allowed earlier
     * in the configuration, or explicitly drop some if you changed the default action.
     */
    public function dropElement(string $element): static
    {
        $clone = clone $this;
        unset($clone->allowedElements[$element], $clone->blockedElements[$element]);

        $clone->droppedElements[$element] = true;

        return $clone;
    }

    /**
     * Configures the given attribute as allowed.
     *
     * Allowed attributes are attributes the sanitizer should retain from the input.
     *
     * A list of allowed elements for this attribute can be passed as a second argument.
     * Passing "*" will allow all currently allowed elements to use this attribute.
     *
     * @param list<string>|string $allowedElements
     */
    public function allowAttribute(string $attribute, array|string $allowedElements): static
    {
        $clone = clone $this;
        $allowedElements = ('*' === $allowedElements) ? array_keys($clone->allowedElements) : (array) $allowedElements;

        // For each configured element ...
        foreach ($clone->allowedElements as $element => $attrs) {
            if (\in_array($element, $allowedElements, true)) {
                // ... if the attribute should be allowed, add it
                $clone->allowedElements[$element][$attribute] = true;
            } else {
                // ... if the attribute should not be allowed, remove it
                unset($clone->allowedElements[$element][$attribute]);
            }
        }

        return $clone;
    }

    /**
     * Configures the given attribute as dropped.
     *
     * Dropped attributes are attributes the sanitizer should remove from the input.
     *
     * A list of elements on which to drop this attribute can be passed as a second argument.
     * Passing "*" will drop this attribute from all currently allowed elements.
     *
     * Note: when using an empty configuration, all unknown attributes are dropped
     * automatically. This method let you drop attributes that were allowed earlier
     * in the configuration.
     *
     * @param list<string>|string $droppedElements
     */
    public function dropAttribute(string $attribute, array|string $droppedElements): static
    {
        $clone = clone $this;
        $droppedElements = ('*' === $droppedElements) ? array_keys($clone->allowedElements) : (array) $droppedElements;

        foreach ($droppedElements as $element) {
            if (isset($clone->allowedElements[$element][$attribute])) {
                unset($clone->allowedElements[$element][$attribute]);
            }
        }

        return $clone;
    }

    /**
     * Forcefully set the value of a given attribute on a given element.
     *
     * The attribute will be created on the nodes if it didn't exist.
     */
    public function forceAttribute(string $element, string $attribute, string $value): static
    {
        $clone = clone $this;
        $clone->forcedAttributes[$element][$attribute] = $value;

        return $clone;
    }

    /**
     * Registers a custom attribute sanitizer.
     */
    public function withAttributeSanitizer(AttributeSanitizerInterface $sanitizer): static
    {
        $clone = clone $this;
        $clone->attributeSanitizers[] = $sanitizer;

        return $clone;
    }

    /**
     * Unregisters a custom attribute sanitizer.
     */
    public function withoutAttributeSanitizer(AttributeSanitizerInterface $sanitizer): static
    {
        $clone = clone $this;
        $clone->attributeSanitizers = array_values(array_filter(
            $this->attributeSanitizers,
            static fn ($current) => $current !== $sanitizer
        ));

        return $clone;
    }

    /**
     * @param int $maxInputLength The maximum length of the input string in bytes
     *                            -1 means no limit
     */
    public function withMaxInputLength(int $maxInputLength): static
    {
        if ($maxInputLength < -1) {
            throw new \InvalidArgumentException(\sprintf('The maximum input length must be greater than -1, "%d" given.', $maxInputLength));
        }

        $clone = clone $this;
        $clone->maxInputLength = $maxInputLength;

        return $clone;
    }

    public function getMaxInputLength(): int
    {
        return $this->maxInputLength;
    }

    public function getDefaultAction(): HtmlSanitizerAction
    {
        return $this->defaultAction;
    }

    /**
     * @return array<string, array<string, true>>
     */
    public function getAllowedElements(): array
    {
        return $this->allowedElements;
    }

    /**
     * @return array<string, true>
     */
    public function getBlockedElements(): array
    {
        return $this->blockedElements;
    }

    /**
     * @return array<string, true>
     */
    public function getDroppedElements(): array
    {
        return $this->droppedElements;
    }

    /**
     * @return array<string, array<string, string>>
     */
    public function getForcedAttributes(): array
    {
        return $this->forcedAttributes;
    }

    /**
     * @return list<string>
     */
    public function getAllowedLinkSchemes(): array
    {
        return $this->allowedLinkSchemes;
    }

    /**
     * @return list<string>|null
     */
    public function getAllowedLinkHosts(): ?array
    {
        return $this->allowedLinkHosts;
    }

    public function getAllowRelativeLinks(): bool
    {
        return $this->allowRelativeLinks;
    }

    /**
     * @return list<string>
     */
    public function getAllowedMediaSchemes(): array
    {
        return $this->allowedMediaSchemes;
    }

    /**
     * @return list<string>|null
     */
    public function getAllowedMediaHosts(): ?array
    {
        return $this->allowedMediaHosts;
    }

    public function getAllowRelativeMedias(): bool
    {
        return $this->allowRelativeMedias;
    }

    public function getForceHttpsUrls(): bool
    {
        return $this->forceHttpsUrls;
    }

    /**
     * @return list<AttributeSanitizerInterface>
     */
    public function getAttributeSanitizers(): array
    {
        return $this->attributeSanitizers;
    }
}
