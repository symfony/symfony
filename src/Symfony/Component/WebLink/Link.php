<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\WebLink;

use Psr\Link\EvolvableLinkInterface;

class Link implements EvolvableLinkInterface
{
    // @see https://www.iana.org/assignments/link-relations/link-relations.xhtml
    public const REL_ABOUT = 'about';
    public const REL_ACL = 'acl';
    public const REL_ALTERNATE = 'alternate';
    public const REL_AMPHTML = 'amphtml';
    public const REL_APPENDIX = 'appendix';
    public const REL_APPLE_TOUCH_ICON = 'apple-touch-icon';
    public const REL_APPLE_TOUCH_STARTUP_IMAGE = 'apple-touch-startup-image';
    public const REL_ARCHIVES = 'archives';
    public const REL_AUTHOR = 'author';
    public const REL_BLOCKED_BY = 'blocked-by';
    public const REL_BOOKMARK = 'bookmark';
    public const REL_CANONICAL = 'canonical';
    public const REL_CHAPTER = 'chapter';
    public const REL_CITE_AS = 'cite-as';
    public const REL_COLLECTION = 'collection';
    public const REL_CONTENTS = 'contents';
    public const REL_CONVERTEDFROM = 'convertedfrom';
    public const REL_COPYRIGHT = 'copyright';
    public const REL_CREATE_FORM = 'create-form';
    public const REL_CURRENT = 'current';
    public const REL_DESCRIBEDBY = 'describedby';
    public const REL_DESCRIBES = 'describes';
    public const REL_DISCLOSURE = 'disclosure';
    public const REL_DNS_PREFETCH = 'dns-prefetch';
    public const REL_DUPLICATE = 'duplicate';
    public const REL_EDIT = 'edit';
    public const REL_EDIT_FORM = 'edit-form';
    public const REL_EDIT_MEDIA = 'edit-media';
    public const REL_ENCLOSURE = 'enclosure';
    public const REL_EXTERNAL = 'external';
    public const REL_FIRST = 'first';
    public const REL_GLOSSARY = 'glossary';
    public const REL_HELP = 'help';
    public const REL_HOSTS = 'hosts';
    public const REL_HUB = 'hub';
    public const REL_ICON = 'icon';
    public const REL_INDEX = 'index';
    public const REL_INTERVALAFTER = 'intervalafter';
    public const REL_INTERVALBEFORE = 'intervalbefore';
    public const REL_INTERVALCONTAINS = 'intervalcontains';
    public const REL_INTERVALDISJOINT = 'intervaldisjoint';
    public const REL_INTERVALDURING = 'intervalduring';
    public const REL_INTERVALEQUALS = 'intervalequals';
    public const REL_INTERVALFINISHEDBY = 'intervalfinishedby';
    public const REL_INTERVALFINISHES = 'intervalfinishes';
    public const REL_INTERVALIN = 'intervalin';
    public const REL_INTERVALMEETS = 'intervalmeets';
    public const REL_INTERVALMETBY = 'intervalmetby';
    public const REL_INTERVALOVERLAPPEDBY = 'intervaloverlappedby';
    public const REL_INTERVALOVERLAPS = 'intervaloverlaps';
    public const REL_INTERVALSTARTEDBY = 'intervalstartedby';
    public const REL_INTERVALSTARTS = 'intervalstarts';
    public const REL_ITEM = 'item';
    public const REL_LAST = 'last';
    public const REL_LATEST_VERSION = 'latest-version';
    public const REL_LICENSE = 'license';
    public const REL_LINKSET = 'linkset';
    public const REL_LRDD = 'lrdd';
    public const REL_MANIFEST = 'manifest';
    public const REL_MASK_ICON = 'mask-icon';
    public const REL_MEDIA_FEED = 'media-feed';
    public const REL_MEMENTO = 'memento';
    public const REL_MICROPUB = 'micropub';
    public const REL_MODULEPRELOAD = 'modulepreload';
    public const REL_MONITOR = 'monitor';
    public const REL_MONITOR_GROUP = 'monitor-group';
    public const REL_NEXT = 'next';
    public const REL_NEXT_ARCHIVE = 'next-archive';
    public const REL_NOFOLLOW = 'nofollow';
    public const REL_NOOPENER = 'noopener';
    public const REL_NOREFERRER = 'noreferrer';
    public const REL_OPENER = 'opener';
    public const REL_OPENID_2_LOCAL_ID = 'openid2.local_id';
    public const REL_OPENID_2_PROVIDER = 'openid2.provider';
    public const REL_ORIGINAL = 'original';
    public const REL_P_3_PV_1 = 'p3pv1';
    public const REL_PAYMENT = 'payment';
    public const REL_PINGBACK = 'pingback';
    public const REL_PRECONNECT = 'preconnect';
    public const REL_PREDECESSOR_VERSION = 'predecessor-version';
    public const REL_PREFETCH = 'prefetch';
    public const REL_PRELOAD = 'preload';
    public const REL_PRERENDER = 'prerender';
    public const REL_PREV = 'prev';
    public const REL_PREVIEW = 'preview';
    public const REL_PREVIOUS = 'previous';
    public const REL_PREV_ARCHIVE = 'prev-archive';
    public const REL_PRIVACY_POLICY = 'privacy-policy';
    public const REL_PROFILE = 'profile';
    public const REL_PUBLICATION = 'publication';
    public const REL_RELATED = 'related';
    public const REL_RESTCONF = 'restconf';
    public const REL_REPLIES = 'replies';
    public const REL_RULEINPUT = 'ruleinput';
    public const REL_SEARCH = 'search';
    public const REL_SECTION = 'section';
    public const REL_SELF = 'self';
    public const REL_SERVICE = 'service';
    public const REL_SERVICE_DESC = 'service-desc';
    public const REL_SERVICE_DOC = 'service-doc';
    public const REL_SERVICE_META = 'service-meta';
    public const REL_SIPTRUNKINGCAPABILITY = 'siptrunkingcapability';
    public const REL_SPONSORED = 'sponsored';
    public const REL_START = 'start';
    public const REL_STATUS = 'status';
    public const REL_STYLESHEET = 'stylesheet';
    public const REL_SUBSECTION = 'subsection';
    public const REL_SUCCESSOR_VERSION = 'successor-version';
    public const REL_SUNSET = 'sunset';
    public const REL_TAG = 'tag';
    public const REL_TERMS_OF_SERVICE = 'terms-of-service';
    public const REL_TIMEGATE = 'timegate';
    public const REL_TIMEMAP = 'timemap';
    public const REL_TYPE = 'type';
    public const REL_UGC = 'ugc';
    public const REL_UP = 'up';
    public const REL_VERSION_HISTORY = 'version-history';
    public const REL_VIA = 'via';
    public const REL_WEBMENTION = 'webmention';
    public const REL_WORKING_COPY = 'working-copy';
    public const REL_WORKING_COPY_OF = 'working-copy-of';

    // Extra relations
    public const REL_MERCURE = 'mercure';

    private string $href = '';

    /**
     * @var string[]
     */
    private array $rel = [];

    /**
     * @var array<string, string|bool|string[]>
     */
    private array $attributes = [];

    public function __construct(?string $rel = null, string $href = '')
    {
        if (null !== $rel) {
            $this->rel[$rel] = $rel;
        }
        $this->href = $href;
    }

    public function getHref(): string
    {
        return $this->href;
    }

    public function isTemplated(): bool
    {
        return $this->hrefIsTemplated($this->href);
    }

    public function getRels(): array
    {
        return array_values($this->rel);
    }

    public function getAttributes(): array
    {
        return $this->attributes;
    }

    public function withHref(string|\Stringable $href): static
    {
        $that = clone $this;
        $that->href = $href;

        return $that;
    }

    public function withRel(string $rel): static
    {
        $that = clone $this;
        $that->rel[$rel] = $rel;

        return $that;
    }

    public function withoutRel(string $rel): static
    {
        $that = clone $this;
        unset($that->rel[$rel]);

        return $that;
    }

    public function withAttribute(string $attribute, string|\Stringable|int|float|bool|array $value): static
    {
        $that = clone $this;
        $that->attributes[$attribute] = $value;

        return $that;
    }

    public function withoutAttribute(string $attribute): static
    {
        $that = clone $this;
        unset($that->attributes[$attribute]);

        return $that;
    }

    private function hrefIsTemplated(string $href): bool
    {
        return str_contains($href, '{') || str_contains($href, '}');
    }
}
