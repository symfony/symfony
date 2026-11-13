<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Tui\Css;

use Psr\Log\LoggerInterface;
use Symfony\Component\Tui\Ansi\AnsiUtils;
use Symfony\Component\Tui\Compatibility\Support;
use Symfony\Component\Tui\Compatibility\SupportProviderInterface;
use Symfony\Component\Tui\Exception\InvalidArgumentException;
use Symfony\Component\Tui\Style\CellSize;
use Symfony\Component\Tui\Style\Color;
use Symfony\Component\Tui\Style\ColorScheme;
use Symfony\Component\Tui\Style\Density;
use Symfony\Component\Tui\Style\Direction;
use Symfony\Component\Tui\Style\IntrinsicSizeAwareInterface;
use Symfony\Component\Tui\Style\MediaContext;
use Symfony\Component\Tui\Style\Padding;
use Symfony\Component\Tui\Style\PseudoElementProviderInterface;
use Symfony\Component\Tui\Style\RasterImage;
use Symfony\Component\Tui\Style\ScrollStateAwareInterface;
use Symfony\Component\Tui\Style\Style;
use Symfony\Component\Tui\Style\TextAlign;
use Symfony\Component\Tui\Style\VectorImage;
use Symfony\Component\Tui\Widget\AbstractWidget;
use Symfony\Component\Tui\Widget\ButtonWidget;
use Symfony\Component\Tui\Widget\CheckboxWidget;
use Symfony\Component\Tui\Widget\CollapsibleWidget;
use Symfony\Component\Tui\Widget\ContainerWidget;
use Symfony\Component\Tui\Widget\DropdownWidget;
use Symfony\Component\Tui\Widget\EditorWidget;
use Symfony\Component\Tui\Widget\FieldSizingInterface;
use Symfony\Component\Tui\Widget\FocusableInterface;
use Symfony\Component\Tui\Widget\FrameWidget;
use Symfony\Component\Tui\Widget\InputWidget;
use Symfony\Component\Tui\Widget\LineWrappingInterface;
use Symfony\Component\Tui\Widget\NativeAppearanceInterface;
use Symfony\Component\Tui\Widget\ParentInterface;
use Symfony\Component\Tui\Widget\PartsInterface;
use Symfony\Component\Tui\Widget\ProgressBarWidget;
use Symfony\Component\Tui\Widget\RadioWidget;
use Symfony\Component\Tui\Widget\RichTextWidget;
use Symfony\Component\Tui\Widget\SelectListWidget;
use Symfony\Component\Tui\Widget\Text\ElementContentInterface;
use Symfony\Component\Tui\Widget\Text\GeneratedContentInterface;
use Symfony\Component\Tui\Widget\Text\Hyphenator;
use Symfony\Component\Tui\Widget\Text\HyphenatorRegistry;
use Symfony\Component\Tui\Widget\Text\InlineContentInterface;
use Symfony\Component\Tui\Widget\Text\InlineFormat;
use Symfony\Component\Tui\Widget\Text\InlineImages;
use Symfony\Component\Tui\Widget\Text\InlineNode;
use Symfony\Component\Tui\Widget\TextWidget;
use Symfony\Component\Tui\Widget\Util\StringUtils;
use Symfony\Component\Tui\Widget\Util\UrlResolver;

/**
 * Styles widgets with CSS.
 *
 * The widget tree is mirrored into a DOM document, so selectors are matched
 * by PHP's native selector engine: combinators, attribute selectors, :is(),
 * :has()... Widget states (focus, hover, any custom state) are written to
 * an attribute of the mirror, and the pseudo-classes rewritten to match it.
 *
 * Each widget gets one computed style: the CSS cascade (origin, importance,
 * specificity, order), inheritance and custom properties are resolved on
 * declarations, which are then mapped to a {@see Style}.
 *
 * Matching runs only when the tree, a class, a state or the media context
 * changed since the previous frame.
 *
 * @experimental
 */
final class CssStyleSheet implements ScrollStateAwareInterface, IntrinsicSizeAwareInterface, SupportProviderInterface, PseudoElementProviderInterface
{
    /** The content of the <meta name="color-scheme"> of the document, on <html>: the color-scheme of the page */
    public const COLOR_SCHEME_ATTRIBUTE = 'data-tui-color-scheme';

    /**
     * The background behind an element, inherited like a custom property, over which translucent colors are blended.
     */
    private const BACKDROP = '--tui-backdrop';

    /** The root of the nested document styled alone, see setScope() */
    private ?AbstractWidget $scope = null;
    /** @var array{int, int}|null */
    private ?array $scopeSize = null;

    /** Set inside a group of its own (isolation: isolate...) without a background: mix-blend-mode has nothing to blend with */
    private const ISOLATED = '--tui-isolated';

    /** Marks an element without a box (display: contents) in a flex row, whose children are flex items */
    private const IN_FLEX_ROW = '--tui-in-flex-row';
    /** Marks a form control in the presentational hints, see computeDeclarations() */
    private const CONTROL = '--tui-control';
    /** Marks an element of a document in the presentational hints, see computeDeclarations() */
    private const ELEMENT = '--tui-element';

    /** The tag of the anonymous row the HTML builder lines up inline blocks and controls in */
    public const LINE_TAG = 'tui-line';

    /** A hint marking <html> and <body>, whose height lets the document overflow, see computeDeclarations() */
    private const DOCUMENT = '--tui-document';

    /** A hint marking inline code, kbd and samp, which the user agent draws in reverse video, see computeDeclarations() */
    private const CODE = '--tui-code';

    /** A hint marking a block lined up in a row of inline content, inline-block in some media only */
    private const LINE_ITEM = '--tui-line-item';

    /** Marks an item of a grid written vertically, placed with its rows and columns the other way around */
    private const IN_VERTICAL_GRID = '--tui-in-vertical-grid';
    /** Marks an element without a box (display: contents) in a flex or grid container, whose children are its items */
    private const IN_ITEMS = '--tui-in-items';
    /** Set on an element of a normal or bold weight inside a light one, which would otherwise stay dim */
    private const UNDIM = '--tui-undim';

    /** The most addresses of images the style sheets read, and the most bytes of them */
    private const MAX_IMAGES = 256;
    private const MAX_IMAGES_BYTES = 64 * 1024 * 1024;

    /** The largest image of a url() read, in bytes, like an <img> */
    public const MAX_IMAGE_BYTES = 8_388_608;

    /** The quotation marks of quotes: auto by language, by nesting level, like CLDR; other languages take the English ones */
    private const LANGUAGE_QUOTES = [
        'fr' => [['«', '»'], ['“', '”']],
        'de' => [['„', '“'], ['‚', '‘']],
        'es' => [['«', '»'], ['“', '”']],
        'it' => [['«', '»'], ['“', '”']],
        'ru' => [['«', '»'], ['„', '“']],
        'uk' => [['«', '»'], ['„', '“']],
        'pl' => [['„', '”'], ['«', '»']],
        'cs' => [['„', '“'], ['‚', '‘']],
        'nl' => [['‘', '’'], ['“', '”']],
        'sv' => [['”', '”'], ['’', '’']],
        'fi' => [['”', '”'], ['’', '’']],
        'no' => [['«', '»'], ['‘', '’']],
        'nb' => [['«', '»'], ['‘', '’']],
        'ja' => [['「', '」'], ['『', '』']],
        'he' => [['”', '”'], ['’', '’']],
        'el' => [['«', '»'], ['“', '”']],
        'hu' => [['„', '”'], ['»', '«']],
    ];

    /** The container units of a value, like 50cqw: the number and the axis */
    private const CONTAINER_UNITS = '/(?<![\w.-])(-?(?:\d+\.?\d*|\.\d+))cq(w|h|i|b|min|max)(?![\w-])/i';

    /** The tag of the root of the tree when it is not described, like the root of the Tui */
    public const ROOT_TAG = 'tui-root';

    /** The properties of a pseudo-element that generate it rather than style it */
    private const GENERATING = ['content' => true, 'counter-increment' => true, 'counter-reset' => true, 'counter-set' => true, 'quotes' => true, 'display' => true, 'position' => true];

    private const DEFAULT_TAGS = [
        ContainerWidget::class => 'div',
        InputWidget::class => 'input',
        EditorWidget::class => 'textarea',
        SelectListWidget::class => 'select',
        ProgressBarWidget::class => 'progress',
        CollapsibleWidget::class => 'details',
        ButtonWidget::class => 'button',
        CheckboxWidget::class => 'input',
        RadioWidget::class => 'input',
    ];

    /** The columns of the narrowest screen the builder lays out for, before the size of the screen is known */
    private const NARROWEST_SCREEN = 80;

    private const OID_ATTRIBUTE = 'data-tui-oid';
    private const INLINE_ATTRIBUTE = 'data-tui-inline';
    private const PART_ATTRIBUTE = 'data-tui-part';

    /** The properties of a field its caret (the ::cursor sub-element) takes */
    private const CARET_PROPERTIES = ['caret' => true, 'caret-shape' => true, 'caret-color' => true, 'caret-animation' => true];
    /** The values of font-variant-numeric that change the characters drawn, in the order they apply */
    private const NUMERIC_VARIANTS = ['diagonal-fractions', 'ordinal', 'slashed-zero'];
    /** Text properties inherited by children, besides the ones the mapper turns into styles */
    /** The sides and corners of the shorthands that set them, with their index in it, see parentValue() */
    private const SIDE_SHORTHANDS = [
        'margin-top' => ['margin', 0], 'margin-right' => ['margin', 1], 'margin-bottom' => ['margin', 2], 'margin-left' => ['margin', 3],
        'padding-top' => ['padding', 0], 'padding-right' => ['padding', 1], 'padding-bottom' => ['padding', 2], 'padding-left' => ['padding', 3],
        'top' => ['inset', 0], 'right' => ['inset', 1], 'bottom' => ['inset', 2], 'left' => ['inset', 3],
        'border-top-width' => ['border-width', 0], 'border-right-width' => ['border-width', 1], 'border-bottom-width' => ['border-width', 2], 'border-left-width' => ['border-width', 3],
        'border-top-style' => ['border-style', 0], 'border-right-style' => ['border-style', 1], 'border-bottom-style' => ['border-style', 2], 'border-left-style' => ['border-style', 3],
        'border-top-color' => ['border-color', 0], 'border-right-color' => ['border-color', 1], 'border-bottom-color' => ['border-color', 2], 'border-left-color' => ['border-color', 3],
        'border-top-left-radius' => ['border-radius', 0], 'border-top-right-radius' => ['border-radius', 1], 'border-bottom-right-radius' => ['border-radius', 2], 'border-bottom-left-radius' => ['border-radius', 3],
    ];

    private const TEXT_INHERITED = ['list-style-type', 'list-style', 'list-style-image', 'image-orientation', 'image-rendering', 'list-style-position', 'text-transform', 'white-space', 'white-space-collapse', 'word-spacing', 'text-align-last', 'text-indent', 'pointer-events', 'cursor', 'accent-color', 'line-height', 'letter-spacing', 'color-scheme', 'quotes', 'font-variant', 'font-variant-caps', 'font-variant-position', 'font-variant-numeric', 'word-break', 'line-break', 'overflow-wrap', 'word-wrap', 'hyphens', 'hyphenate-character', 'hyphenate-limit-chars', '-webkit-text-fill-color', 'forced-color-adjust', 'text-autospace', 'interpolate-size', 'text-wrap', 'text-wrap-mode', 'text-wrap-style', 'tab-size', 'empty-cells', 'ruby-position', 'ruby-align', 'orphans', 'widows', 'text-emphasis', 'text-emphasis-style', 'text-emphasis-color', 'text-emphasis-position', 'text-shadow', 'text-justify'];

    /** The sub-elements accent-color paints: the box of checkboxes and radios, the filled part of progress bars */
    private const ACCENT_ELEMENTS = ['box', 'bar-fill', 'bar-progress'];

    /** The CSS cursor keywords: the pointer shapes terminals know with OSC 22 */
    public const CURSORS = ['auto', 'default', 'none', 'context-menu', 'help', 'pointer', 'progress', 'wait', 'cell', 'crosshair', 'text', 'vertical-text', 'alias', 'copy', 'move', 'no-drop', 'not-allowed', 'grab', 'grabbing', 'all-scroll', 'col-resize', 'row-resize', 'n-resize', 'e-resize', 's-resize', 'w-resize', 'ne-resize', 'nw-resize', 'se-resize', 'sw-resize', 'ew-resize', 'ns-resize', 'nesw-resize', 'nwse-resize', 'zoom-in', 'zoom-out'];

    private readonly CssParser $parser;
    private readonly DeclarationMapper $mapper;

    /** @var list<CssRule> */
    private array $rules = [];

    /**
     * The counters in scope during the cascade, innermost last, with the
     * depth of the element whose children and following siblings see them.
     *
     * @var array<string, list<array{int, int}>>
     */
    private array $counters = [];
    private int $counterDepth = 0;

    /**
     * The ordinals of the items of the lists seen during the cascade, by the object id of their list: the list, the
     * ordinal of each item and the one after them all.
     *
     * @var array<int, array{\Dom\Element, \SplObjectStorage<\Dom\Element, int>, int}>
     */
    private array $listOrdinals = [];

    /** How deep the quotes generated so far are nested, like CSS */
    private int $quoteDepth = 0;

    /** @var list<array{string, string}> The quotes of the content being parsed */
    private array $quotes = [];

    /** @var array<string, true> Sources already added, by hash */
    private array $sources = [];

    /** @var \WeakMap<AbstractWidget, array{string, array<string, string>}> */
    private \WeakMap $descriptions;
    /** @var \WeakMap<AbstractWidget, list<string>> The classes of each widget that are not those of its element, see describe() */
    private \WeakMap $internalClasses;

    /** @var array<string, list<Declaration>> */
    private array $inlineCache = [];

    // The mirror

    private ?\Dom\HTMLDocument $document = null;

    /** @var array<int, \Dom\Element> By widget object id */
    private array $elements = [];

    /** @var array<int, \WeakReference<AbstractWidget>> */
    private array $widgetsById = [];

    /** @var list<array{RasterImage, int, string}>|null The images of the generated content of the text being formatted, see parseContent() */
    private ?array $contentImages = null;
    /** @var array<string, int> The pixels of the images of generated content, by address, which they may not take more of than 4 large images together */
    private array $contentPixels = [];

    /** @var array<string, true> The hrefs of the links visited, which :visited matches */
    private array $visited = [];
    /** The URL relative links are resolved against, like the href of <base>, for :visited */
    private ?string $baseUrl = null;

    /** @var \WeakMap<AbstractWidget, array{int, Density, bool, bool, string, string, 6?: list<\Dom\Element>}> Revision, density, focus-within, an invalid control within, language and direction when last mirrored */
    private \WeakMap $synced;

    private ?AbstractWidget $root = null;

    /** @var \WeakMap<AbstractWidget, array{int, int}> The content size of the containers, from the last frame */
    private \WeakMap $containerSizes;
    /** @var \WeakMap<AbstractWidget, array{stuck?: list<'top'|'bottom'>, snapped?: list<'x'|'y'>, scrollable?: list<'top'|'bottom'|'left'|'right'>, scrolled?: list<'top'|'bottom'|'left'|'right'>, targetCurrent?: bool, offset?: array{array{}|array{int, int}, array{}|array{int, int}}}> The scroll state of the scroll-state containers in the last frame */
    private \WeakMap $scrollStates;
    /** @var (\Closure(AbstractWidget): array{stuck: list<'top'|'bottom'>, snapped: list<'x'|'y'>, scrollable: list<'top'|'bottom'|'left'|'right'>, scrolled: list<'top'|'bottom'|'left'|'right'>, targetCurrent: bool, offset: array{array{}|array{int, int}, array{}|array{int, int}}})|null */
    private ?\Closure $scrollStateOf = null;
    /** @var array<string, array{params: list<array{string, ?string, ?string}>, returns: ?string, body: list<array{list<array{string, string}>, string, string}>}> The custom functions of @function rules, by name */
    private array $functions = [];
    /** @var \WeakMap<AbstractWidget, true> The widgets with a scroll-driven animation */
    private \WeakMap $scrollAnimated;
    /** @var \WeakMap<AbstractWidget, string> Where the timelines of the scroll-driven animations of each widget were, last computed */
    private \WeakMap $timelineFractions;
    /** @var (\Closure(AbstractWidget): ?int)|null The height of a widget as its content makes it, in the last frame */
    private ?\Closure $rowsOf = null;
    private bool $timelinesMoved = false;
    /** @var list<ContainerWidget> The scroll containers with a scroll-marker-group */
    private array $markerScrollers = [];

    /** @var list<AbstractWidget> The widgets that are size containers, found by the last cascade */
    private array $containerWidgets = [];

    private bool $restyled = false;
    private ?MediaContext $media = null;
    private int $rootRevision = -1;
    private bool $rulesChanged = true;

    // Matching

    /** @var array<int, list<int>>|null Rules without state pseudo-classes, by widget object id; null when stale */
    private ?array $staticMatches = null;

    /** @var array{array<string, string>, array<string, string>}|null The declarations of the last parent, and those its children inherit, see inherit() */
    private ?array $inheritable = null;

    /** @var array{\Dom\Document, array{array<string, true>, array<string, true>, array<string, true>}, array<string, list<string>>, 3?: array<string, list<int>>}|null The document of the elements mayDeclare() was last asked of, held, its names, and the selectors that may match in it by property */
    private ?array $declaringIn = null;

    /**
     * The selectors of the elements that may have a ::before or ::after with content, for the number of rules.
     *
     * @var array{int, list<string>}|null
     */
    private ?array $contentPseudos = null;

    /** @var array<string, array<string, bool>> Whether a value without var() is of a syntax, by syntax, see ofSyntax() */
    private array $syntaxMatches = [];

    /** @var array<int, array<string, string>> What the children of each declarations, by id, inherit, see inherit() */
    private array $inheritableById = [];

    /** @var array<int, list<\Dom\Element>> The elements each rule of every state matches, by its index, see match() */
    private array $staticRuleElements = [];
    /** @var array<string, list<array{list<array<string, true>>, list<string>, list<string>}>|null> What each selector needs in the document, see requiredTags() */
    private array $requiredTags = [];

    /** @var array<int, list<int>> */
    private array $staticElementMatches = [];

    /** @var array<int, array<int, list<int>>> Rules matching inline elements, by widget object id and node index */
    private array $staticInlineMatches = [];

    /** @var array<int, array<int, list<int>>> */
    private array $staticInlineElementMatches = [];

    /** @var array{array<int, array<string, list<int>>>, array<int, array<string, list<int>>>} The rules matching the parts of widgets (see PartsInterface), static ones: the parts, their pseudo-elements */
    private array $staticPartMatches = [[], []];

    /** @var array{array<int, array<string, list<int>>>, array<int, array<string, list<int>>>} Those involving states */
    private array $dynamicPartMatches = [[], []];

    /** @var array<int, array<string, \Dom\Element>> The element of each part, by widget object id */
    private array $partElements = [];

    /** @var \WeakMap<AbstractWidget, array{list<InlineNode>, list<\Dom\Element>, string, string}> The nodes, their elements, and the language and direction they inherit */
    private \WeakMap $inlineMirrors;
    /** @var \WeakMap<AbstractWidget, array{\Dom\Element, list<\Dom\Element>, \Closure(AbstractWidget, array<int, bool>): void, ?int, array<int, list<int>>, ?array<int, bool>, bool}> The elements a widget draws whose display the cascade decides, like the spans of the label of a button, see followDisplay(): the element of the widget, them, what to call when which are displayed changes, the rule count and the rules that may declare their display, by object id, which were displayed at the last cascade, and whether a rule of a state may declare their display */
    private \WeakMap $displayFollowers;

    // The cascade

    /** @var list<array<string, string>> Computed declarations, by id */
    private array $declarations = [];

    /** @var array<string, int> Computed declarations ids, by matched rules, inline style and parent */
    private array $declarationIds = [];

    /** @var array<int, Style|null> Styles by computed declarations id */
    private array $stylesByDeclarations = [];

    /** @var array<int, Style|null> Styles of text blocks by computed declarations id, see textStyle() */
    private array $textStylesByDeclarations = [];
    /** @var array<int, array{int, int}> The padding columns of text blocks hanging punctuation goes over, by computed declarations id, see textStyle() */
    private array $hangs = [];
    /** @var array<string, array{list<string>, bool}> The texts of each custom highlight and whether their case matters, in the order they were registered */
    private array $highlights = [];
    /** @var list<array{AbstractWidget, string}> The text blocks a text fragment goes to, and the pattern of its text */
    private array $targetTexts = [];
    /** Whether the highlights changed since the styles were computed */
    private bool $highlightsChanged = false;
    /** @var array<int, bool> Whether a stop or a comma ending a line goes over the right padding, like hanging-punctuation: allow-end, by declarations */
    private array $hangEnds = [];

    /** @var array<string, list<Style>> */
    private array $elementStyleCache = [];

    /** @var \WeakMap<AbstractWidget, Style> */
    private \WeakMap $styles;

    /** @var \WeakMap<AbstractWidget, int> */
    private \WeakMap $widgetDeclarations;

    /** @var \WeakMap<AbstractWidget, list<int>> Indexes of the pseudo-element rules matching each widget */
    private \WeakMap $elementRules;

    /** @var array<string, list<array{float, array<string, string>}>> */
    private array $keyframes = [];

    /** @var array<string, array{bool, ?string, string}> The custom properties registered by @property, see CssParser::takeRegisteredProperties() */
    private array $registered = [];

    /** The predefined counter styles and those of the @counter-style rules */
    private CounterStyles $counterStyles;

    /** @var array{array<int, list<int>>, array<int, list<int>>, array<int, array<int, list<int>>>, array<int, array<int, list<int>>>}|null The dynamic matches of the last cascade */
    private ?array $lastDynamic = null;

    /** @var \WeakMap<AbstractWidget, array{array<string, list<array{int, int}>>, int, int}> The counters and quote depth where each animated widget is */
    private \WeakMap $countersAt;

    /**
     * What each widget was computed from in the last cascade, and what it left: the declarations of its parent,
     * whether it was inside a hidden one, the counters and quote depth it was reached with and those after it, and
     * the containers and scroll containers with markers among it and its descendants.
     *
     * @var \WeakMap<AbstractWidget, array{int, bool, array<string, list<array{int, int}>>, int, int, array<string, list<array{int, int}>>, int, int, list<AbstractWidget>, list<ContainerWidget>}>
     */
    private \WeakMap $computed;

    /** @var array<int, true>|null In a cascade after states changed only, the widgets whose matches changed and their ancestors, by object id: the others are computed again only when what they inherit changed */
    private ?array $restyling = null;

    /** @var \WeakMap<AbstractWidget, array<string, array{string, float}>> The animations of each animated widget, by name and occurrence, and when each started */
    private \WeakMap $animationStarts;

    /** @var \WeakMap<AbstractWidget, array{values: array<string, string>, running: array<string, array{string, string, float, float, float, array{0: string, 1: int, 2: 'start'|'end'|'none'|'both', 3?: list<float>, points?: list<array{float, float}>}}>, shown: bool}> The values last computed for the transitioned properties of each widget, its running transitions, and whether it was displayed */
    private \WeakMap $transitions;

    /** @var \WeakMap<AbstractWidget, bool> Whether each control draws its own look, see appearance */
    private \WeakMap $nativeAppearances;

    /** @var array<string, list<string>> The selectors of the rules that declare a property with a value of a pattern, see mayDeclare() */
    private array $declaring = [];
    /** @var array<string, list<int>> The rules of every media and state that declare some properties, see cascadeInEveryMedia() */
    private array $declaringRules = [];
    /** The properties the shapes of an <svg> are drawn with, which their presentation attributes set too */
    private const SVG_PROPERTIES = ['fill', 'stroke', 'stroke-width', 'fill-opacity', 'stroke-opacity', 'fill-rule', 'opacity', 'display', 'visibility', 'color', 'stop-color', 'stop-opacity', 'font-size', 'text-anchor', 'stroke-dasharray', 'stroke-dashoffset', 'stroke-linecap', 'stroke-linejoin', 'stroke-miterlimit', 'marker', 'marker-start', 'marker-mid', 'marker-end', 'paint-order', 'clip-path', 'clip-rule', 'mask', 'mask-type', 'filter', 'flood-color', 'flood-opacity'];
    /** The geometry of the shapes of an <svg> the style sheets may set, over their attributes, which are not read as hints: those of the shape stand when no rule sets one */
    private const SVG_GEOMETRY = ['x', 'y', 'width', 'height', 'cx', 'cy', 'r', 'rx', 'ry', 'd'];
    /** The rule count the caches of the shapes of an <svg> are for */
    private ?int $svgRulesCount = null;
    /** @var array{\Dom\Document, list<int>, \SplObjectStorage<\Dom\Element, list<int>>|null, list<int>|null}|null The document read last, held: the DOM makes another object for one no one holds, which a weak map would not know; the indexes of the rules that may style the shapes of its <svg>, see svgRulesOf(), the rules each of its elements matches, see svgMatchesOf(), and those that may match an element out of it, by the names they need */
    private ?array $svgDocument = null;
    /** @var list<int>|null Those of the shapes out of a document */
    private ?array $svgLooseRules = null;
    /** @var \WeakMap<\Dom\Element, array{array<string, string>, array<string, string>}>|null What the cascade gives each element svgDeclarations() asks of, and the custom properties it inherits or sets */
    private ?\WeakMap $svgCascade = null;
    private ?string $svgMedia = null;

    /** @var array<string, array<string, string>> The declarations of the @position-try rules, by name */
    private array $positionTries = [];

    /** @var array<string, bool> Whether the computed declarations of a key use attr(), sibling-index() or sibling-count(), which depend on the element */
    private array $elementDependent = [];

    /** Whether a rule has a style query, @container style(...) */
    private bool $hasStyleQueries = false;

    /** @var list<int> The declarations of the ancestors of the widget computed, from the root */
    private array $ancestry = [];

    /** Whether a rule is in a @starting-style */
    private bool $hasStartingRules = false;

    /** Whether the widget computed is in one that is not displayed */
    private bool $insideHidden = false;

    /** @var \WeakMap<AbstractWidget, true> The widgets an animation or a transition changes as time goes by */
    private \WeakMap $moving;

    private bool $animating = false;

    /** @var \Closure(): float */
    private \Closure $clock;

    /** @var (\Closure(): void)|null */
    private ?\Closure $onAnimate = null;

    /** The language quotes: auto takes the marks of, for the generated content being computed */
    private string $quoteLanguage = '';
    /** The patterns that break the words of text with hyphens: auto, by their language */
    private HyphenatorRegistry $hyphenators;

    /** @var array<int|string, string> The language of each mirrored widget, by object id, and of each inline node, by id and index; "" when unknown */
    private array $languages = [];

    /** @var \WeakMap<AbstractWidget, true> The anonymous text blocks, around text between blocks: their inline elements are children of their parent */
    private \WeakMap $anonymous;

    /** @var \WeakMap<AbstractWidget, true> The rows of the inline content of an element flex in some media only */
    private \WeakMap $itemRows;

    /** @var array<int, true> The declarations of an <html> or <body> whose height their content overflows, by id */
    private array $documentHeights = [];

    /** @var \WeakMap<AbstractWidget, true> The anonymous rows of the cells of a table without rows, see setTableRow() */
    private \WeakMap $tableRows;

    /** @var \WeakMap<AbstractWidget, list<string>> The classes of an element in the order of its attribute, see describe() */
    private \WeakMap $classOrders;

    /** @var \WeakMap<AbstractWidget, true> The items of those rows the text has a space before */
    private \WeakMap $spacedItems;

    /** @var \WeakMap<AbstractWidget, string> The elements lined up in such a row that are blocks in some media only, by their display without a rule */
    private \WeakMap $lineItems;

    /** The widget whose ::before and ::after are being computed, which keeps those out of the flow */
    private ?AbstractWidget $pseudoOwner = null;

    /** @var \WeakMap<AbstractWidget, array<'before'|'after', array{string, Style}>> The absolute and fixed ::before and ::after of each widget */
    private \WeakMap $positionedPseudos;

    /** Whether a declaration uses container units, which are known after the layout */
    private bool $usesContainerUnits = false;

    /** The bytes of the images read, see loadImage() */
    private int $imageBytes = 0;

    /** @var array<string, ?RasterImage> The images of url(), by address */
    private array $images = [];

    public function __construct(
        private readonly ?StylesheetLoaderInterface $loader = null,
        private readonly ?LoggerInterface $logger = null,
        private readonly bool $strict = false,
        ?DeclarationMapper $mapper = null,
        ?HyphenatorRegistry $hyphenators = null,
    ) {
        $this->hyphenators = $hyphenators ?? new HyphenatorRegistry();
        $this->mapper = $mapper ?? new DeclarationMapper($logger, $strict);
        $this->mapper->setImageLoader($this->loadImage(...));
        $this->counterStyles = new CounterStyles();
        $this->parser = new CssParser($loader, $logger, $strict, $this->mapper);
        $this->descriptions = new \WeakMap();
        $this->internalClasses = new \WeakMap();
        $this->positionedPseudos = new \WeakMap();
        $this->anonymous = new \WeakMap();
        $this->itemRows = new \WeakMap();
        $this->tableRows = new \WeakMap();
        $this->classOrders = new \WeakMap();
        $this->spacedItems = new \WeakMap();
        $this->lineItems = new \WeakMap();
        $this->containerSizes = new \WeakMap();
        $this->scrollStates = new \WeakMap();
        $this->scrollAnimated = new \WeakMap();
        $this->timelineFractions = new \WeakMap();
        $this->animationStarts = new \WeakMap();
        $this->transitions = new \WeakMap();
        $this->nativeAppearances = new \WeakMap();
        $this->moving = new \WeakMap();
        $this->countersAt = new \WeakMap();
        $this->computed = new \WeakMap();
        $this->clock = static fn (): float => microtime(true);
        $this->synced = new \WeakMap();
        $this->styles = new \WeakMap();
        $this->widgetDeclarations = new \WeakMap();
        $this->elementRules = new \WeakMap();
        $this->inlineMirrors = new \WeakMap();
        $this->displayFollowers = new \WeakMap();

        // Text properties: applied to inline content, see formatText()
        $noop = static fn (Style $style) => $style;
        $keywords = static fn (string ...$allowed) => static fn (Style $style, string $value) => \in_array(strtolower($value), $allowed, true) ? $style : false;
        $this->mapper
            ->addProperty('container-type', static fn (Style $style, string $value) => null !== self::containerTypes($value) ? $style : false, Support::supported('size and inline-size make the element a container for @container size queries; a query on the block axis (height, block-size, aspect-ratio, orientation) skips the inline-size containers for a size one further up; scroll-state, alone or with one of them, for scroll-state() queries.'))
            ->addProperty('container-name', static fn (Style $style, string $value) => preg_match('/^(?:none|(?:-?[a-z_][\w-]*\s*)+)$/i', trim($value)) ? $style : false, Support::supported())
            ->addProperty('container', static fn (Style $style, string $value) => preg_match('/^(?:-?[a-z_][\w-]*\s*)*(?:\/(.*))?$/is', trim($value), $m) && (!isset($m[1]) || null !== self::containerTypes($m[1])) ? $style : false, Support::supported('<name> / <type>.'))
            ->addProperty('color-scheme', static fn (Style $style, string $value) => self::isColorScheme($value) ? $style : false, Support::supported('light or dark alone forces that scheme on the element and its descendants for light-dark(); light dark, both, and normal follow prefers-color-scheme.'))
            ->addProperty('quotes', static fn (Style $style, string $value) => null !== self::quotePairs($value) ? $style : false, Support::supported('auto, the marks of the language of the element (lang, inherited: « » in French, „ “ in German, 「 」 in Japanese...; English ones for the others), none, or pairs of strings by nesting level, for open-quote and close-quote.'))
            ->addProperty('vertical-align', static fn (Style $style, string $value, MediaContext $media) => \in_array(strtolower(trim($value)), ['baseline', 'super', 'sub', 'top', 'middle', 'bottom', 'text-top', 'text-bottom'], true) || preg_match('/^-?\d*\.?\d+%$/', trim($value)) || null !== Length::toCells($value, $media) ? $style : false, Support::partial('On table cells, top, middle (the default of td and th, like HTML) and bottom place the content in the height of the row, or of the rows a cell spans; baseline is like top. On inline elements, super and sub write their text with superscript or subscript characters, when Unicode has all of them (digits, signs, some letters); the other values, lengths and percentages keep the text on the line.'))
            ->addProperty('line-height', static fn (Style $style, string $value, MediaContext $media) => null !== self::lineGap($value, $media) ? $style : false, Support::partial('Numbers, lengths and percentages are rounded to rows: line-height: 2 puts a blank row between the lines of a text block; normal and below 2, like the 1.5 to 1.75 of the text of many pages, none.'))
            ->addProperty('letter-spacing', static fn (Style $style, string $value, MediaContext $media) => null !== self::letterSpacing($value, $media) ? $style : false, Support::partial('Lengths rounded to columns, a column from three quarters of one, so that the .1em of a page is none, between the characters of a run; negative values are 0.'))
            ->addProperty('scroll-initial-target', $keywords('none', 'nearest'), Support::partial('nearest: its scroll containers start scrolled to it, after the focus is first given, the first element of the document a rule or its style attribute sets it on, in any media or state; a later rule setting none back is not seen, and the page changing does not scroll again.'))
            ->addProperty('pointer-events', $keywords('auto', 'none', 'all', 'visible', 'visiblepainted', 'visiblefill', 'visiblestroke', 'painted', 'fill', 'stroke'), Support::supported('none lets clicks and hover through to the element behind, see HtmlView::widgetAt(); the SVG values are auto.'))
            ->addProperty('cursor', static fn (Style $style, string $value) => \in_array(strtolower(trim((string) preg_replace('/^.*,/s', '', $value))), self::CURSORS, true) ? $style : false, Support::partial('The keyword, for HtmlView::cursorAt(): the application sets the pointer shape (OSC 22); url() images are skipped for the fallback keyword.'))
            ->addProperty('accent-color', static fn (Style $style, string $value, MediaContext $media) => 'auto' === strtolower(trim($value)) || false !== Value::color($value, $media) ? $style : false, Support::supported('Colors the box of checkboxes and radios and the filled part of progress bars.'))
            ->addProperty('counter-reset', $noop, Support::supported('With the CSS scoping: a counter is seen by the element, its descendants and its following siblings.'))
            ->addProperty('counter-increment', $noop, Support::supported())
            ->addProperty('counter-set', $noop, Support::supported())
            ->addProperty('content', $noop, Support::partial('On ::before and ::after of text blocks and inline elements, and on li::marker: strings, attr(), counters, quotes and url() images, drawn in the line a row tall and as wide as their proportions say (nothing for one that cannot be read); ::before and ::after of a block holding blocks are lines of their own, before and after them, in the style of the pseudo-element (inline in its text when it starts or ends with text); in a flex row or a grid, items of their own, placed like the others.'))
            ->addProperty('list-style-type', $noop, Support::supported('A counter style: disc, circle, square, disclosure-open, disclosure-closed, decimal, decimal-leading-zero, lower/upper-alpha, lower/upper-latin, lower/upper-roman, lower-greek, cjk-decimal, the other predefined styles of CSS Counter Styles (the digits of arabic-indic, persian, devanagari, bengali, thai and the other scripts, armenian, georgian, hebrew, hiragana, katakana and their iroha order, cjk-earthly-branch and cjk-heavenly-stem, ethiopic-numeric, the Japanese, Korean and Chinese long forms), or one of a @counter-style rule (an unknown one is decimal); none, or a string.'))
            ->addProperty('list-style', $noop, Support::partial('The type, the position and a url() image, see list-style-image.'))
            ->addProperty('list-style-image', $noop, Support::partial('A url() image is the marker, a row tall and as wide as it says, from 1 to 4 columns, in half blocks, before a space; the list-style-type when it cannot be loaded, with forced colors, or a gradient.'))
            ->addProperty('text-transform', static fn (Style $style, string $value) => null !== self::transformKeywords($value) ? $style : false, Support::supported('none, or a case (uppercase, lowercase, capitalize) with full-width (the ASCII characters as their full-width forms) and full-size-kana (small kana as their full-size forms), one after the other.'))
            ->addProperty('font-variant', static fn (Style $style, string $value) => null !== self::fontVariant($value) ? $style : false, Support::partial('small-caps draws lowercase letters as Unicode small capitals (ᴀʙᴄ), all-small-caps every letter; super and sub write the text with superscript or subscript characters, see font-variant-position; diagonal-fractions, ordinal and slashed-zero, see font-variant-numeric; the other values of the shorthand (ligatures, figures, alternates, east Asian forms, emoji) are accepted, the terminal choosing the glyphs of its font.'))
            ->addProperty('font-variant-caps', $keywords('normal', 'small-caps', 'all-small-caps'), Support::partial('See font-variant.'))
            ->addProperty('font-variant-numeric', static fn (Style $style, string $value) => null !== self::numericVariant($value) ? $style : false, Support::partial('diagonal-fractions writes 1/2 as ½, and the other fractions with superscript and subscript digits (⁵⁄₁₆); ordinal writes the lowercase letters right after a number as superscript ones (1ˢᵗ, 2ⁿᵈ); slashed-zero draws 0 with a slash (0̸); the figure widths and styles (lining, oldstyle, proportional, tabular) and stacked fractions are left to the font of the terminal.'))
            ->addProperty('font-variant-position', $keywords('normal', 'super', 'sub'), Support::partial('super and sub write the text with superscript or subscript characters when Unicode has all of them (digits, signs, most lowercase letters), like vertical-align on an inline element, which takes precedence; otherwise the text stays on the line.'))
            ->addProperty('white-space', static fn (Style $style, string $value) => null !== self::whiteSpaceValue($value) ? $style : false, Support::partial('normal, nowrap (truncates), pre, pre-wrap, pre-line and break-spaces (like pre-wrap), and the pairs of white-space-collapse and text-wrap-mode, like preserve nowrap; nowrap on an inline element keeps its words on one line, unless longer than it; on a textarea, pre and nowrap keep each line on one row, scrolled sideways to the cursor.'))
            ->addProperty('text-indent', static fn (Style $style, string $value, MediaContext $media) => null !== self::textIndent($value, $media) ? $style : false, Support::partial('Lengths and percentages of the width of the text block, hanging and each-line; a line pulled out by a negative length goes over the left padding, as far as there is; when a line is pulled out, the first line wraps as narrow as the others; one line pulled out further than the block is wide shows past the pull, like the text of a link replaced by a logo, which shows none; a first line pushed in as far as the block is wide holds its first word out of sight.'))
            ->addProperty('list-style-position', static fn (Style $style, string $value) => \in_array(strtolower(trim($value)), ['inside', 'outside'], true) ? $style : false, Support::supported('outside, the default: the marker before the first line of an item, its other lines aligned after it; inside: the marker is the start of the text, the lines wrapping under it.'))
            ->addProperty('field-sizing', static fn (Style $style, string $value) => \in_array(strtolower(trim($value)), ['fixed', 'content'], true) ? $style : false, Support::supported('content lets a textarea grow with its content past its rows, and makes a text input as wide as its value (or its placeholder) and the cursor, within its width; fixed, the default, keeps a textarea at its rows, scrolling, and an input at its width.'))
            ->addProperty('appearance', static fn (Style $style, string $value) => \in_array(strtolower(trim($value)), ['none', 'auto', 'menulist-button', 'textfield', 'button', 'checkbox', 'radio', 'menulist', 'listbox', 'searchfield', 'textarea', 'push-button', 'slider-horizontal', 'square-button', 'meter', 'progress-bar', 'base-select', 'base'], true) ? $style : false, Support::partial('none draws a button without its brackets, a checkbox or radio without its box (only its ::before and ::after content, like a ✓ from input:checked::before; without content, a box of CSS too short for its border, like the 1em one of Bootstrap, is a mark, ☐ ☑ or ○ ◉, in the color of its border, or of its background once checked) a select without its ▾ marker and a textarea without the rules above and below its text, which a border the page gives it replaces too, like the textareas of Bootstrap; base-select draws a select as a dropdown without the brackets of its field, its ::picker-icon kept and its ::picker(select) styled; the other values draw their own look.'))
            ->addProperty('-webkit-appearance', static fn (Style $style, string $value) => '' !== trim($value) ? $style : false, Support::partial('Like appearance.'))
            ->addProperty('word-spacing', static fn (Style $style, string $value, MediaContext $media) => 'normal' === strtolower(trim($value)) || null !== self::letterSpacing($value, $media) ? $style : false, Support::partial('Lengths rounded to columns, a column from three quarters of one, added to each space between the words of a text block or of an inline element, the space before an element taking the spacing around it; negative values are 0.'))
            // An alignment of the last lines needs the lines aligned: left, unless text-align says otherwise
            ->addProperty('text-align-last', static fn (Style $style, string $value) => \in_array($value = strtolower(trim($value)), ['auto', 'start', 'end', 'left', 'right', 'center', 'justify'], true) ? ('auto' !== $value && null === $style->getTextAlign() ? $style->withTextAlign(TextAlign::Left) : $style) : false, Support::supported('The last line of each paragraph of a text block, and a paragraph of one line within the widest.'))
            ->addProperty('text-justify', $keywords('auto', 'none', 'inter-word', 'inter-character', 'distribute'), Support::supported('How text-align: justify fills the lines: inter-word stretches the spaces between words, inter-character (and distribute) the room between their characters too, none does not justify, auto like inter-word, and like inter-character for a line without spaces of wide (CJK) characters.'))
            ->addProperty('white-space-collapse', static fn (Style $style, string $value) => \in_array(strtolower(trim($value)), ['collapse', 'preserve', 'preserve-breaks', 'break-spaces', 'preserve-spaces'], true) ? $style : false, Support::partial('collapse, preserve and preserve-breaks, with text-wrap-mode, like the white-space values they make; break-spaces and preserve-spaces are like preserve.'))
            ->addProperty('ruby-position', $keywords('over', 'under', 'alternate', 'inter-character'), Support::partial('over: the annotations of a ruby on a row above the line of their bases, under: on a row below it; alternate is like over, and inter-character too, an annotation not being drawn beside each character.'))
            ->addProperty('ruby-align', $keywords('start', 'center', 'space-between', 'space-around'), Support::partial('Where an annotation narrower than its base is over it, and a base narrower than its annotation under it, the room shared on both sides: start at the start, the others centered, the spaces between the characters not stretched.'))
            ->addProperty('hyphenate-character', static fn (Style $style, string $value) => null !== self::hyphenateCharacter($value) ? $style : false, Support::supported('auto, a hyphen, or a string, ending a line broken at a soft hyphen when it fits.'))
            ->addProperty('text-autospace', static fn (Style $style, string $value) => null !== self::autospace($value) ? $style : false, Support::partial('A space between ideographs (Han, kana, bopomofo) and Latin, Greek or Cyrillic letters (ideograph-alpha) or digits (ideograph-numeric), across inline elements too: a whole column, where browsers add an eighth of an em, so the initial value is no-autospace and normal or auto turns it on; punctuation, insert and replace are accepted.'))
            ->addProperty('hanging-punctuation', static fn (Style $style, string $value) => null !== self::hangingPunctuation($value) ? $style : false, Support::partial('first: an opening bracket or quote starting a text block goes over its left padding; last: a closing one ending it over its right padding, the line before taking the last word when it then fits; as far as two columns of padding there are; aligned text is aligned with it; allow-end: a stop or a comma ending a line goes over the right padding when only it would not fit, the line keeping it; force-end is like allow-end.'))
            ->addProperty('initial-letter', static fn (Style $style, string $value) => 'normal' === strtolower(trim($value)) || null !== self::initialLetter($value) ? $style : false, Support::partial('On ::first-letter: a drop cap, the letter drawn in a bundled FIGlet font as many rows tall as its size (mini for 3, small for 4, standard for 5, big from 6, or the one its font-family names), the first lines of the text beside it; below 3 the letter alone, its lines kept; raise is like drop, nothing being drawn above the text.'))
            ->addProperty('scroll-marker-group', $keywords('none', 'before', 'after'), Support::partial('before or after: the ::scroll-marker of the children of a scroll container on a row inside it, before or after the content, styled by ::scroll-marker-group; a click on one scrolls to its child; they do not take the focus.'))
            ->addProperty('interpolate-size', $keywords('numeric-only', 'allow-keywords'), Support::partial('allow-keywords: a height transitions from or to auto, fit-content, min-content or max-content, through the height of the content in the last frame; not the widths.'))
            ->addProperty('text-overflow', $noop, Support::supported('clip, ellipsis (…) or a string, on lines that do not wrap.'));
    }

    /**
     * Tells whether a rule, in any media, or the style attribute can make
     * a source element a flex or grid container.
     *
     * The HTML builder turns the inline children of such an element into
     * flex or grid items, like CSS blockifies them. Rules that depend on widget
     * states are not considered.
     *
     * @internal
     */
    public function mayBlockifyChildren(\Dom\Element $element): bool
    {
        // Without a box (display: contents), its children are those of its parent; displayed like its parent
        // (display: inherit), a flex container inside one
        if ($this->mayDisplay($element, '(?:contents|inherit)\b') && null !== ($parent = $element->parentElement) && $this->mayBlockifyChildren($parent)) {
            return true;
        }

        return $this->mayDisplay($element, '(?:inline-)?(?:flex|grid)\b');
    }

    /**
     * Whether the rules in every media and state make the children of an element items, like the display: flex of
     * .d-flex, not those of a media only, like a menu flex on a narrow screen, nor a flex element displayed otherwise
     * in one.
     *
     * @internal
     */
    public function alwaysBlockifiesChildren(\Dom\Element $element): bool
    {
        $display = strtolower(trim($this->cascadeInEveryMedia($element, ['display'])['display'] ?? ''));
        // Without a box, or displayed like its parent: as its parent does
        if (preg_match('/^(?:contents|inherit)\b/', $display) && null !== $parent = $element->parentElement) {
            return $this->alwaysBlockifiesChildren($parent);
        }

        // Nor displayed otherwise in a media or a state, like the d-flex d-md-block of Bootstrap
        return preg_match('/^(?:inline-)?(?:flex|grid)\b/', $display) && !$this->mayDeclare($element, 'display', '(?!(?:inline-)?(?:flex|grid)\b|none\b|contents\b|inherit\b)[a-z]', true, conditional: true);
    }

    /**
     * Whether some rule or the style attribute may give an element a display matching a pattern.
     */
    private function mayDisplay(\Dom\Element $element, string $pattern): bool
    {
        return $this->mayDeclare($element, 'display', $pattern);
    }

    /**
     * Tells whether a rule, in any media, or the style attribute can position a source element out of the flow, like
     * position: absolute.
     *
     * The HTML builder makes such an inline element a widget of its own, that it can be placed.
     *
     * @internal
     */
    public function mayBeOutOfFlow(\Dom\Element $element): bool
    {
        // Visually hidden, like .sr-only: not drawn, it does not split the text it is in
        return $this->mayDeclare($element, 'position', '(?:absolute|fixed)\b', true)
            && !$this->mayDeclare($element, 'clip', 'rect\(') && !$this->mayDeclare($element, 'clip-path', 'inset\(');
    }

    /**
     * Whether a rule of every state or the style attribute floats an element to the right, like a source link at the
     * end of a summary.
     *
     * @internal
     */
    public function mayFloatRight(\Dom\Element $element): bool
    {
        return $this->mayDeclare($element, 'float', '(?:right|inline-end)\b');
    }

    /**
     * Whether some rule or the style attribute may float an element, like the <span class="float-end"> of Bootstrap:
     * an inline one is then laid out apart from the text around it, at its side.
     *
     * @internal
     */
    public function mayFloat(\Dom\Element $element, bool $anyState = true): bool
    {
        return $this->mayDeclare($element, 'float', '(?:left|right|inline-start|inline-end)\b', $anyState);
    }

    /**
     * Tells whether a rule that holds in every state, or the style attribute, positions a source element out of the
     * flow: the text around it then flows on, like CSS, the element drawn over it.
     *
     * @internal
     */
    public function staysOutOfFlow(\Dom\Element $element): bool
    {
        return $this->mayDeclare($element, 'position', '(?:absolute|fixed)\b')
            && !$this->mayDeclare($element, 'clip', 'rect\(') && !$this->mayDeclare($element, 'clip-path', 'inset\(');
    }

    /**
     * Marks a text block as anonymous, like the one around text between blocks, or a container, like the row of the
     * controls in the line of text: no element of the document, its inline elements or the elements of its children
     * are children of its parent for selectors, like :first-child or input + .unit.
     *
     * @internal
     */
    public function setAnonymous(AbstractWidget $widget): void
    {
        $this->anonymous[$widget] = true;
    }

    /**
     * Marks the row the HTML builder lines up the inline content of an element flex in some media only in: no box
     * where that element is flex, its widgets the items.
     *
     * @internal
     */
    public function setItemRow(AbstractWidget $widget): void
    {
        $this->itemRows[$widget] = true;
    }

    /**
     * Marks an item of such a row that the text has a space before: a column apart from the one before it where the
     * row lines them up.
     *
     * @internal
     */
    public function setSpacedItem(AbstractWidget $widget): void
    {
        $this->spacedItems[$widget] = true;
    }

    /**
     * Marks a block lined up with the inline content around it, inline-block in some media only: a line of its own in
     * that row where it is a block.
     *
     * @internal
     */
    public function setLineItem(AbstractWidget $widget, string $display = 'block'): void
    {
        $this->lineItems[$widget] = $display;
    }

    /**
     * Whether some rule may display a block element inline-block, inline-flex or inline-grid, like on a wide screen
     * only.
     *
     * @internal
     */
    public function mayBeInlineLevel(\Dom\Element $element): bool
    {
        return $this->mayDeclare($element, 'display', 'inline-(?:block|flex|grid)\b');
    }

    /**
     * Whether some rule may display an element as a table, or as a cell, which the HTML builder puts in an anonymous
     * row when its parent is a table, like browsers.
     *
     * @internal
     */
    public function mayDisplayAsTable(\Dom\Element $element, bool $cell = false): bool
    {
        return $this->mayDisplay($element, $cell ? 'table-cell\b' : '(?:inline-)?table\b(?!-)');
    }

    /**
     * Marks the anonymous row the HTML builder puts the cells of a table without rows in: a row where its parent is a
     * table, no box where it is not, like the .row of htmx displayed as a table on a wide screen only.
     *
     * @internal
     */
    public function setTableRow(AbstractWidget $widget): void
    {
        $this->tableRows[$widget] = true;
    }

    /**
     * Whether the rules in every media leave an inline element inline, a block in a media only, like the links of the
     * header of GNU Emacs, blocks on a narrow screen.
     *
     * @internal
     */
    public function staysInlineInEveryMedia(\Dom\Element $element): bool
    {
        $display = strtolower(trim($this->cascadeInEveryMedia($element, ['display'])['display'] ?? ''));

        // Without a display, or one of the keywords back to it: inline; or one in the line, like inline-block
        return '' === $display || \in_array($display, ['initial', 'unset', 'revert', 'revert-layer'], true) || (bool) preg_match('/^inline\b/', $display);
    }

    /**
     * Whether a display lays an element lined up with inline content out as a block, on a line of its own: none, the
     * keywords that go back to the display of the element without a rule, initial (inline) and unset (inline), as its
     * own display, inline for a link, a block for a <li>.
     */
    private static function isLineBlock(?string $display, string $default): bool
    {
        $display = strtolower(trim($display ?? ''));
        $display = match ($display) {
            '', 'revert', 'revert-layer' => $default,
            'initial', 'unset' => 'inline',
            default => $display,
        };

        return !preg_match('/^(?:inline|none|contents)\b/', $display);
    }

    /**
     * Whether some rule may display a block element inline, like the links of the header of Node from 601px.
     *
     * @internal
     */
    public function mayBeInline(\Dom\Element $element): bool
    {
        return $this->mayDeclare($element, 'display', 'inline\b(?!-)');
    }

    /**
     * Whether a widget is the box of an element described, see describe(), not an anonymous one around text between
     * blocks nor one of a tree built without elements.
     *
     * @internal
     */
    public function isElementBox(AbstractWidget $widget): bool
    {
        return isset($this->descriptions[$widget]) && !isset($this->anonymous[$widget]);
    }

    /**
     * @internal
     */
    public function isAnonymous(AbstractWidget $widget): bool
    {
        return isset($this->anonymous[$widget]);
    }

    /**
     * Whether an element keeps its floats in its box as a formatting context of its own, like CSS: displayed as a flow
     * root, an inline block, a table, a flex or grid container, of an overflow other than visible, or cleared by its
     * ::after, like the clearfix of so many pages; the others let them out, beside what follows them.
     *
     * @internal
     */
    public function containsFloats(AbstractWidget $widget): bool
    {
        if ($this->isFormattingContext($widget)) {
            return true;
        }
        foreach ($this->elementRules[$widget] ?? [] as $index) {
            $rule = $this->rules[$index];
            if (self::pseudoElementMatches($rule->selector->pseudoElement, 'after')) {
                foreach ($rule->declarations as $declaration) {
                    if ('clear' === $declaration->property && preg_match('/^\s*(?:both|left|right|inline-start|inline-end)\s*$/i', $declaration->value)) {
                        return true;
                    }
                }
            }
        }

        return false;
    }

    /**
     * Whether an element is a formatting context of its own, like overflow: hidden or display: flow-root: not one only
     * cleared by its ::after, see containsFloats().
     *
     * @internal
     */
    public function isFormattingContext(AbstractWidget $widget): bool
    {
        return self::establishesFormattingContext($this->declarations[$this->widgetDeclarations[$widget] ?? -1] ?? []);
    }

    /**
     * Whether an element of these declarations is a formatting context of its own, which the floats around do not reach
     * and which holds its floats: not display: contents, which has no box, its floats in the block around it; overflow:
     * clip is no formatting context either.
     *
     * @param array<string, string> $declarations
     */
    private static function establishesFormattingContext(array $declarations): bool
    {
        return preg_match('/\b(?:flow-root|inline|table|flex|grid|none)\b/i', trim($declarations['display'] ?? ''))
            || preg_match('/\b(?:hidden|auto|scroll)\b/i', ($declarations['overflow'] ?? '').' '.($declarations['overflow-x'] ?? '').' '.($declarations['overflow-y'] ?? ''))
            || preg_match('/\b(?:paint|layout|strict|content)\b/i', $declarations['contain'] ?? '');
    }

    /**
     * Whether the children of an element are items of a flex or grid container: its own, or one around elements
     * without a box (display: contents) it is in.
     *
     * @param array<string, string> $declarations
     */
    private static function holdsItems(array $declarations): bool
    {
        $display = strtolower(trim($declarations['display'] ?? ''));

        return 1 === preg_match('/\b(?:flex|grid)\b/', $display) || ('contents' === $display && isset($declarations[self::IN_ITEMS]));
    }

    /**
     * The side a ::before displayed as a block clears, see compute(); null for none: not that of an element of a
     * formatting context of its own, which floats outside do not reach, nor an absolute or fixed one, out of the flow.
     *
     * @param list<int>             $elementRules
     * @param array<string, string> $element      The declarations of the element
     * @param array<string, string> $parent       Those of its parent
     */
    private function beforeClear(array $elementRules, array $element, array $parent = []): ?string
    {
        // The rules of a ::before that clears, found once for the rules there are: none of them, nothing cascaded
        $key = 'before-clear:'.\count($this->rules);
        if (null === $clearing = $this->declaringRules[$key] ?? null) {
            $clearing = [];
            foreach ($this->rules as $index => $rule) {
                if (self::pseudoElementMatches($rule->selector->pseudoElement, 'before') && array_any($rule->declarations, static fn (Declaration $declaration): bool => 'clear' === $declaration->property)) {
                    $clearing[] = $index;
                }
            }
            $this->declaringRules[$key] = $clearing;
        }
        if (!$clearing || !array_intersect($elementRules, $clearing)) {
            return null;
        }
        $matching = array_values(array_filter($elementRules, fn (int $index) => self::pseudoElementMatches($this->rules[$index]->selector->pseudoElement, 'before')));
        // An item of a flex or grid container, through elements without a box too, a formatting context of its own
        if (self::holdsItems($parent)) {
            return null;
        }
        if (self::establishesFormattingContext($element)
            || preg_match('/^\s*(?:left|right|inline-start|inline-end)\b/i', $element['float'] ?? '')
            || preg_match('/^\s*(?:absolute|fixed)\b/i', $element['position'] ?? '')) {
            return null;
        }
        $declarations = $this->cascadeDeclarations($matching, []);
        if (!isset($declarations['content']) || preg_match('/^\s*(?:none|normal)\s*$/i', $declarations['content'])
            || !preg_match('/^\s*(?:block|table|flex|grid|flow-root|list-item)\s*$/i', $declarations['display'] ?? '')
            || preg_match('/^\s*(?:absolute|fixed)\b/i', $declarations['position'] ?? '')
            // A float, which its clear moves alone
            || preg_match('/^\s*(?:left|right|inline-start|inline-end)\b/i', $declarations['float'] ?? '')) {
            return null;
        }

        return match (strtolower(trim($declarations['clear'] ?? ''))) {
            'both' => 'both',
            'left', 'inline-start' => 'left',
            'right', 'inline-end' => 'right',
            default => null,
        };
    }

    public function getPositionedPseudoElements(AbstractWidget $widget): array
    {
        $pseudos = $this->positionedPseudos[$widget] ?? null;

        return null !== $pseudos ? $pseudos : [];
    }

    /**
     * Keeps an absolute or fixed ::before or ::after of the widget being styled out of its text, for the renderer
     * to draw it over the frame; whether it is one.
     *
     * @param array<string, string> $declarations
     * @param array<string, string> $attributes
     */
    private function keepsOutOfFlow(string $pseudoElement, array $declarations, array $attributes): bool
    {
        if (null === $this->pseudoOwner || ('before' !== $pseudoElement && 'after' !== $pseudoElement) || !preg_match('/^(?:absolute|fixed)\b/i', trim($declarations['position'] ?? ''))) {
            return false;
        }
        $this->quotes = self::quotePairs($declarations['quotes'] ?? 'auto', $this->quoteLanguage) ?? [];
        $content = $this->parseContent($this->substitute($declarations['content'], $declarations), $attributes);
        // Empty, a triangle drawn by its borders or a dot by its background: that glyph, its box and background not
        // drawn, like the bullets of VitePress placed beside each item
        if ('' === $content && null !== $glyph = $this->emptyBoxGlyph($declarations, $declarations)) {
            $content = $glyph[0];
            // On the row its middle is on, not its top rounded, like the .75rem of a dot of 5px on the first line
            // A row is as tall as a font of 16 CSS pixels, whatever the pixels of the cells
            $rowHeight = CellSize::REFERENCE_HEIGHT;
            if (null !== $top = self::pixels($this->substitute($declarations['top'] ?? '', $declarations))) {
                // Its middle: of its height, or of the borders that draw a triangle
                $border = fn (string $side): float => self::pixels(Value::split($this->substitute($declarations['border-'.$side.'-width'] ?? $declarations['border-'.$side] ?? '', $declarations))[0] ?? '') ?? 0.0;
                $middle = $top + max(self::pixels($this->substitute($declarations['height'] ?? '', $declarations)) ?? 0.0, $border('top') + $border('bottom')) / 2;
                // In the top padding of its element, which rounds to no row: no row of its own, the glyph would cover
                // the text, like the caret of the tab of python.org in its padding of 8px, left out
                if ($middle >= 0 && $middle < $this->topPadding() && 0 === (($this->styles[$this->pseudoOwner] ?? null)?->getPadding()->top ?? 0)) {
                    return true;
                }
                $declarations['top'] = ((int) floor($middle / $rowHeight) * $rowHeight).'px';
            }
            $declarations = self::withoutGlyphBox($declarations);
            if (null !== $glyph[1]) {
                $declarations['color'] = $glyph[1];
            }
        } elseif ('' === $content && $this->paintsBox($declarations)) {
            // Empty, of a size and a background, like a decoration placed beside a heading: its box, drawn
            $content = ' ';
        }
        if (null !== $content && '' !== $content) {
            $pseudos = $this->positionedPseudos[$this->pseudoOwner] ?? [];
            $pseudos[$pseudoElement] = [$content, $this->computeStyle($declarations, $this->media ?? new MediaContext())];
            $this->positionedPseudos[$this->pseudoOwner] = $pseudos;
        }

        return true;
    }

    /**
     * Whether some rule may display an inline element as a block, like a { display: block }: it is laid out apart from
     * the text around it.
     *
     * @internal
     */
    public function mayBeBlock(\Dom\Element $element): bool
    {
        return $this->mayDeclare($element, 'display', '(?:block|flex|grid|table|flow-root)\b', true);
    }

    /**
     * Whether some rule may make an element an inline box with a border, like the display: inline-block and border of
     * the .btn of Bootstrap: drawn boxed in the line, not as text whose line cuts its border to its sides.
     *
     * @internal
     */
    public function mayBeInlineBox(\Dom\Element $element): bool
    {
        if (!$this->mayDeclare($element, 'display', 'inline-(?:block|flex|grid)\b', true)) {
            return false;
        }
        if ($this->mayBeBordered($element)) {
            return true;
        }
        // Its items in a column, like the arrow over the score of the upvoter of lobste.rs: lines of a box
        if ($this->mayDeclare($element, 'display', 'inline-(?:flex|grid)\b', true) && ($this->mayDeclare($element, 'flex-direction', 'column', true) || $this->mayDeclare($element, 'flex-flow', 'column', true))) {
            return true;
        }
        // A width of its own, like the bars of the placeholders of Bootstrap: as wide as it says, its background drawn
        foreach (['width', 'min-width'] as $property) {
            if ($this->mayDeclare($element, $property, '(?!(?:auto|0(?:px)?|fit-content|max-content|min-content)\s*(?:!important)?$)', true)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Whether some rule may draw a border over or under an element: a border of no width is none, like the border: 0
     * solid that the preflight of Tailwind gives every element, and so is one a later rule takes away, like the
     * border-width: 0 of the <code> of the Arch Wiki after the border of every <code>.
     */
    private function mayBeBordered(\Dom\Element $element): bool
    {
        $declared = false;
        foreach (['border', 'border-top', 'border-bottom', 'border-block', 'border-style'] as $property) {
            if ($this->mayDeclare($element, $property, '(?!(?:none|hidden)\b)(?!(?:[^(]*\s)?0(?:px)?(?:\s|$))(?!\s*(?:!important)?$)', true)) {
                $declared = true;
                break;
            }
        }
        if (!$declared && !$this->mayDeclare($element, 'border-width', '(?=.*(?:^|\s)(?:[0-9.]*[1-9]|thin|medium|thick))', true)) {
            return false;
        }
        // In some media or state: may be drawn there
        foreach (['border', 'border-top', 'border-bottom', 'border-block', 'border-style', 'border-width', 'border-top-width', 'border-bottom-width', 'border-block-width'] as $property) {
            if ($this->mayDeclare($element, $property, '', true, conditional: true)) {
                return true;
            }
        }

        // The cascade of the rules in every media and state: their last word on the top and the bottom border
        $properties = ['border' => [true, true], 'border-top' => [true, false], 'border-bottom' => [false, true], 'border-block' => [true, true], 'border-style' => [true, true], 'border-width' => [true, true], 'border-top-width' => [true, false], 'border-bottom-width' => [false, true], 'border-block-width' => [true, true]];
        // Each side drawn with a style and a width, medium unless set: the shorthand resets both
        $styled = [false, false];
        $wide = [true, true];
        foreach ($this->cascadeInEveryMedia($element, array_keys($properties)) as $property => $value) {
            if (null === $sides = $properties[$property] ?? null) {
                continue;
            }
            // The words of the value, a function one, like the space-separated channels of rgb(0 0 0 / 10%)
            $tokens = Value::split(strtolower(trim(preg_replace('/\s*!important\s*$/i', '', $value) ?? $value)));
            foreach ([0, 1] as $side) {
                if (!$sides[$side]) {
                    continue;
                }
                // The top and the bottom of four sides, or of the start and the end of a block
                $own = \in_array($property, ['border-style', 'border-width'], true) ? ($tokens[$side ? 2 : 0] ?? $tokens[0]) : ($tokens[$side] ?? $tokens[0]);
                if (str_ends_with($property, '-width')) {
                    $wide[$side] = !self::isZeroWidth($own);
                } elseif ('border-style' === $property) {
                    $styled[$side] = !\in_array($own, ['none', 'hidden'], true);
                } else {
                    // A shorthand: a style of its words, or one var() may give
                    $styled[$side] = [] !== array_filter($tokens, static fn (string $token) => \in_array($token, ['solid', 'dashed', 'dotted', 'double', 'groove', 'ridge', 'inset', 'outset'], true) || str_contains($token, 'var('));
                    $wide[$side] = [] === array_filter($tokens, self::isZeroWidth(...));
                }
            }
        }

        return ($styled[0] && $wide[0]) || ($styled[1] && $wide[1]);
    }

    /**
     * The cascade for an element of the rules of every media and state that declare one of some properties, with its
     * style attribute: what the builder asks of an element before any widget is styled, like whether it stays inline.
     *
     * @param list<string> $properties
     *
     * @return array<string, string>
     */
    private function cascadeInEveryMedia(\Dom\Element $element, array $properties): array
    {
        // The rules found once for the rules there are, then those that may match in the document of the element, by
        // the names they need: not the thousands of rules of a framework for each element
        $key = 'every:'.implode(',', $properties).':'.\count($this->rules);
        if (null === $indexes = $this->declaringRules[$key] ?? null) {
            $wanted = array_flip($properties);
            $indexes = [];
            foreach ($this->rules as $index => $rule) {
                if (null === $rule->selector->pseudoElement && !$rule->selector->dynamic && self::inEveryMedia($rule) && [] === $rule->containers && !$rule->starting
                    && array_any($rule->declarations, static fn (Declaration $declaration) => isset($wanted[$declaration->property]))) {
                    $indexes[] = $index;
                }
            }
            $this->declaringRules[$key] = $indexes;
        }
        $document = $element->ownerDocument;
        if (null !== $document) {
            if ($document !== ($this->declaringIn[0] ?? null)) {
                $this->declaringIn = [$document, $this->documentNames($document), []];
            }
            $indexes = $this->declaringIn[3][$key] ??= array_values(array_filter($indexes, fn (int $index): bool => $this->mayMatchNames($this->rules[$index]->selector->forHtml(), $this->declaringIn[1])));
        }
        $matching = [];
        foreach ($indexes as $index) {
            try {
                if ($element->matches($this->rules[$index]->selector->forHtml())) {
                    $matching[] = $index;
                }
            } catch (\Throwable) {
            }
        }
        $inline = trim($element->getAttribute('style') ?? '');
        if ([] === $matching && '' === $inline) {
            return [];
        }

        return $this->cascadeDeclarations($matching, '' !== $inline ? $this->parseInline($inline, true) : []);
    }

    /**
     * Whether a rule applies in every media, like one in @media screen, for what is decided before the size of the screen
     * is known, like what is in the line of the text: also on every screen as wide as a terminal at least, like the
     * header of the Node.js docs displayed inline from 601px.
     */
    private static function inEveryMedia(CssRule $rule): bool
    {
        return array_all($rule->media, static fn (MediaQuery $query) => $query->isUnconditional(self::NARROWEST_SCREEN));
    }

    private static function isZeroWidth(string $token): bool
    {
        return (bool) preg_match('/^[+-]?0*\.?0*(?:[a-z]+)?$/', $token) && preg_match('/0/', $token);
    }

    /**
     * Whether some rule may pad an element on its sides, like the padding of the links of the table of contents of MDN.
     *
     * @internal
     */
    public function mayPadSides(\Dom\Element $element): bool
    {
        foreach (['padding', 'padding-left', 'padding-right', 'padding-inline', 'padding-inline-start', 'padding-inline-end'] as $property) {
            if ($this->mayDeclare($element, $property, '(?!(?:0(?:px)?\s*)+(?:!important)?$)', true)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Whether some rule may give an element a ::before or ::after with content, like the separator after each item of
     * the breadcrumbs of Docusaurus.
     *
     * @internal
     */
    public function mayHaveContentPseudo(\Dom\Element $element): bool
    {
        $key = \count($this->rules);
        if ($key !== ($this->contentPseudos[0] ?? null)) {
            $selectors = [];
            foreach ($this->rules as $rule) {
                if ('before' !== $rule->selector->pseudoElement && 'after' !== $rule->selector->pseudoElement) {
                    continue;
                }
                foreach ($rule->declarations as $declaration) {
                    if ('content' === $declaration->property && !preg_match('/^(?:none|normal)\s*(?:!important)?$/i', trim($declaration->value))) {
                        $selectors[$rule->selector->dynamic ? self::withoutStates($rule->selector->forHtml()) : $rule->selector->forHtml()] = true;
                        break;
                    }
                }
            }
            $this->contentPseudos = [$key, array_keys(array_diff_key($selectors, ['' => true]))];
        }
        foreach ($this->contentPseudos[1] as $selector) {
            try {
                if ($element->matches($selector)) {
                    return true;
                }
            } catch (\Throwable) {
            }
        }

        return false;
    }

    /**
     * Whether some rule or the style attribute may display an element otherwise than as a flex or grid container, like
     * the inline-block .checkbox of Bulma on a label the default rules lay out as a row.
     *
     * @internal
     */
    public function mayDisplayOtherwiseThanItems(\Dom\Element $element): bool
    {
        return $this->mayDeclare($element, 'display', '(?!(?:inline-)?(?:flex|grid)\b|none\b|contents\b)[a-z]', true);
    }

    /**
     * Whether a block element is in the line of the text around it, like a <div class="d-inline-block"> of Bootstrap:
     * the rules in every media and state display it inline-block, inline-flex or inline-grid, and none in some media
     * or state as a block.
     *
     * @internal
     */
    public function staysInlineLevel(\Dom\Element $element): bool
    {
        if (!$this->mayDeclare($element, 'display', 'inline-(?:block|flex|grid)\b') || $this->mayDeclare($element, 'display', '(?:block|flex|grid|table|list-item|flow-root)\b', true, conditional: true)) {
            return false;
        }
        // The cascade of the rules in every media and state, like .form-check-inline after .form-check
        $display = trim($this->cascadeInEveryMedia($element, ['display'])['display'] ?? '');

        // Or hidden there, like the items of a pagination of Foundation, displayed inline-block on a wide screen only
        return (bool) preg_match('/^(?:inline-(?:block|flex|grid)|none)\b/i', $display);
    }

    /**
     * Whether a block element is inline, in the line of the text around it, like the <li> of a breadcrumb or a menu
     * displayed inline: the rules in every media and state display it inline, and none in some media or state otherwise.
     *
     * @internal
     */
    public function staysInline(\Dom\Element $element): bool
    {
        if (!$this->mayDeclare($element, 'display', 'inline(?![\w-])') || $this->mayDeclare($element, 'display', '(?:block|flex|grid|table|list-item|flow-root|inline-|ruby|contents)', true, conditional: true)) {
            return false;
        }
        // The cascade of the rules in every media and state, like li.right after .nav li
        $display = trim($this->cascadeInEveryMedia($element, ['display'])['display'] ?? '');

        return (bool) preg_match('/^inline(?![\w-])/i', $display);
    }

    /**
     * Whether some rule may lay the children of an element out as items, like display: flex or grid: its text and its
     * inline elements are then items of their own, not the lines of a text.
     *
     * @internal
     */
    public function mayLayOutItems(\Dom\Element $element): bool
    {
        return $this->mayDeclare($element, 'display', '(?:inline-)?(?:flex|grid)\b', true);
    }

    /**
     * Whether some rule may make an element scroll sideways, like overflow-x: auto.
     *
     * @internal
     */
    public function mayScrollSideways(\Dom\Element $element): bool
    {
        return $this->mayDeclare($element, 'overflow-x', '(?:auto|scroll)\b') || $this->mayDeclare($element, 'overflow-inline', '(?:auto|scroll)\b') || $this->mayDeclare($element, 'overflow', '(?:auto|scroll)\b');
    }

    /**
     * Whether an element may not be drawn: hidden, display: none, or, unless only what is hidden from all counts,
     * clipped to nothing, like .sr-only, which accessible names read.
     *
     * Not one some rule of the page displays again in every media, like display: none, then inline-flex from 400px, which
     * the narrowest screen is as wide as: drawn, the cascade of the page not known when it is built; one from a wider
     * screen only, like the lg:inline of Tailwind, is hidden, like there.
     *
     * @internal
     */
    public function mayBeHidden(\Dom\Element $element, bool $visually = true): bool
    {
        return $element->hasAttribute('hidden')
            // Its style attribute wins; a rule for a state, like :hover, does not display it again
            || preg_match('/(?:^|;)\s*display\s*:\s*none\b/i', $element->getAttribute('style') ?? '')
            || ($this->mayDeclare($element, 'display', 'none\b') && !$this->mayDeclare($element, 'display', '(?!none\b|inherit\b|unset\b|initial\b|revert)[a-z]', false, false, true))
            || ($visually && $this->mayBeVisuallyHidden($element));
    }

    /**
     * Whether an element may be clipped to nothing, like .sr-only, which accessible names read.
     *
     * @internal
     */
    public function mayBeVisuallyHidden(\Dom\Element $element): bool
    {
        return $this->mayDeclare($element, 'clip', 'rect\(') || $this->mayDeclare($element, 'clip-path', 'inset\(');
    }

    /**
     * Whether the display of an element may change with the media or the states: some rule of some media or state may
     * hide it, like .label { display: none } before a media query displays it from a width; not when its hidden
     * attribute or its style attribute hides it in every one.
     *
     * @internal
     */
    public function mayChangeDisplay(\Dom\Element $element): bool
    {
        return !$element->hasAttribute('hidden')
            && !preg_match('/(?:^|;)\s*display\s*:\s*none\b/i', $element->getAttribute('style') ?? '')
            // Not a rule of a container query, which is not followed: hidden, like before the cascade
            && $this->mayDeclare($element, 'display', 'none\b', true, false, false, true);
    }

    /**
     * Follows the display of elements a widget draws that are not widgets, like the spans of the label of a button: at
     * each cascade where which of them are displayed changes, in the media and the states of the widget, $update is
     * called with it, by object id of the element.
     *
     * Rules depending on a state match the states of the widget and of its ancestors, like button:hover .tip or
     * .card:hover .tip; those of container queries are left out.
     *
     * @internal for {@see \Symfony\Component\Tui\Html\Element\ButtonElement}
     *
     * @param \Dom\Element                                     $owner    The element of the widget, in its document
     * @param list<\Dom\Element>                               $elements Its descendants, which the cascade displays or not
     * @param \Closure(AbstractWidget, array<int, bool>): void $update
     */
    public function followDisplay(AbstractWidget $widget, \Dom\Element $owner, array $elements, \Closure $update): void
    {
        $this->displayFollowers[$widget] = [$owner, $elements, $update, null, [], null, false];
    }

    /**
     * Lets go of the documents of elements held to read them faster, like the one mayDeclare() was last asked of: once a
     * document is built, or a view left, not to keep it.
     *
     * @internal
     */
    public function releaseDocuments(): void
    {
        $this->declaringIn = null;
        $this->svgDocument = null;
    }

    /**
     * The rules of every media that may declare the display of an element in some state, like .label in a media
     * query: those of its document matched at once, for the rules there are, not each rule for each element.
     *
     * @param array<int, array{\Dom\Document, \SplObjectStorage<\Dom\Element, list<int>>}> $matches The rules each element of each document read may take its display from, by object id of the document: kept for one cascade only, not to hold a page left
     *
     * @return list<int>
     */
    private function displayRulesOf(\Dom\Element $element, array &$matches): array
    {
        $key = 'display:'.\count($this->rules);
        if (null === $indexes = $this->declaringRules[$key] ?? null) {
            $indexes = [];
            foreach ($this->rules as $index => $rule) {
                if (null === $rule->selector->pseudoElement && [] === $rule->containers && !$rule->starting
                    && array_any($rule->declarations, static fn (Declaration $declaration) => 'display' === $declaration->property || 'all' === $declaration->property)) {
                    $indexes[] = $index;
                }
            }
            $this->declaringRules[$key] = $indexes;
        }
        $document = $element->isConnected ? $element->ownerDocument : null;
        if (null !== $document && !isset($matches[$documentId = spl_object_id($document)])) {
            $names = $this->documentNames($document);
            /** @var \SplObjectStorage<\Dom\Element, list<int>> $found */
            $found = new \SplObjectStorage();
            foreach ($indexes as $index) {
                $rule = $this->rules[$index];
                if (!$this->mayMatchNames($rule->selector->forHtml(), $names)) {
                    continue;
                }
                $selector = $rule->selector->dynamic ? self::withoutStates($rule->selector->forHtml()) : $rule->selector->forHtml();
                try {
                    $elements = '' !== $selector ? Selector::queryAll($document, $selector) : [];
                } catch (\Throwable) {
                    continue;
                }
                foreach ($elements as $match) {
                    $list = $found[$match] ?? [];
                    // A selector list may return an element once per selector it matches
                    if ($index !== end($list)) {
                        $list[] = $index;
                        $found[$match] = $list;
                    }
                }
            }
            // The document held while it is read: its elements keep their objects, which the matches are keyed by
            $matches[$documentId] = [$document, $found];
        }
        if (null !== $document) {
            return $matches[spl_object_id($document)][1][$element] ?? [];
        }

        return array_values(array_filter($indexes, function (int $index) use ($element): bool {
            $rule = $this->rules[$index];
            try {
                return $element->matches($rule->selector->dynamic ? self::withoutStates($rule->selector->forHtml()) : $rule->selector->forHtml());
            } catch (\Throwable) {
                return false;
            }
        }));
    }

    /**
     * Which elements each widget follows the display of are displayed in the media and states of this cascade, see
     * followDisplay().
     */
    private function followDisplays(MediaContext $media, bool $statesOnly): void
    {
        $changes = [];
        $matches = [];
        foreach ($this->displayFollowers as $widget => [$owner, $elements, $update, $count, $candidates, $last, $stateful]) {
            $mirror = $this->elements[spl_object_id($widget)] ?? null;
            if (null === $mirror || !$mirror->isConnected) {
                continue;
            }
            // The rules that may declare their display, found again for other rules
            if ($count !== \count($this->rules) || !$statesOnly) {
                $count = \count($this->rules);
                $candidates = [];
                foreach ($elements as $element) {
                    $candidates[spl_object_id($element)] = $this->displayRulesOf($element, $matches);
                }
                $stateful = array_any(array_merge(...array_values($candidates)), fn (int $index): bool => $this->rules[$index]->selector->dynamic);
            } elseif (null !== $last && !$stateful) {
                // Only states changed, which no rule of them depends on: displayed as they were
                continue;
            }
            // In the states of the widget and of its ancestors, like button:hover .tip or .card:hover .tip, when a rule of
            // a state may declare their display
            $copied = $stateful ? self::copyStates($owner, $mirror) : [];
            $displayed = [];
            try {
                foreach ($elements as $element) {
                    $matching = [];
                    foreach ($candidates[spl_object_id($element)] ?? [] as $index) {
                        $rule = $this->rules[$index];
                        foreach ($rule->media as $query) {
                            if (!$query->matches($media)) {
                                continue 2;
                            }
                        }
                        try {
                            if (!$rule->selector->dynamic || $element->matches($rule->selector->forHtml())) {
                                $matching[] = $index;
                            }
                        } catch (\Throwable) {
                        }
                    }
                    $inline = trim($element->getAttribute('style') ?? '');
                    $display = [] === $matching && '' === $inline ? '' : ($this->cascadeDeclarations($matching, '' !== $inline ? $this->parseInline($inline, true) : [])['display'] ?? '');
                    $displayed[spl_object_id($element)] = 'none' !== strtolower(trim($display));
                }
            } finally {
                foreach ($copied as [$element, $states]) {
                    null === $states ? $element->removeAttribute(Selector::STATE_ATTRIBUTE) : $element->setAttribute(Selector::STATE_ATTRIBUTE, $states);
                }
            }
            $changes[] = [$widget, [$owner, $elements, $update, $count, $candidates, $displayed, $stateful], $displayed !== $last];
        }
        foreach ($changes as [$widget, $entry, $changed]) {
            $this->displayFollowers[$widget] = $entry;
            if ($changed) {
                ($entry[2])($widget, $entry[5]);
            }
        }
    }

    /**
     * Copies the states of a widget and of the widgets of its ancestors onto the elements of the document they come
     * from, which rules like .card:hover .tip match then: up the two trees, each element of the document has the
     * nearest mirror of its name, its id and its classes, none when it is not a widget, like a <span> in a paragraph.
     *
     * @param \Dom\Element $source The element of the widget, in its document
     * @param \Dom\Element $mirror Its mirror
     *
     * @return list<array{\Dom\Element, ?string}> The elements changed, with their states before, to restore
     */
    private static function copyStates(\Dom\Element $source, \Dom\Element $mirror): array
    {
        $copied = [];
        for ($first = true; null !== $source; $source = $source->parentElement, $first = false) {
            // The next element up the mirror, past those of its own, like an anonymous row: one of the document that is
            // not it has no widget, skipped, not paired with one further up of the same name
            $candidate = $mirror;
            while (!$first && null !== $candidate && !self::mirrors($candidate, $source) && str_starts_with($candidate->localName, 'tui-')) {
                $candidate = $candidate->parentElement;
            }
            if (null === $candidate || (!$first && !self::mirrors($candidate, $source))) {
                continue;
            }
            if (($states = $candidate->getAttribute(Selector::STATE_ATTRIBUTE)) !== $before = $source->getAttribute(Selector::STATE_ATTRIBUTE)) {
                null === $states ? $source->removeAttribute(Selector::STATE_ATTRIBUTE) : $source->setAttribute(Selector::STATE_ATTRIBUTE, $states);
                $copied[] = [$source, $before];
            }
            if (null === $mirror = $candidate->parentElement) {
                break;
            }
        }

        return $copied;
    }

    /**
     * Whether an element of the mirror may stand for one of a document: its name, its id and its classes.
     */
    private static function mirrors(\Dom\Element $mirror, \Dom\Element $element): bool
    {
        if ($mirror->localName !== strtolower($element->localName) || $mirror->getAttribute('id') !== $element->getAttribute('id')) {
            return false;
        }
        // Of no class nor id, one of no class nor id, not a classed element further up standing for an ancestor
        if ('' === $classes = trim($element->getAttribute('class') ?? '')) {
            return '' === trim($mirror->getAttribute('class') ?? '');
        }
        $present = preg_split('/\s+/', trim($mirror->getAttribute('class') ?? '')) ?: [];

        return [] === array_diff(preg_split('/\s+/', $classes) ?: [], $present);
    }

    /**
     * Whether an element may be where its scroll container starts scrolled to, like scroll-initial-target: nearest.
     */
    public function mayBeInitialTarget(\Dom\Element $element): bool
    {
        return $this->mayDeclare($element, 'scroll-initial-target', 'nearest\b');
    }

    /**
     * The values of SVG properties, like fill and stroke, that the cascade gives an element of an <svg>: its
     * presentation attributes, under every rule, then the rules that match it, then its style attribute; var() takes
     * the custom properties it sets or inherits, from :root down.
     *
     * Rules depending on a state, like :hover, or on a container, are left out; those of media queries match the media
     * of the last cascade.
     *
     * @internal for {@see VectorImage}
     *
     * @param list<string> $names The properties asked for
     *
     * @return array<string, string>
     */
    public function svgDeclarations(\Dom\Element $element, array $names): array
    {
        [$declarations, $custom] = $this->svgCascade($element);
        $values = [];
        foreach ($names as $name) {
            if (null === $value = $declarations[$name] ?? null) {
                continue;
            }
            // var() of the custom properties of the shape or of what holds it, like fill: var(--accent)
            $values[$name] = str_contains($value, '(') ? $this->substitute($value, $custom) : $value;
        }

        return $values;
    }

    /**
     * The indexes of the rules that may style the shapes of an <svg> of a document: those setting their properties,
     * or the custom properties these read, or the style attributes of the document.
     *
     * @return list<int>
     */
    private function svgRulesOf(?\Dom\Document $document): array
    {
        $wanted = array_flip([...self::SVG_PROPERTIES, ...self::SVG_GEOMETRY, 'all']);
        // The custom properties the values of those read, and those these read in turn: only the rules setting them
        // count, not the many others of a framework, like the --bs-* of Bootstrap
        $custom = [];
        $read = static function (string $value) use (&$custom): bool {
            $added = false;
            if (str_contains($value, 'var(') && preg_match_all('/var\(\s*(--[\w-]+)/', $value, $m)) {
                foreach ($m[1] as $name) {
                    $added = !isset($custom[$name]) || $added;
                    $custom[$name] = true;
                }
            }

            return $added;
        };
        foreach ($this->rules as $rule) {
            foreach ($rule->declarations as $declaration) {
                if (isset($wanted[$declaration->property])) {
                    $read($declaration->value);
                }
            }
        }
        // Those the style attributes of the document read, like style="fill: var(--brand)"
        foreach ($document?->querySelectorAll('[style*="var("]') ?? [] as $styled) {
            $read($styled->getAttribute('style') ?? '');
        }
        for ($pass = 0, $added = true; $added && $pass < 32; ++$pass) {
            $added = false;
            foreach ($this->rules as $rule) {
                foreach ($rule->declarations as $declaration) {
                    if (isset($custom[$declaration->property]) && $read($declaration->value)) {
                        $added = true;
                    }
                }
            }
        }
        $indexes = [];
        foreach ($this->rules as $index => $rule) {
            if (null === $rule->selector->pseudoElement && !$rule->selector->dynamic && [] === $rule->containers && !$rule->starting
                && array_any($rule->declarations, static fn (Declaration $declaration) => isset($wanted[$declaration->property]) || isset($custom[$declaration->property]))) {
                $indexes[] = $index;
            }
        }

        return $indexes;
    }

    /**
     * The rules each element of a document matches among some, in their order, each rule queried once: an element is
     * not matched against every rule, which takes long with those of a framework.
     *
     * @param list<int> $rules
     *
     * @return \SplObjectStorage<\Dom\Element, list<int>> Its elements held: the DOM makes another object for an
     *                                                    element no one holds, which a weak map would not know
     */
    private function svgMatchesOf(\Dom\Document $document, array $rules): \SplObjectStorage
    {
        /** @var \SplObjectStorage<\Dom\Element, list<int>> $matches */
        $matches = new \SplObjectStorage();
        $names = $this->documentNames($document);
        foreach ($rules as $index) {
            $rule = $this->rules[$index];
            if (!$this->mayMatchNames($rule->selector->forSvg(), $names)) {
                continue;
            }
            try {
                $elements = Selector::queryAll($document, $rule->selector->forSvg());
            } catch (\Throwable) {
                // Valid CSS the native engine does not support: skipped, like in the cascade
                continue;
            }
            foreach ($elements as $matched) {
                // A selector list may return an element once per selector it matches
                $indexes = $matches[$matched] ?? [];
                if ($index !== ($indexes[\count($indexes) - 1] ?? null)) {
                    $indexes[] = $index;
                    $matches[$matched] = $indexes;
                }
            }
        }

        return $matches;
    }

    /**
     * The declarations of the properties asked for that the cascade gives an element, and the custom properties it
     * sets or inherits from its ancestors.
     *
     * @return array{array<string, string>, array<string, string>}
     */
    private function svgCascade(\Dom\Element $element): array
    {
        // Read again for other rules, and in other media, like another color scheme
        $media = $this->media ?? new MediaContext();
        if ($this->svgRulesCount !== \count($this->rules) || $this->svgMedia !== $media->getKey()) {
            // In another media only, like a terminal of another size: the rules each element matches stay, their
            // media queries read again
            if ($this->svgRulesCount !== \count($this->rules)) {
                $this->svgDocument = null;
                $this->svgLooseRules = null;
            }
            $this->svgRulesCount = \count($this->rules);
            $this->svgMedia = $media->getKey();
            $this->svgCascade = new \WeakMap();
        }
        if (null !== $cached = $this->svgCascade[$element] ?? null) {
            return $cached;
        }
        $inherited = null !== $element->parentElement ? $this->svgCascade($element->parentElement)[1] : [];

        // In a document, the rules found for each of its elements at once; out of one, matched one by one
        $document = $element->ownerDocument;
        if (null !== $document && $document !== ($this->svgDocument[0] ?? null)) {
            $this->svgDocument = [$document, $this->svgRulesOf($document), null, null];
        }
        $rules = null !== $document ? $this->svgDocument[1] : ($this->svgLooseRules ??= $this->svgRulesOf(null));
        $matching = [];
        if (null !== $document && $element->isConnected) {
            foreach (($this->svgDocument[2] ??= $this->svgMatchesOf($document, $rules))[$element] ?? [] as $index) {
                foreach ($this->rules[$index]->media as $query) {
                    if (!$query->matches($media)) {
                        continue 2;
                    }
                }
                $matching[] = $index;
            }
            $rules = [];
        } elseif (null !== $document) {
            // Out of it, like a copy of an icon in the text of a link: those that may match there, by the names they need
            $rules = $this->svgDocument[3] ??= (function () use ($document, $rules): array {
                $names = $this->documentNames($document);

                return array_values(array_filter($rules, fn (int $index): bool => $this->mayMatchNames($this->rules[$index]->selector->forSvg(), $names)));
            })();
        }
        foreach ($rules as $index) {
            $rule = $this->rules[$index];
            foreach ($rule->media as $query) {
                if (!$query->matches($media)) {
                    continue 2;
                }
            }
            try {
                if ($element->matches($rule->selector->forSvg())) {
                    $matching[] = $index;
                }
            } catch (\Throwable) {
                // Valid CSS the native engine does not support: skipped, like in the cascade
            }
        }

        $hints = '';
        foreach (self::SVG_PROPERTIES as $name) {
            if (null !== $value = $element->getAttribute($name)) {
                $hints .= $name.': '.str_replace(';', '', $value).';';
            }
        }
        $inline = trim($element->getAttribute('style') ?? '');
        $declarations = [] === $matching && '' === $inline && '' === $hints ? [] : $this->cascadeDeclarations($matching, '' !== $inline ? $this->parseInline($inline, true) : [], '' !== $hints ? $this->parseInline($hints) : []);
        $custom = array_filter($declarations, static fn (string $property) => str_starts_with($property, '--'), \ARRAY_FILTER_USE_KEY) + $inherited;

        return $this->svgCascade[$element] = [array_intersect_key($declarations, array_flip([...self::SVG_PROPERTIES, ...self::SVG_GEOMETRY])), $custom];
    }

    /**
     * Whether some rule or the style attribute may give an element a value of a property matching a pattern.
     *
     * @param bool $conditional Only the rules of the page in a media or container query, or for a state
     * @param bool $page        Only the rules of the page in every media, the narrowest screen holding those from a
     *                          width it is as wide as (see inEveryMedia()), not those of the user agent
     * @param bool $plain       Not the rules of a container query nor of @starting-style
     */
    private function mayDeclare(\Dom\Element $element, string $property, string $pattern, bool $anyState = false, bool $conditional = false, bool $page = false, bool $plain = false): bool
    {
        if (preg_match('/(?:^|;)\s*'.$property.'\s*:\s*'.$pattern.'/i', $element->getAttribute('style') ?? '')) {
            return true;
        }
        // The selectors of the rules that declare it, found once for the rules there are: the builder asks of many
        // elements, which match them all at once
        $key = $property.':'.$pattern.':'.($anyState ? 1 : 0).($conditional ? 1 : 0).($page ? 1 : 0).($plain ? 1 : 0).':'.\count($this->rules);
        if (null === $selectors = $this->declaring[$key] ?? null) {
            $selectors = [];
            foreach ($this->rules as $rule) {
                if (null !== $rule->selector->pseudoElement || ($rule->selector->dynamic && !$anyState)) {
                    continue;
                }
                if ($conditional && ($rule->userAgent || (self::inEveryMedia($rule) && [] === $rule->containers && !$rule->selector->dynamic))) {
                    continue;
                }
                if ($plain && ([] !== $rule->containers || $rule->starting)) {
                    continue;
                }
                if ($page && ($rule->userAgent || !self::inEveryMedia($rule))) {
                    continue;
                }
                foreach ($rule->declarations as $declaration) {
                    if ($property === $declaration->property && preg_match('/^'.$pattern.'/i', trim($declaration->value))) {
                        // In any state, like input:invalid + .error: the states left out, which the document has not
                        $selector = $rule->selector->dynamic ? self::withoutStates($rule->selector->forHtml()) : $rule->selector->forHtml();
                        if ('' !== $selector) {
                            $selectors[$selector] = true;
                        }
                        break;
                    }
                }
            }
            $selectors = $this->declaring[$key] = array_keys($selectors);
        }
        // Those that may match in the document of the element, by the names they need: not every rule of a framework,
        // like the thousands that declare display of the styles of GitHub
        $document = $element->ownerDocument;
        if (null !== $document) {
            if ($document !== ($this->declaringIn[0] ?? null)) {
                $this->declaringIn = [$document, $this->documentNames($document), []];
            }
            $selectors = $this->declaringIn[2][$key] ??= array_values(array_filter($selectors, fn (string $selector): bool => $this->mayMatchNames($selector, $this->declaringIn[1])));
        }
        if ([] === $selectors) {
            return false;
        }

        try {
            return $element->matches(implode(', ', $selectors));
        } catch (\Throwable) {
            // A selector the engine does not know: those it knows, kept for the next elements
            $known = static function (string $selector) use ($element): bool {
                try {
                    $element->matches($selector);

                    return true;
                } catch (\Throwable) {
                    return false;
                }
            };
            $this->declaring[$key] = array_values(array_filter($this->declaring[$key], $known));
            $selectors = array_values(array_filter($selectors, $known));
            if (null !== $document) {
                $this->declaringIn[2][$key] = $selectors;
            }

            return [] !== $selectors && $element->matches(implode(', ', $selectors));
        }
    }

    /**
     * A selector without the widget states it tests, like :hover or :invalid: what it matches in some state.
     */
    private static function withoutStates(string $selector): string
    {
        // Each test of a state, or of its absence, matches any element: a compound of states alone too
        $state = preg_quote('['.Selector::STATE_ATTRIBUTE.'~="', '/').'[^"]*"\]';

        return preg_replace(['/:not\('.$state.'\)/', '/'.$state.'/'], ':is(*)', $selector) ?? '';
    }

    public static function fromString(string $css, ?StylesheetLoaderInterface $loader = null): self
    {
        return (new self($loader))->add($css);
    }

    /**
     * Adds CSS source; adding the same source twice is a no-op.
     *
     * @param string|null $media   A media query list the whole source applies to
     * @param string|null $scope   The selector of the element the style sheet is in, the root of its @scope without one
     * @param int|null    $tree    The shadow tree the style sheet is in, like a <style> of a <template shadowrootmode>, its
     *                             rules matching there only; 0 for the document when it has shadow trees (@internal)
     * @param int         $context How deep that tree is in others: the document wins over it, but for important
     *                             declarations (@internal)
     *
     * @return $this
     * @return $this
     */
    public function add(string $css, ?string $media = null, ?string $scope = null, ?int $tree = null, int $context = 0): static
    {
        return $this->addSource($css, $media, false, $scope, $tree, $context);
    }

    /**
     * Adds the user agent stylesheet: its rules come before every author rule, whatever their layers.
     *
     * @internal
     *
     * @return $this
     */
    public function addUserAgentStyles(string $css): static
    {
        return $this->addSource($css, null, true);
    }

    /**
     * @return $this
     */
    private function addSource(string $css, ?string $media, bool $userAgent, ?string $scope = null, ?int $tree = null, int $context = 0): static
    {
        $key = hash('xxh128', ($userAgent ? 'ua' : '').$media."\0".$scope."\0".$tree."\0".$context."\0".$css);
        if (isset($this->sources[$key])) {
            return $this;
        }
        $this->sources[$key] = true;

        $order = \count($this->rules);
        foreach ($this->parser->parse($css, null !== $media ? [MediaQuery::parse($media)] : [], $scope) as $rule) {
            // In a document with shadow trees, each rule matches in its own tree
            $selector = $rule->selector;
            if (null !== $tree) {
                try {
                    $selector = Selector::parse(Selector::inTree($selector->source, $tree));
                } catch (InvalidArgumentException) {
                    continue;
                }
            }
            $this->rules[] = new CssRule($selector, $rule->declarations, $rule->media, $order + $rule->order, $rule->layer, $userAgent, $rule->containers, $rule->scoped, $rule->starting, $context);
            $this->hasStartingRules = $this->hasStartingRules || $rule->starting;
            $this->hasStyleQueries = $this->hasStyleQueries || array_any($rule->containers, static fn (ContainerQuery $query) => [] !== $query->styles);
        }
        // A later @keyframes of the same name replaces the earlier one
        $this->keyframes = $this->parser->takeKeyframes() + $this->keyframes;
        // Like CSS, the first registration of a name wins
        $this->registered += $this->parser->takeRegisteredProperties();
        // A later @function of the same name replaces the earlier one
        $this->functions = $this->parser->takeFunctions() + $this->functions;
        $this->counterStyles->add($this->parser->takeCounterStyles());
        // A later @position-try of the same name replaces the earlier one
        if ([] !== $tries = $this->parser->takePositionTries()) {
            $this->positionTries = $tries + $this->positionTries;
            $this->mapper->setPositionTries($this->positionTries);
        }
        $this->rulesChanged = true;

        return $this;
    }

    /**
     * Adds a stylesheet through the loader, like <link rel="stylesheet">.
     *
     * @return $this
     */
    /**
     * @param int|null $tree    The shadow tree the style sheet is in, see add() (@internal)
     * @param int      $context How deep that tree is, see add() (@internal)
     */
    public function import(string $href, ?string $media = null, ?int $tree = null, int $context = 0): static
    {
        if (null === $this->loader) {
            throw new InvalidArgumentException(\sprintf('Cannot import "%s": no stylesheet loader is configured.', $href));
        }

        return $this->add($this->loader->load($href), $media, null, $tree, $context);
    }

    /**
     * Tells which element a widget stands for, with its attributes.
     *
     * The HTML builder describes the widgets it creates; other widgets are
     * mirrored with a default element name (InputWidget is <input>,
     * TextWidget is <tui-text>...).
     *
     * @param array<string, string> $attributes      Attributes like aria-*, data-*, or style for inline declarations
     * @param list<string>          $internalClasses The classes of the widget that are not those of the element, like the p of
     *                                               a paragraph that the default style sheet styles widgets by: selectors of the
     *                                               page, like [class] or .p, do not match them
     * @param list<string>          $classOrder      The classes of the element in the order of its class attribute, which
     *                                               the mirror keeps, when some stay on the element and the others go to
     *                                               the widget
     */
    public function describe(AbstractWidget $widget, string $tagName, array $attributes = [], array $internalClasses = [], array $classOrder = []): void
    {
        $tagName = strtolower($tagName);
        if (!preg_match('/^[a-z][a-z0-9]*(?:-[a-z0-9._]+)*$/', $tagName)) {
            throw new InvalidArgumentException(\sprintf('"%s" is not a valid element name.', $tagName));
        }
        $description = [$tagName, $attributes];
        if (($this->descriptions[$widget] ?? null) !== $description || ($this->internalClasses[$widget] ?? []) !== $internalClasses || ($this->classOrders[$widget] ?? []) !== $classOrder) {
            $this->descriptions[$widget] = $description;
            if ($internalClasses) {
                $this->internalClasses[$widget] = $internalClasses;
            } else {
                unset($this->internalClasses[$widget]);
            }
            if ($classOrder) {
                $this->classOrders[$widget] = $classOrder;
            } else {
                unset($this->classOrders[$widget]);
            }
            $widget->invalidate();
        }
    }

    /**
     * Returns the computed value of a property for a widget, as the last cascade left it, inherited ones included.
     */
    public function getComputedValue(AbstractWidget $widget, string $property): ?string
    {
        return $this->declarations[$this->widgetDeclarations[$widget] ?? -1][strtolower($property)] ?? null;
    }

    /**
     * @return array{string, array<string, string>}|null
     */
    public function getDescription(AbstractWidget $widget): ?array
    {
        return $this->descriptions[$widget] ?? null;
    }

    /**
     * Returns the widgets matching a selector, in tree order.
     *
     * The root widget itself is included when it matches.
     *
     * @return list<AbstractWidget>
     */
    public function querySelectorAll(string $selector, ?AbstractWidget $root = null): array
    {
        $root ??= $this->root;
        if (null === $root) {
            return [];
        }

        $element = $this->elements[spl_object_id($root)] ?? null;
        if (null === $element || !$element->isConnected || $root->getRenderRevision() !== ($this->synced[$root][0] ?? null)) {
            // Not mirrored yet, or changed since: mirror it, without disturbing the tree being rendered
            $previousRoot = $this->root;
            $this->sync($root, $this->media ?? MediaContext::fromEnvironment());
            $this->root = $previousRoot;
            $this->rootRevision = -1;
            $element = $this->elements[spl_object_id($root)];
        }

        $natives = implode(', ', array_map(static fn (Selector $s) => $s->native, Selector::parseList($selector)));
        $widgets = [];
        if ($element->matches($natives) && null !== $widget = $this->widgetFor($element)) {
            $widgets[spl_object_id($widget)] = $widget;
        }
        // The native engine returns an element once per selector of a list it matches
        foreach ($element->querySelectorAll($natives) as $match) {
            if (null !== $widget = $this->widgetFor($match)) {
                $widgets[spl_object_id($widget)] ??= $widget;
            }
        }

        return array_values($widgets);
    }

    /**
     * Styles the content of a nested document only, like an <iframe srcdoc>: prepare() starts from that root, the
     * viewport of its media queries and viewport units being the size of the frame.
     *
     * @param array{int, int}|null $size The columns and rows of the frame
     */
    public function setScope(?AbstractWidget $root, ?array $size = null): void
    {
        $this->scope = $root;
        $this->scopeSize = $size;
    }

    /**
     * A stylesheet for a document nested in the page, like an <iframe srcdoc>: the same loader, logger, strictness and
     * properties, none of its rules.
     */
    public function createNested(): self
    {
        // Its own copy of the mapper, the properties added to it kept, its images read by the new stylesheet
        return new self($this->loader, $this->logger, $this->strict, clone $this->mapper);
    }

    public function prepare(AbstractWidget $root, MediaContext $media): void
    {
        if (null !== $this->scope) {
            $root = $this->scope;
            $media = null !== $this->scopeSize ? $media->withSize(...$this->scopeSize) : $media;
        }
        $mediaChanged = $media->getKey() !== $this->media?->getKey();
        $wasAnimating = $this->animating;
        $highlightsChanged = $this->highlightsChanged;
        if (!$wasAnimating && !$mediaChanged && !$this->rulesChanged && !$highlightsChanged && $root === $this->root && $root->getRenderRevision() === $this->rootRevision) {
            return;
        }
        $this->highlightsChanged = false;

        // Only time went by for running animations: the tree is as it was left
        $untouched = $wasAnimating && !$mediaChanged && !$this->rulesChanged && !$highlightsChanged && $root === $this->root && $root->getRenderRevision() === $this->rootRevision;
        [$structureChanged, $stateChanged] = $untouched ? [false, false] : $this->sync($root, $media);

        if ($this->rulesChanged) {
            $this->declarations = [];
            $this->declarationIds = [];
            $this->inheritableById = [];
            $this->inheritable = null;
            $this->elementDependent = [];
        }
        if ($this->rulesChanged || $mediaChanged) {
            // Media queries and media-dependent values (light-dark(), px) may resolve differently
            $this->stylesByDeclarations = [];
            $this->textStylesByDeclarations = [];
            $this->hangs = [];
            $this->elementStyleCache = [];
            $this->staticMatches = null;
        }
        if ($structureChanged || $this->rulesChanged) {
            $this->staticMatches = null;
            $this->staticRuleElements = [];
        }

        // While animations run, their styles change with time: computed again each frame, only them and what they
        // contain when nothing else changed, from the counters and quotes they were reached with
        if ($wasAnimating && !$structureChanged && !$stateChanged && !$mediaChanged && !$this->rulesChanged && !$highlightsChanged && null !== $this->lastDynamic) {
            $this->animating = false;
            $containers = $this->containerWidgets;
            $moving = [];
            foreach ($this->moving as $widget => $_) {
                $moving[] = $widget;
            }
            foreach ($moving as $widget) {
                for ($ancestor = $widget->getParent(); null !== $ancestor; $ancestor = $ancestor->getParent()) {
                    if (isset($this->moving[$ancestor])) {
                        continue 2;
                    }
                }
                $parent = $widget->getParent();
                $counters = $this->counters;
                [$this->counters, $this->counterDepth, $this->quoteDepth] = $this->countersAt[$widget] ?? [[], 0, 0];
                $this->compute($widget, $this->lastDynamic, null !== $parent ? $this->widgetDeclarations[$parent] ?? -1 : -1);
                $this->counters = $counters;
            }
            $this->containerWidgets = $containers;
        } elseif ($structureChanged || $stateChanged || $mediaChanged || $this->rulesChanged || $highlightsChanged || $wasAnimating) {
            $this->animating = false;
            // Only states changed, like a hover: what their rules match again, the rest as it was
            $this->cascade($root, $media, !$structureChanged && !$mediaChanged && !$this->rulesChanged && !$highlightsChanged && !$wasAnimating);
            if ($this->animating && !$wasAnimating && null !== $this->onAnimate) {
                ($this->onAnimate)();
            }
        }

        $this->rulesChanged = false;
        $this->rootRevision = $root->getRenderRevision();
    }

    /**
     * Whether some CSS animation is running: the styles change until it ends, frames must be drawn.
     */
    public function isAnimating(): bool
    {
        return $this->animating;
    }

    /**
     * Calls a listener when animations start running, after none did; and sets the clock they follow, for tests.
     *
     * @param (\Closure(): void)|null  $onAnimate
     * @param (\Closure(): float)|null $clock     The time in seconds
     */
    public function setAnimationHandlers(?\Closure $onAnimate, ?\Closure $clock = null): void
    {
        $this->onAnimate = $onAnimate;
        if (null !== $clock) {
            $this->clock = $clock;
        }
    }

    public function getStyles(AbstractWidget $widget): array
    {
        return isset($this->styles[$widget]) ? [$this->styles[$widget]] : [];
    }

    public function getElementStyles(AbstractWidget $widget, string $element): array
    {
        $rules = $this->elementRules[$widget] ?? [];
        $declarationsId = $this->widgetDeclarations[$widget] ?? -1;
        $accent = \in_array($element, self::ACCENT_ELEMENTS, true) ? ($this->declarations[$declarationsId]['accent-color'] ?? null) : null;
        // The caret of a field takes the caret-shape, caret-color and caret-animation of the field, inherited
        $caret = 'cursor' === $element ? array_intersect_key($this->declarations[$declarationsId] ?? [], self::CARET_PROPERTIES) : [];
        if ((!$rules && null === $accent && !$caret) || null === $this->media) {
            return [];
        }

        $key = $declarationsId.'|'.implode(',', $rules).'|'.$element;
        if (isset($this->elementStyleCache[$key])) {
            return $this->elementStyleCache[$key];
        }

        $matching = array_values(array_filter($rules, fn (int $index) => self::pseudoElementMatches($this->rules[$index]->selector->pseudoElement, $element)));
        $styles = [];
        // accent-color first: the rules of the sub-element itself win over it
        if (null !== $accent && 'auto' !== strtolower($accent) && ($color = Value::color($this->substitute($accent, $this->declarations[$declarationsId] ?? []), $this->media)) instanceof Color) {
            $styles[] = new Style(color: $color);
        }
        if ($caret && !$matching) {
            $styles[] = $this->computeStyle($caret, $this->media);
        }
        if ($matching) {
            // A pseudo-element inherits from the widget it belongs to
            $own = $this->cascadeDeclarations($matching, []);
            $declarations = $this->inherit($own, $this->declarations[$declarationsId] ?? [], isset($this->declarations[$declarationsId]) ? $declarationsId : null);
            $style = $this->computeStyle($declarations, $this->media);
            // A transparent color of its own, like the ::placeholder of a floating label of Bootstrap: its text not drawn
            if (isset($own['color'])) {
                try {
                    $style = $style->withConcealed(null === Value::color($this->substitute($own['color'], $declarations), $this->media) ? true : null);
                } catch (InvalidArgumentException) {
                }
            }
            $styles[] = $style;
        }

        return $this->elementStyleCache[$key] = $styles;
    }

    public function getSupport(): array
    {
        return Selector::getSupport() + MediaQuery::getSupport() + CssParser::getSupport() + $this->mapper->getSupport();
    }

    /**
     * Mirrors the widget tree into the DOM document.
     *
     * Subtrees whose render revision did not change since they were last
     * mirrored are skipped: any change to a widget invalidates it and its
     * ancestors.
     *
     * @return array{bool, bool} Whether the structure (elements, attributes) and the states changed
     */
    private function sync(AbstractWidget $root, MediaContext $media): array
    {
        $rootChanged = $root !== $this->root;
        $this->root = $root;
        // Lengths of the viewport are of a syntax or not in a media
        if ($media->getKey() !== $this->media?->getKey()) {
            $this->syntaxMatches = [];
        }
        $this->media = $media;
        $document = $this->document ??= \Dom\HTMLDocument::createEmpty();

        $structureChanged = $rootChanged;
        $stateChanged = false;
        [$element] = $this->mirror($root, $media->density, '', 'ltr', $structureChanged, $stateChanged);

        if ($document->documentElement !== $element) {
            if (null !== $document->documentElement) {
                $document->removeChild($document->documentElement);
            }
            $document->appendChild($element);
            $structureChanged = true;
        }

        return [$structureChanged, $stateChanged];
    }

    /**
     * @param string $lang The language of the parent, "" when unknown
     * @param string $dir  The direction of the parent, "ltr" or "rtl"
     *
     * @return array{\Dom\Element, bool, bool, 3?: list<\Dom\Element>|null} The element, whether it or a descendant has the focus, and whether it or a descendant is invalid
     */
    private function mirror(AbstractWidget $widget, Density $density, string $lang, string $dir, bool &$structureChanged, bool &$stateChanged): array
    {
        $id = spl_object_id($widget);
        // The root of the tree, like the one of the Tui, is the screen: :root matches it, not the selectors of its class
        [$tagName, $attributes] = $this->descriptions[$widget] ?? [$widget === $this->root ? self::ROOT_TAG : self::defaultTagName($widget), []];
        $internal = $this->internalClasses[$widget] ?? [];
        if (isset($attributes['data-density'])) {
            $density = Density::tryFrom($attributes['data-density']) ?? $density;
        }
        [$lang, $dir] = self::langAndDir($attributes, $lang, $dir);
        $this->languages[(string) $id] = $lang;

        $synced = $this->synced[$widget] ?? null;
        if (null !== $synced && $synced[0] === $widget->getRenderRevision() && $synced[1] === $density && $synced[4] === $lang && $synced[5] === $dir && isset($this->elements[$id])) {
            return [$this->elements[$id], $synced[2], $synced[3], $synced[6] ?? null];
        }

        $this->widgetsById[$id] = \WeakReference::create($widget);
        $element = $this->elements[$id] ?? null;
        if (null === $element || $element->localName !== $tagName) {
            $element = $this->document->createElement($tagName);
            $this->elements[$id] = $element;
            $structureChanged = true;
        }

        $states = $widget->getStateFlags();
        if (Density::Condensed === $density) {
            $states[] = 'condensed';
        }

        $children = [];
        $focus = \in_array('focus', $states, true);
        // An invalid control inside: a form or a fieldset is invalid too, like HTML
        $invalid = \in_array('invalid', $states, true);
        // A nested document is styled by its own stylesheet
        if ($widget instanceof ParentInterface && !$widget instanceof FrameWidget) {
            foreach ($widget->all() as $child) {
                [$childElement, $childFocus, $childInvalid, $inline] = $this->mirror($child, $density, $lang, $dir, $structureChanged, $stateChanged) + [3 => null];
                // An anonymous text block is not an element: its inline elements are children here
                null !== $inline ? array_push($children, ...$inline) : $children[] = $childElement;
                $focus = $focus || $childFocus;
                $invalid = $invalid || $childInvalid;
            }
        }
        if ($focus) {
            $states[] = 'focus-within';
        }
        if ($this->visited && \in_array($tagName, ['a', 'area'], true) && $this->isVisited($attributes['href'] ?? '')) {
            $states[] = 'visited';
        }
        if (\in_array($tagName, ['form', 'fieldset'], true) && !\in_array('valid', $states, true) && !\in_array('invalid', $states, true)) {
            $states[] = $invalid ? 'invalid' : 'valid';
        }
        if ($widget instanceof InlineContentInterface) {
            $children = $this->mirrorInline($widget, $id, $lang, $dir, $structureChanged);
            // The link of the text with the focus matches :focus and :focus-visible, like a:focus { background: yellow }
            if ($widget instanceof RichTextWidget) {
                $this->markCurrentLink($children, $widget->getCurrentLink(), $widget->getHoveredLink(), $stateChanged);
            }
        }
        // The parts it draws, like the <summary> of a <details>, before its children
        if ($widget instanceof PartsInterface) {
            $parts = [];
            foreach ($widget->getParts() as $name => [$partTag, $partAttributes]) {
                $part = $this->partElements[$id][$name] ?? null;
                if (null === $part || $part->localName !== $partTag) {
                    $part = $this->partElements[$id][$name] = $this->document->createElement($partTag);
                    $structureChanged = true;
                }
                $partAttributes[self::PART_ATTRIBUTE] = $id.':'.$name;
                $partAttributes += self::langAndDirAttributes(...self::langAndDir($partAttributes, $lang, $dir));
                foreach ($part->getAttributeNames() as $attribute) {
                    if (!isset($partAttributes[$attribute])) {
                        $part->removeAttribute($attribute);
                        $structureChanged = true;
                    }
                }
                foreach ($partAttributes as $attribute => $value) {
                    if ($part->getAttribute($attribute) !== $value) {
                        try {
                            $part->setAttribute($attribute, $value);
                        } catch (\DOMException) {
                            continue;
                        }
                        $structureChanged = true;
                    }
                }
                $parts[] = $part;
            }
            $children = [...$parts, ...$children];
        }

        $wanted = $attributes;
        $wanted[self::OID_ATTRIBUTE] = (string) $id;
        $wanted += self::langAndDirAttributes($lang, $dir);
        if (null !== $widgetId = $widget->getId()) {
            $wanted['id'] = $widgetId;
        }
        // Not those of the element kept on it, like the class="p" of a paragraph, once
        if ($classes = array_diff($widget->getStyleClasses(), $internal, explode(' ', $attributes['class'] ?? ''))) {
            $present = [...preg_split('/\s+/', trim($attributes['class'] ?? ''), -1, \PREG_SPLIT_NO_EMPTY) ?: [], ...$classes];
            // In the order of the class attribute of the element, like [class^="col-"] reads it, then the others
            if ($order = $this->classOrders[$widget] ?? []) {
                $present = [...array_intersect($order, $present), ...array_diff($present, $order)];
            }
            $wanted['class'] = implode(' ', array_unique($present));
        }
        if ($states) {
            $wanted[Selector::STATE_ATTRIBUTE] = implode(' ', $states);
        }

        foreach ($element->getAttributeNames() as $name) {
            if (!isset($wanted[$name])) {
                $element->removeAttribute($name);
                Selector::STATE_ATTRIBUTE === $name ? $stateChanged = true : $structureChanged = true;
            }
        }
        foreach ($wanted as $name => $value) {
            if ($element->getAttribute($name) !== (string) $value) {
                try {
                    $element->setAttribute($name, (string) $value);
                } catch (\DOMException) {
                    // A name HTML accepts but the DOM does not, like @click: no selector can match it anyway
                    continue;
                }
                Selector::STATE_ATTRIBUTE === $name ? $stateChanged = true : $structureChanged = true;
            }
        }

        // An anonymous text block: its element, detached, holds no child, its inline elements going to its parent; an
        // anonymous row, like the one of controls in the line of text, the elements of its children
        if (isset($this->anonymous[$widget]) && ($widget instanceof InlineContentInterface || $widget instanceof ParentInterface)) {
            while (null !== $element->firstElementChild) {
                $element->removeChild($element->firstElementChild);
            }
            $element->parentNode?->removeChild($element);
            $this->synced[$widget] = [$widget->getRenderRevision(), $density, $focus, $invalid, $lang, $dir, $children];

            return [$element, $focus, $invalid, $children];
        }

        // Children, in order
        $node = $element->firstElementChild;
        foreach ($children as $child) {
            if ($node === $child) {
                $node = $node->nextElementSibling;
                continue;
            }
            $element->insertBefore($child, $node);
            $structureChanged = true;
        }
        while (null !== $node) {
            $next = $node->nextElementSibling;
            $element->removeChild($node);
            $node = $next;
            $structureChanged = true;
        }

        // Its text, its own or that of the text in it, for :empty
        if (self::markText($element, $this->hasText($widget))) {
            $structureChanged = true;
        }

        $this->synced[$widget] = [$widget->getRenderRevision(), $density, $focus, $invalid, $lang, $dir];

        return [$element, $focus, $invalid];
    }

    /**
     * Whether a widget holds text of its own, like a paragraph, or in an anonymous block, like a <div> of text.
     */
    private function hasText(AbstractWidget $widget): bool
    {
        if ($widget instanceof InlineContentInterface) {
            return array_any($widget->getInlineNodes(), static fn (InlineNode $node): bool => $node->isText() && '' !== $node->text);
        }
        if ($widget instanceof TextWidget) {
            return '' !== $widget->getText();
        }

        return $widget instanceof ParentInterface && array_any($widget->all(), fn (AbstractWidget $child): bool => isset($this->anonymous[$child]) && $child instanceof InlineContentInterface && $this->hasText($child));
    }

    /**
     * Gives an element of the mirror a text child when the element it stands for holds text, which :empty sees, or
     * takes it away; the text itself does not matter to selectors.
     *
     * @return bool Whether it changed
     */
    private static function markText(\Dom\Element $element, bool $hasText): bool
    {
        $text = null;
        foreach ($element->childNodes as $child) {
            if ($child instanceof \Dom\Text) {
                $text = $child;
                break;
            }
        }
        if ($hasText === (null !== $text)) {
            return false;
        }
        $hasText ? $element->appendChild($element->ownerDocument->createTextNode('x')) : $element->removeChild($text);

        return true;
    }

    /**
     * Mirrors the inline elements of a text block as children of its element.
     *
     * @return list<\Dom\Element>
     */
    private function mirrorInline(AbstractWidget&InlineContentInterface $widget, int $id, string $lang, string $dir, bool &$structureChanged): array
    {
        $nodes = $widget->getInlineNodes();
        if (null !== ($cached = $this->inlineMirrors[$widget] ?? null) && $cached[0] === $nodes && $cached[2] === $lang && $cached[3] === $dir) {
            return $cached[1];
        }

        $index = 0;
        $elements = $this->mirrorInlineNodes($nodes, $id, $lang, $dir, $index);
        $this->inlineMirrors[$widget] = [$nodes, $elements, $lang, $dir];
        $structureChanged = true;

        return $elements;
    }

    /**
     * Gives the link with the focus and the one under the pointer, by their index among the links of the text, the
     * focus and hover states, and the others none.
     *
     * @param list<\Dom\Element> $elements
     */
    private function markCurrentLink(array $elements, ?int $current, ?int $hovered, bool &$stateChanged): void
    {
        $links = [];
        $collect = static function (iterable $elements) use (&$collect, &$links): void {
            foreach ($elements as $element) {
                if ('a' === $element->localName && '' !== trim($element->getAttribute('href') ?? '')) {
                    $links[] = $element;
                }
                $collect(array_values(array_filter(iterator_to_array($element->childNodes, false), static fn (\Dom\Node $node): bool => $node instanceof \Dom\Element)));
            }
        };
        $collect($elements);
        foreach ($links as $i => $link) {
            $states = implode(' ', array_keys(array_filter(['focus' => $i === $current, 'hover' => $i === $hovered, 'visited' => $this->isVisited($link->getAttribute('href') ?? '')])));
            if ($states !== ($link->getAttribute(Selector::STATE_ATTRIBUTE) ?? '')) {
                '' !== $states ? $link->setAttribute(Selector::STATE_ATTRIBUTE, $states) : $link->removeAttribute(Selector::STATE_ATTRIBUTE);
                $stateChanged = true;
            }
        }
    }

    /**
     * @param list<InlineNode> $nodes
     *
     * @return list<\Dom\Element>
     */
    private function mirrorInlineNodes(array $nodes, int $id, string $lang, string $dir, int &$index): array
    {
        $elements = [];
        foreach ($nodes as $node) {
            if ($node->isText()) {
                continue;
            }
            try {
                $element = $this->document->createElement($node->tagName);
            } catch (\DOMException) {
                $element = $this->document->createElement('span');
            }
            foreach ($node->attributes as $name => $value) {
                try {
                    $element->setAttribute($name, $value);
                } catch (\DOMException) {
                }
            }
            [$nodeLang, $nodeDir] = self::langAndDir($node->attributes, $lang, $dir);
            $this->languages[$id.':'.$index] = $nodeLang;
            $element->setAttribute(self::INLINE_ATTRIBUTE, $id.':'.$index++);
            foreach (self::langAndDirAttributes($nodeLang, $nodeDir) as $name => $value) {
                $element->setAttribute($name, $value);
            }
            foreach ($this->mirrorInlineNodes($node->children, $id, $nodeLang, $nodeDir, $index) as $child) {
                $element->appendChild($child);
            }
            // Its text, for :empty
            self::markText($element, array_any($node->children, static fn (InlineNode $child): bool => $child->isText() && '' !== $child->text));
            $elements[] = $element;
        }

        return $elements;
    }

    /**
     * The language and the direction of an element: its own attributes, else those of its parent, like :lang() and :dir() inherit them.
     *
     * @param array<string, string> $attributes
     *
     * @return array{string, string}
     */
    private static function langAndDir(array $attributes, string $lang, string $dir): array
    {
        if (isset($attributes['lang'])) {
            $lang = trim($attributes['lang']);
        }
        $own = strtolower(trim($attributes['dir'] ?? ''));

        return [$lang, 'ltr' === $own || 'rtl' === $own ? $own : $dir];
    }

    /**
     * @return array<string, string>
     */
    private static function langAndDirAttributes(string $lang, string $dir): array
    {
        $attributes = [];
        if ('' !== $lang) {
            $attributes[Selector::LANG_ATTRIBUTE] = $lang;
        }
        if ('rtl' === $dir) {
            $attributes[Selector::DIR_ATTRIBUTE] = 'rtl';
        }

        return $attributes;
    }

    /**
     * Matches the rules and computes the style of every widget.
     */
    private function cascade(AbstractWidget $root, MediaContext $media, bool $statesOnly = false): void
    {
        if (null === $this->staticMatches) {
            [$this->staticMatches, $this->staticElementMatches, $this->staticInlineMatches, $this->staticInlineElementMatches] = $this->match(false, $media);
        }
        [$previous, $previousParts] = [$this->lastDynamic, $this->dynamicPartMatches];
        $dynamic = $this->lastDynamic = $this->match(true, $media);
        // Container and style queries, and the styles of what is shown, depend on more than the matches
        $this->restyling = $statesOnly && null !== $previous && !$this->hasStyleQueries && !$this->hasStartingRules && !$this->hasContainerRules() ? $this->restyling($previous, $dynamic, $previousParts) : null;

        $this->counters = [];
        $this->listOrdinals = [];
        $this->counterDepth = 0;
        $this->quoteDepth = 0;
        $this->containerWidgets = [];
        $this->markerScrollers = [];
        // The text of what draws elements that are not widgets, like the label of a button, before it is styled
        if (\count($this->displayFollowers) > 0) {
            $this->followDisplays($media, $statesOnly);
        }
        try {
            $this->compute($root, $dynamic, -1);
        } finally {
            $this->restyling = null;
        }
    }

    /**
     * @return array{array<int, list<int>>, array<int, list<int>>, array<int, array<int, list<int>>>, array<int, array<int, list<int>>>} Rule indexes by widget object id, for the widget, its pseudo-elements, its inline elements and their pseudo-elements
     */
    /**
     * The widgets whose dynamic matches changed since the last cascade, and their ancestors, by object id.
     *
     * @param array{array<int, list<int>>, array<int, list<int>>, array<int, array<int, list<int>>>, array<int, array<int, list<int>>>} $previous
     * @param array{array<int, list<int>>, array<int, list<int>>, array<int, array<int, list<int>>>, array<int, array<int, list<int>>>} $dynamic
     * @param array{array<int, mixed>, array<int, mixed>}                                                                               $previousParts
     *
     * @return array<int, true>
     */
    private function restyling(array $previous, array $dynamic, array $previousParts): array
    {
        $changed = [];
        foreach ([[$previous[0], $dynamic[0]], [$previous[1], $dynamic[1]], [$previous[2], $dynamic[2]], [$previous[3], $dynamic[3]], [$previousParts[0], $this->dynamicPartMatches[0]], [$previousParts[1], $this->dynamicPartMatches[1]]] as [$before, $after]) {
            if ($before === $after) {
                continue;
            }
            foreach ($before + $after as $id => $_) {
                if (($before[$id] ?? null) !== ($after[$id] ?? null)) {
                    $changed[$id] = true;
                }
            }
        }

        $restyling = [];
        foreach ($changed as $id => $_) {
            for ($widget = ($this->widgetsById[$id] ?? null)?->get(); null !== $widget && !isset($restyling[$wid = spl_object_id($widget)]); $widget = $widget->getParent()) {
                $restyling[$wid] = true;
            }
        }

        return $restyling;
    }

    /**
     * The names of the elements of the document, lowercase: read on each match, the inline elements mirrored in it too.
     *
     * @return array<string, true>
     */
    /**
     * The names in the document: of its elements, and, lowercased, its classes and ids, see requiredTags().
     *
     * @return array{array<string, true>, array<string, true>, array<string, true>}
     */
    private function documentNames(?\Dom\Document $document = null): array
    {
        $names = $classes = $ids = [];
        foreach (($document ?? $this->document)->getElementsByTagName('*') as $element) {
            $names[strtolower($element->localName)] = true;
            if ('' !== $class = trim($element->getAttribute('class') ?? '')) {
                foreach (preg_split('/\s+/', strtolower($class)) ?: [] as $name) {
                    $classes[$name] = true;
                }
            }
            if ('' !== $id = $element->getAttribute('id') ?? '') {
                $ids[strtolower($id)] = true;
            }
        }

        return [$names, $classes, $ids];
    }

    /**
     * What each selector of a list needs in the document, outside functions and attribute selectors: the names of the
     * elements of its compounds, each as the names one of which must be there (the type, or an :is() of names only),
     * and its classes and ids, lowercased; null when it cannot tell, like with escapes, which the native engine reads.
     *
     * @return list<array{list<array<string, true>>, list<string>, list<string>}>|null
     */
    private static function requiredTags(string $selector): ?array
    {
        if (str_contains($selector, '\\')) {
            return null;
        }
        $items = [];
        foreach (Selector::splitTopLevel($selector) as $item) {
            $tags = $classes = $ids = [];
            $depth = 0;
            $start = true;
            for ($i = 0, $length = \strlen($item); $i < $length; ++$i) {
                $char = $item[$i];
                if ('(' === $char || '[' === $char) {
                    ++$depth;
                } elseif (')' === $char || ']' === $char) {
                    --$depth;
                } elseif ('"' === $char || "'" === $char) {
                    $end = strpos($item, $char, $i + 1);
                    if (false === $end) {
                        return null;
                    }
                    $i = $end;
                } elseif (0 === $depth && \in_array($char, [' ', '>', '+', '~'], true)) {
                    $start = true;
                    continue;
                } elseif (0 === $depth && ('.' === $char || '#' === $char) && preg_match('/\G[.#](-?[a-zA-Z_][\w-]*)/', $item, $m, 0, $i)) {
                    '.' === $char ? $classes[] = strtolower($m[1]) : $ids[] = strtolower($m[1]);
                    $i += \strlen($m[0]) - 1;
                } elseif (0 === $depth && $start) {
                    if (preg_match('/\G([a-zA-Z][\w-]*)(?![\w|-])/', $item, $m, 0, $i)) {
                        $tags[] = [strtolower($m[1]) => true];
                    } elseif (preg_match('/\G\*?:is\(\s*([a-zA-Z][\w-]*(?:\s*,\s*[a-zA-Z][\w-]*)*)\s*\)/', $item, $m, 0, $i)) {
                        $tags[] = array_fill_keys(array_map(strtolower(...), preg_split('/\s*,\s*/', $m[1]) ?: []), true);
                    }
                }
                $start = false;
            }
            $items[] = [$tags, $classes, $ids];
        }

        return $items;
    }

    /**
     * Whether a selector may match an element of the document, by the names it needs, see requiredTags().
     *
     * @param array{array<string, true>, array<string, true>, array<string, true>} $names
     */
    private function mayMatchNames(string $selector, array $names): bool
    {
        $items = \array_key_exists($selector, $this->requiredTags) ? $this->requiredTags[$selector] : ($this->requiredTags[$selector] = self::requiredTags($selector));
        if (null === $items) {
            return true;
        }
        foreach ($items as [$tags, $classes, $ids]) {
            foreach ($tags as $anyOf) {
                if (!array_intersect_key($anyOf, $names[0])) {
                    continue 2;
                }
            }
            foreach ($classes as $class) {
                if (!isset($names[1][$class])) {
                    continue 2;
                }
            }
            foreach ($ids as $id) {
                if (!isset($names[2][$id])) {
                    continue 2;
                }
            }

            return true;
        }

        return false;
    }

    private function match(bool $dynamic, MediaContext $media): array
    {
        $matches = [];
        $elementMatches = [];
        $inlineMatches = [];
        $inlineElementMatches = [];
        $partMatches = [[], []];
        $present = [];
        $tags = null;
        foreach ($this->rules as $index => $rule) {
            // Container queries depend on the last layout: they are matched on each cascade, like states
            if (($rule->selector->dynamic || [] !== $rule->containers) !== $dynamic) {
                continue;
            }

            // A rule requiring states no widget has cannot match: skip the query
            if ($dynamic && !str_contains($rule->selector->forHtml(), ':not(') && preg_match_all('/\['.Selector::STATE_ATTRIBUTE.'~="([^"]+)"\]/', $rule->selector->forHtml(), $m)) {
                foreach ($m[1] as $state) {
                    if (!($present[$state] ??= null !== $this->document->querySelector(Selector::stateSelector($state)))) {
                        continue 2;
                    }
                }
            }

            foreach ($rule->media as $query) {
                if (!$query->matches($media)) {
                    continue 2;
                }
            }
            // A rule naming elements the document has none of matches nothing: not queried, which can take long, like
            // mroot > :nth-child(2) among thousands of siblings, matched from the right
            $tags ??= $this->documentNames();
            if (!$this->mayMatchNames($rule->selector->forHtml(), $tags)) {
                continue;
            }
            // The elements of a rule of every state: kept while the document stays as it is, like in another media,
            // the terminal of another size, where only which rules apply changes
            if (!$dynamic && \array_key_exists($index, $this->staticRuleElements)) {
                $elements = $this->staticRuleElements[$index];
            } else {
                try {
                    $elements = iterator_to_array(Selector::queryAll($this->document, $rule->selector->forHtml()), false);
                } catch (\Throwable) {
                    // Valid CSS the native engine does not support: skip the rule
                    $elements = [];
                }
                if (!$dynamic) {
                    $this->staticRuleElements[$index] = $elements;
                }
            }
            $seen = [];
            foreach ($elements as $element) {
                // A selector list may return an element once per selector it matches
                if (isset($seen[$elementId = spl_object_id($element)])) {
                    continue;
                }
                $seen[$elementId] = true;
                if ($rule->containers && !$this->matchesContainers($element, $rule->containers)) {
                    continue;
                }
                // Starting styles only give the values transitions start from, of the widgets themselves
                if ($rule->starting && (null !== $rule->selector->pseudoElement || null !== $element->getAttribute(self::INLINE_ATTRIBUTE))) {
                    continue;
                }
                if (null !== $part = $element->getAttribute(self::PART_ATTRIBUTE)) {
                    [$partId, $partName] = explode(':', $part, 2);
                    $partMatches[null === $rule->selector->pseudoElement ? 0 : 1][(int) $partId][$partName][] = $index;
                    continue;
                }
                if (null !== $inline = $element->getAttribute(self::INLINE_ATTRIBUTE)) {
                    [$id, $node] = array_map('intval', explode(':', $inline));
                    if (null === $rule->selector->pseudoElement) {
                        $inlineMatches[$id][$node][] = $index;
                    } else {
                        $inlineElementMatches[$id][$node][] = $index;
                    }
                    continue;
                }
                $id = (int) $element->getAttribute(self::OID_ATTRIBUTE);
                if (null === $rule->selector->pseudoElement) {
                    $matches[$id][] = $index;
                } else {
                    $elementMatches[$id][] = $index;
                }
            }
        }

        if ($dynamic) {
            $this->dynamicPartMatches = $partMatches;
        } else {
            $this->staticPartMatches = $partMatches;
        }

        return [$matches, $elementMatches, $inlineMatches, $inlineElementMatches];
    }

    /**
     * @param array{array<int, list<int>>, array<int, list<int>>, array<int, array<int, list<int>>>, array<int, array<int, list<int>>>} $dynamic Matches of the rules involving states, see match()
     */
    private function compute(AbstractWidget $widget, array $dynamic, int $parentDeclarations): void
    {
        // What counts across the document when the widget is reached, to compute a moving one again alone
        if (isset($this->moving[$widget])) {
            $this->countersAt[$widget] = [$this->counters, $this->counterDepth, $this->quoteDepth];
        }
        $id = spl_object_id($widget);
        // Its matches and those of its descendants as they were, and what it inherits and counts from too: computed as it was
        if (null !== $this->restyling && !isset($this->restyling[$id]) && null !== ($last = $this->computed[$widget] ?? null)
            && [$parentDeclarations, $this->insideHidden, $this->counters, $this->counterDepth, $this->quoteDepth] === [$last[0], $last[1], $last[2], $last[3], $last[4]]
        ) {
            [$this->counters, $this->counterDepth, $this->quoteDepth] = [$last[5], $last[6], $last[7]];
            array_push($this->containerWidgets, ...$last[8]);
            array_push($this->markerScrollers, ...$last[9]);

            return;
        }
        $entry = [$parentDeclarations, $this->insideHidden, $this->counters, $this->counterDepth, $this->quoteDepth, \count($this->containerWidgets), \count($this->markerScrollers)];
        $rules = $this->passStyleQueries([...$this->staticMatches[$id] ?? [], ...$dynamic[0][$id] ?? []], $parentDeclarations);
        $inlineStyle = $this->descriptions[$widget][1]['style'] ?? '';
        // The row lining up the inline content of an element flex in some media only, like a paragraph of md:flex: no
        // box where it is flex, its widgets the items
        if (isset($this->itemRows[$widget]) && preg_match('/^(?:inline-)?(?:flex|grid)\b/i', trim($this->declarations[$parentDeclarations]['display'] ?? ''))) {
            $inlineStyle = 'display: contents';
        }
        // The anonymous row of the cells of a table without rows: a row where its parent is a table, no box where it
        // is not, like the .row of htmx displayed as a table on a wide screen only
        if (isset($this->tableRows[$widget])) {
            $inlineStyle = preg_match('/^(?:inline-)?table\b(?!-)/i', trim($this->declarations[$parentDeclarations]['display'] ?? '')) ? 'display: table-row' : 'display: contents';
        }
        $hints = self::presentationalHints($this->descriptions[$widget][0] ?? '', $this->descriptions[$widget][1] ?? []);
        // In a row lining up the inline content of its parent, where that row is laid out, not where it is no box: a
        // block inline-block in some media only, see computeDeclarations()
        $inRow = 'flex' === strtolower(trim($this->declarations[$parentDeclarations]['display'] ?? ''));
        if (isset($this->lineItems[$widget]) && $inRow) {
            $hints .= ';'.self::LINE_ITEM.': '.$this->lineItems[$widget];
        }
        // A control, which takes the focus when it is clipped to nothing too, see computeDeclarations()
        if ($widget instanceof FocusableInterface && \in_array($this->descriptions[$widget][0] ?? '', ['input', 'select', 'textarea', 'button'], true)) {
            $hints .= ';'.self::CONTROL.': 1';
        }
        if (isset($this->descriptions[$widget])) {
            $hints .= ';'.self::ELEMENT.': 1';
        }
        $element = fn () => [$this->descriptions[$widget][1] ?? [], ...self::siblingPosition($this->elements[$id] ?? null), ...[$this->containerUnits($this->elements[$id] ?? null)]];
        // The @starting-style rules are out of the cascade: with them, the values transitions start from
        $starting = null;
        if ($this->hasStartingRules) {
            $own = array_values(array_filter($rules, fn (int $index) => !$this->rules[$index]->starting));
            if ($own !== $rules) {
                $starting = $this->computeDeclarations($rules, $inlineStyle, $parentDeclarations, $hints, $element);
                $rules = $own;
            }
        }
        $declarations = $this->computeDeclarations($rules, $inlineStyle, $parentDeclarations, $hints, $element);
        $rendered = !$this->insideHidden && 'none' !== ($this->declarations[$declarations]['display'] ?? null);
        // Transitions follow the declared values; animations, over them, do not start transitions
        $declarations = $this->animate($widget, $this->transition($widget, $declarations, $starting, $rendered));
        $style = $widget instanceof InlineContentInterface ? $this->textStyle($declarations) : $this->styleOf($declarations);
        // In a row lining up inline content, where it is laid out: a column before it where the text has a space, which
        // the row drops at the start of a line, not before a block on a line of its own
        if (isset($this->documentHeights[$declarations])) {
            $style = ($style ?? new Style())->withDocumentHeight();
        }
        $block = $inRow && isset($this->lineItems[$widget]) && self::isLineBlock($this->declarations[$declarations]['display'] ?? null, $this->lineItems[$widget]);
        // Not where the page sets a margin before it, like the ms-2 of Bootstrap, which stands for that space
        $margin = $this->declarations[$declarations];
        if ($inRow && isset($this->spacedItems[$widget]) && !$block && !array_filter(array_intersect_key($margin, array_flip(['margin', 'margin-left', 'margin-right', 'margin-inline', 'margin-inline-start', 'margin-inline-end'])), static fn (string $value) => !preg_match('/^\s*0*(?:\.0*)?(?:[a-z]+|%)?\s*$/i', $value))) {
            $style = ($style ?? new Style())->withSpaceBefore();
        }
        // A block lined up with inline content, displayed as a block here: a line of its own, as wide as it is set
        if ($block) {
            $style = ($style ?? new Style())->withOwnLine();
        }
        $elementRules = [...$this->staticElementMatches[$id] ?? [], ...$dynamic[1][$id] ?? []];
        // A ::before displayed as a block that clears, like the .menu::before { clear: both; display: block } of SQLite:
        // its content below the floats, like CSS, which puts that empty block, then what follows it, there; its box where
        // it is, its border and background beside them, see Renderer::renderContainer(), or below them where it does not
        // lay its content out among them, see LayoutEngine::layoutFloats()
        if ($elementRules && null !== $clear = $this->beforeClear($elementRules, $this->declarations[$declarations], $this->declarations[$parentDeclarations] ?? [])) {
            $style = ($style ?? new Style())->withBeforeClear($clear);
        }

        // A style computed again may be another object of the same values, like those of a blend mode or a filter: the
        // same, the widget is not drawn again
        $previous = $this->styles[$widget] ?? null;
        $changed = (null === $style ? null !== $previous : !$style->equals($previous))
            || ($this->elementRules[$widget] ?? []) !== $elementRules
            // Sub-elements take from the widget: their rules, and accent-color
            || (($elementRules || isset($this->declarations[$declarations]['accent-color'])) && ($this->widgetDeclarations[$widget] ?? null) !== $declarations);

        if (null === $style) {
            unset($this->styles[$widget]);
        } else {
            $this->styles[$widget] = $style;
        }
        $this->widgetDeclarations[$widget] = $declarations;
        if ($elementRules) {
            $this->elementRules[$widget] = $elementRules;
        } else {
            unset($this->elementRules[$widget]);
        }

        if ($changed) {
            $widget->invalidate();
            $this->restyled = true;
        }
        if ([] !== self::containerTypesOf($this->declarations[$declarations])) {
            $this->containerWidgets[] = $widget;
        }

        // A widget that is not displayed does not count, nor do its descendants or pseudo-elements
        $displayed = 'none' !== ($this->declarations[$declarations]['display'] ?? null);
        $counters = $this->counters;
        if ($displayed) {
            $this->applyCounters($this->declarations[$declarations]);
        }
        $this->enterCounterScope();

        // Pushed to the widget, which invalidates itself when the formats or decorations change
        // white-space: pre or nowrap, the lines kept whole
        if ($widget instanceof LineWrappingInterface) {
            $widget->setLineWrapping(!\in_array(self::whiteSpace($this->declarations[$declarations]), ['nowrap', 'pre'], true));
        }
        if ($widget instanceof FieldSizingInterface) {
            $widget->setContentSized('content' === strtolower(trim($this->declarations[$declarations]['field-sizing'] ?? '')));
        }
        // appearance: none, the control without its own look
        if ($widget instanceof NativeAppearanceInterface) {
            $appearance = $this->declarations[$declarations]['appearance'] ?? $this->declarations[$declarations]['-webkit-appearance'] ?? null;
            // A part of a control, like the field of a select, looks like the control unless it says otherwise
            // none, its initial value too, like all: unset resetting a button
            $native = null !== $appearance ? !\in_array(strtolower(trim($appearance)), ['none', 'initial', 'unset'], true) : (null !== ($parent = $widget->getParent()) && isset($this->nativeAppearances[$parent]) ? $this->nativeAppearances[$parent] : true);
            // A button the page gives a border, like browsers: the border is its look, not brackets inside it
            // appearance: auto or button, like the -webkit-appearance: button of the reboot of Bootstrap, is the same
            // A textarea the page gives a border too: the border, not the rules above and below its text
            if ((null === $appearance || \in_array(strtolower(trim($appearance)), ['auto', 'button', 'textarea', 'textfield'], true)) && ($widget instanceof ButtonWidget || $widget instanceof EditorWidget) && null !== ($border = $this->styleOf($declarations)?->getBorder()) && $border->top + $border->right + $border->bottom + $border->left > 0) {
                $native = false;
            }
            // A button the page gives a background or no border, like the border: 0 and the background of the buttons of
            // an accordion of Bootstrap: its own look too, like browsers, which drop the native one then
            if ($native && (null === $appearance || \in_array(strtolower(trim($appearance)), ['auto', 'button'], true)) && $widget instanceof ButtonWidget && null !== ($element = $this->elements[$id] ?? null) && $this->authorLook($element, $this->declarations[$declarations])) {
                $native = false;
            }
            // appearance: base-select, a select drawn as a dropdown keeps its picker icon, its field without brackets
            $base = $widget instanceof DropdownWidget && null !== $appearance && 'base-select' === strtolower(trim($appearance));
            $this->nativeAppearances[$widget] = $native && !$base;
            $widget->setNativeAppearance($native);
        }
        if ($widget instanceof GeneratedContentInterface) {
            $attributes = $this->descriptions[$widget][1] ?? [];
            unset($this->positionedPseudos[$widget]);
            $this->pseudoOwner = $widget;
            // Their images, like content: url(icon.png), drawn in their lines or items
            $this->contentImages = [];
            // Drawn by its borders, like the caret of a dropdown button of Bootstrap: a glyph, after its margin
            $triangle = function (?string $content, string $pseudoElement) use ($elementRules, $declarations): ?string {
                if ('' !== $content || null === $triangle = $this->pseudoTriangle($elementRules, $pseudoElement, $this->declarations[$declarations])) {
                    return $content;
                }

                return self::paddedPseudo($triangle[0], $triangle[1]);
            };
            $widget->setGeneratedContent(
                // The ::before of an item holding blocks is drawn by its first text, like its marker (see itemBefore())
                // the item itself when it starts with a box, like <li><div> or the <a class="flex"> of a breadcrumb of Bulma
                $widget instanceof ContainerWidget && (('li' === ($this->descriptions[$widget][0] ?? null) && ($widget->all()[0] ?? null) instanceof RichTextWidget) || null !== $this->textCarryingPseudo($widget, 'before')) ? null : $triangle($this->contentOverride($elementRules, 'before', $declarations, $attributes), 'before'),
                // Those its text carries, see blockPseudo()
                $widget instanceof ContainerWidget && null !== $this->textCarryingPseudo($widget, 'after') ? null : $triangle($this->contentOverride($elementRules, 'after', $declarations, $attributes), 'after'),
            );
            if ($widget instanceof ContainerWidget) {
                $widget->setGeneratedImages($this->contentImages);
            }
            $this->contentImages = null;
            $this->pseudoOwner = null;
        }
        // ::scroll-button(): the buttons of a scroll container that have content
        if ($widget instanceof ContainerWidget) {
            $buttons = [];
            foreach (null !== $style?->getScroll() ? ['up', 'down', 'left', 'right'] : [] as $direction) {
                if (null !== $content = $this->generatedContent($elementRules, 'scroll-button('.$direction.')', $declarations, $this->descriptions[$widget][1] ?? [])) {
                    $buttons[$direction] = $content;
                }
            }
            $widget->setScrollButtons($buttons);
        }
        if ($widget instanceof ElementContentInterface) {
            $attributes = $this->descriptions[$widget][1] ?? [];
            foreach ($widget->getContentElements() as $element) {
                $widget->setElementContent($element, $this->contentOverride($elementRules, $element, $declarations, $attributes));
            }
        }
        if ($widget instanceof InlineContentInterface) {
            $revision = $widget->getRenderRevision();
            $this->formatText($widget, $id, $declarations, $elementRules, $dynamic);
            // The inline elements styled otherwise, like by a container query: drawn again
            $this->restyled = $this->restyled || $revision !== $widget->getRenderRevision();
        }
        if ($widget instanceof PartsInterface) {
            $this->styleParts($widget, $id, $declarations);
        }

        if ($widget instanceof ParentInterface) {
            $insideHidden = $this->insideHidden;
            $this->insideHidden = !$rendered;
            $this->ancestry[] = $declarations;
            foreach ($widget->all() as $child) {
                $this->compute($child, $dynamic, $declarations);
            }
            array_pop($this->ancestry);
            $this->insideHidden = $insideHidden;
        }
        // scroll-marker-group: the ::scroll-marker of the children of a scroll container, once they are computed
        if ($widget instanceof ContainerWidget) {
            $position = null !== $style?->getScroll() ? strtolower(trim($this->declarations[$declarations]['scroll-marker-group'] ?? 'none')) : 'none';
            $markers = [];
            foreach ('before' === $position || 'after' === $position ? $widget->all() : [] as $child) {
                if (null !== $content = $this->generatedContent($this->elementRules[$child] ?? [], 'scroll-marker', $this->widgetDeclarations[$child] ?? -1, $this->descriptions[$child][1] ?? [])) {
                    $markers[] = [$child, $content];
                }
            }
            $widget->setScrollMarkers('before' === $position || 'after' === $position ? $position : null, $markers);
            if ($markers) {
                $this->markerScrollers[] = $widget;
            }
        }
        $this->leaveCounterScope();
        if (!$displayed) {
            $this->counters = $counters;
        }

        // Invalidations above changed the revision: remember the new one, children included
        if (isset($this->synced[$widget])) {
            $synced = $this->synced[$widget];
            $synced[0] = $widget->getRenderRevision();
            $this->synced[$widget] = $synced;
        }

        $this->computed[$widget] = [$entry[0], $entry[1], $entry[2], $entry[3], $entry[4], $this->counters, $this->counterDepth, $this->quoteDepth, \array_slice($this->containerWidgets, $entry[5]), \array_slice($this->markerScrollers, $entry[6])];
    }

    /**
     * Tells a widget the style of its parts and the content of their markers: what ::marker gives, none for
     * list-style: none, display: none or a hidden ::-webkit-details-marker.
     */
    private function styleParts(AbstractWidget&PartsInterface $widget, int $id, int $declarations): void
    {
        foreach ($widget->getParts() as $name => [, $attributes]) {
            $rules = [...$this->staticPartMatches[0][$id][$name] ?? [], ...$this->dynamicPartMatches[0][$id][$name] ?? []];
            $pseudoRules = [...$this->staticPartMatches[1][$id][$name] ?? [], ...$this->dynamicPartMatches[1][$id][$name] ?? []];
            if (!$rules && !$pseudoRules) {
                $widget->setPartStyle($name, null, null);
                if ($widget instanceof CollapsibleWidget) {
                    $widget->setPartContent($name, null, null);
                    $widget->setSummaryHidden(false);
                }
                continue;
            }
            $part = $this->computeDeclarations($rules, $attributes['style'] ?? '', $declarations, element: static fn () => [$attributes, null, null]);
            $computed = $this->declarations[$part];
            // none in the shorthand, in any order, unless it is the image beside a type
            $shorthand = array_map(strtolower(...), Value::split($computed['list-style'] ?? ''));
            $listStyle = strtolower(trim($computed['list-style-type'] ?? (['none'] === array_values(array_diff($shorthand, ['inside', 'outside'])) ? 'none' : '')));
            $marker = match (true) {
                'none' === $listStyle, 'none' === ($computed['display'] ?? null), '' === $this->contentOverride($pseudoRules, '-webkit-details-marker', $part, $attributes) => '',
                default => $this->contentOverride($pseudoRules, 'marker', $part, $attributes),
            };
            $widget->setPartStyle($name, $rules ? $this->styleOf($part) : null, $marker);
            if ($widget instanceof CollapsibleWidget && 'summary' === $name) {
                $widget->setSummaryHidden('none' === strtolower(trim($computed['display'] ?? '')));
            }
            // Its ::before and ::after, the latter at the end of a flex row whose content is spread, like a "+"
            if ($widget instanceof CollapsibleWidget) {
                $apart = \in_array(strtolower(trim($computed['display'] ?? '')), ['flex', 'inline-flex'], true) && \in_array(strtolower(trim($computed['justify-content'] ?? '')), ['space-between', 'flex-end', 'end', 'right'], true);
                // Placed out of the flow: at the end of the line, like a "+" at right: 0; none when of no size and
                // clipped, like the hidden "Collapse" of the toggles of rustdoc
                [$before, $after] = [$this->generatedContent($pseudoRules, 'before', $part, $attributes), $this->generatedContent($pseudoRules, 'after', $part, $attributes)];
                foreach (['before', 'after'] as $pseudoElement) {
                    $own = $this->cascadeDeclarations(array_values(array_filter($pseudoRules, fn (int $index) => self::pseudoElementMatches($this->rules[$index]->selector->pseudoElement, $pseudoElement))), []);
                    $clipped = \in_array(strtolower(trim($own['overflow'] ?? '')), ['hidden', 'clip'], true)
                        && (\in_array(trim($own['width'] ?? ''), ['0', '0px'], true) || \in_array(trim($own['height'] ?? ''), ['0', '0px'], true));
                    if ($clipped) {
                        'before' === $pseudoElement ? $before = null : $after = null;
                    } elseif ('after' === $pseudoElement && preg_match('/^(?:absolute|fixed)\b/i', trim($own['position'] ?? ''))) {
                        $apart = true;
                    }
                }
                $widget->setPartContent($name, $before, $after, $apart);
            }
        }
    }

    /**
     * Returns the id of the computed declarations of an element.
     *
     * Elements matching the same rules, with the same inline style and parent, share their computed values, unless
     * they use attr(), sibling-index() or sibling-count(), which are resolved for each element.
     *
     * @param list<int>                                                   $rules
     * @param (\Closure(): array{array<string, string>, ?int, ?int})|null $element The attributes of the element, its position among its siblings and their count
     */
    private function computeDeclarations(array $rules, string $inlineStyle, int $parentDeclarations, string $hints = '', ?\Closure $element = null): int
    {
        $key = implode(',', $rules).'|'.$parentDeclarations.'|'.$inlineStyle.'|'.$hints;
        if (false === ($this->elementDependent[$key] ?? null)) {
            return $this->declarationIds[$key];
        }

        $cascaded = $this->cascadeDeclarations($rules, '' !== $inlineStyle ? $this->parseInline($inlineStyle, true) : [], '' !== $hints ? $this->parseInline($hints) : []);
        // Clipped to nothing, like the visually hidden pattern of .sr-only: not drawn, its text still in the names
        // A control so hidden still takes the focus, like the checkbox of a toggle of Tailwind (peer sr-only), which
        // its label or a sibling of it shows: drawn as nothing, like opacity: 0
        if (self::clippedToNothing($cascaded)) {
            if (isset($cascaded[self::CONTROL])) {
                $cascaded['opacity'] = '0';
            } else {
                $cascaded['display'] = 'none';
            }
        }
        unset($cascaded[self::CONTROL]);
        // flex-direction lays out the items of a flex container only: an element of a document not one stays a block,
        // like the .flex-none.flex-row of Tachyons below the media its .flex-l makes it one in; a widget laid out by its
        // direction alone keeps it
        if (isset($cascaded[self::ELEMENT]) && (isset($cascaded['flex-direction']) || isset($cascaded['flex-flow']))
            && \in_array(strtolower(trim($cascaded['display'] ?? '')), ['', 'block', 'inline', 'inline-block', 'flow-root', 'list-item', 'grid', 'inline-grid', 'table', 'table-cell', 'table-row', 'contents', 'none'], true)) {
            unset($cascaded['flex-direction'], $cascaded['flex-flow']);
        }
        unset($cascaded[self::ELEMENT]);
        // Inline code in reverse video, apart from the text in a terminal of one font: not where the page gives it its
        // own colors, like browsers, which draw them as the page says, like the light chips of the code of Ruby
        if (isset($cascaded[self::CODE]) && '-tui-invert' === strtolower(trim($cascaded['filter'] ?? '')) && array_intersect_key($cascaded, array_flip(['color', 'background-color', 'background']))) {
            unset($cascaded['filter']);
        }
        unset($cascaded[self::CODE]);
        // The height of <html> or <body>, like the height: 100% of the footer pushed down of GNU Emacs: a least one, its
        // content going on past it, the document scrolling, like browsers, whatever their overflow, which is the one of
        // the viewport; still the height of the percentages in it, see compute()
        $documentHeight = false;
        if (isset($cascaded[self::DOCUMENT], $cascaded['height']) && !preg_match('/^\s*(?:auto|initial|unset|revert(?:-layer)?)\s*$/i', $cascaded['height'])) {
            $cascaded['min-height'] = isset($cascaded['min-height']) ? 'max('.$cascaded['height'].', '.$cascaded['min-height'].')' : $cascaded['height'];
            unset($cascaded['height']);
            $documentHeight = true;
        }
        // Nor a most one, like the max-height: 100% of the <html> of Go: the document scrolling past it, not cut to the
        // screen
        if (isset($cascaded[self::DOCUMENT])) {
            unset($cascaded['max-height']);
        }
        unset($cascaded[self::DOCUMENT]);
        // A block lined up with the inline content around it, displayed as a block here: a line of its own in its row,
        // like the items of the menu of TypeScript on a narrow screen
        // Without a display, the one of the element: inline for a link, a block for a <li>
        $block = isset($cascaded[self::LINE_ITEM]) && self::isLineBlock($cascaded['display'] ?? null, $cascaded[self::LINE_ITEM]);
        // As wide as its row unless it has a width, which a line of its own keeps, see compute()
        if ($block && preg_match('/^(?:auto|initial|unset|revert(?:-layer)?|)$/i', trim($cascaded['width'] ?? $cascaded['inline-size'] ?? ''))) {
            $cascaded['flex'] = '0 0 100%';
        }
        unset($cascaded[self::LINE_ITEM]);
        $this->elementDependent[$key] = null !== $element && self::usesElementFunctions($cascaded);
        // Container units depend on the layout, like container queries: styles are computed again after it
        if ($this->elementDependent[$key] && !$this->usesContainerUnits) {
            foreach ($cascaded as $value) {
                if (preg_match(self::CONTAINER_UNITS, $value)) {
                    $this->usesContainerUnits = true;
                    break;
                }
            }
        }
        if (!$this->elementDependent[$key]) {
            $this->declarations[] = $this->inherit($cascaded, $this->declarations[$parentDeclarations] ?? [], isset($this->declarations[$parentDeclarations]) ? $parentDeclarations : null);
            if ($documentHeight) {
                $this->documentHeights[array_key_last($this->declarations)] = true;
            }

            return $this->declarationIds[$key] = array_key_last($this->declarations);
        }

        // Resolved before inheritance, so that the children inherit the values of the element
        [$attributes, $index, $count, $containers] = $element() + [3 => null];
        $cascaded = $this->resolveElementFunctions($cascaded, $attributes, $index, $count, $containers);
        $elementKey = $key.'|'.hash('xxh128', serialize($cascaded));
        if (!isset($this->declarationIds[$elementKey])) {
            $this->declarations[] = $this->inherit($cascaded, $this->declarations[$parentDeclarations] ?? [], isset($this->declarations[$parentDeclarations]) ? $parentDeclarations : null);
            $this->declarationIds[$elementKey] = array_key_last($this->declarations);
            if ($documentHeight) {
                $this->documentHeights[$this->declarationIds[$elementKey]] = true;
            }
        }

        return $this->declarationIds[$elementKey];
    }

    /**
     * @param array<string, string> $declarations
     */
    private static function usesElementFunctions(array $declarations): bool
    {
        foreach ($declarations as $property => $value) {
            if ('content' !== $property && (str_contains($value, 'attr(') || str_contains($value, 'sibling-') || preg_match(self::CONTAINER_UNITS, $value))) {
                return true;
            }
        }

        return false;
    }

    /**
     * The position of an element among the elements of its parent, from 1, and their count.
     *
     * @return array{?int, ?int}
     */
    private static function siblingPosition(?\Dom\Element $element): array
    {
        if (null === $element) {
            return [null, null];
        }
        $parent = $element->parentElement;
        if (null === $parent) {
            return [1, 1];
        }
        $index = 1;
        for ($sibling = $element->previousElementSibling; null !== $sibling; $sibling = $sibling->previousElementSibling) {
            ++$index;
        }

        return [$index, $parent->childElementCount];
    }

    /**
     * Replaces sibling-index(), sibling-count() and attr() by their values for an element; a declaration whose
     * attr() has no value nor fallback is invalid at computed-value time, and dropped.
     *
     * The content property keeps its attr(), which generated content resolves.
     *
     * @param array<string, string> $declarations
     * @param array<string, string> $attributes
     *
     * @return array<string, string>
     */
    private function resolveElementFunctions(array $declarations, array $attributes, ?int $index, ?int $count, ?array $containers = null): array
    {
        foreach ($declarations as $property => $value) {
            if ('content' === $property) {
                continue;
            }
            try {
                // Container units, as pixels: a share of the width or the height of the nearest container
                if (null !== $containers && preg_match(self::CONTAINER_UNITS, $value)) {
                    $value = preg_replace_callback(self::CONTAINER_UNITS, static function (array $m) use ($containers): string {
                        [$width, $height] = $containers;
                        $size = match (strtolower($m[2])) {
                            'w', 'i' => $width,
                            'h', 'b' => $height,
                            'min' => min($width, $height),
                            default => max($width, $height),
                        };

                        return rtrim(rtrim(number_format((float) $m[1] * $size / 100, 3, '.', ''), '0'), '.').'px';
                    }, $value) ?? $value;
                }
                if (str_contains($value, 'sibling-')) {
                    $value = preg_replace_callback('/(?<![\w-])sibling-(index|count)\(\s*\)/i', static fn (array $m) => (string) (('index' === strtolower($m[1]) ? $index : $count) ?? throw new InvalidArgumentException('The element has no siblings.')), $value) ?? $value;
                }
                if (str_contains($value, 'attr(')) {
                    $value = $this->substituteAttributes($value, $attributes);
                }
                $declarations[$property] = $value;
            } catch (InvalidArgumentException) {
                unset($declarations[$property]);
            }
        }

        return $declarations;
    }

    /**
     * Replaces each attr() by the value of the attribute, of the type it names: a string without one (or with
     * raw-string), a value of the syntax of type(), a number with number, or a dimension with a unit.
     *
     * @param array<string, string> $attributes
     */
    private function substituteAttributes(string $value, array $attributes): string
    {
        $offset = 0;
        while (null !== $start = Value::findOutsideStrings($value, '/(?<![\w-])attr\(/i', $offset)) {
            $end = Value::closingParenthesis($value, $start + 4);
            $arguments = Selector::splitTopLevel(substr($value, $start + 5, $end - $start - 5));
            $fallback = isset($arguments[1]) ? implode(', ', \array_slice($arguments, 1)) : null;
            if (!preg_match('/^\s*([a-z_][\w-]*)(?:\s+(.+?))?\s*$/is', $arguments[0] ?? '', $a)) {
                throw new InvalidArgumentException(\sprintf('Invalid attr() in "%s".', $value));
            }
            $type = strtolower(trim($a[2] ?? ''));
            $attribute = $attributes[strtolower($a[1])] ?? null;
            $replacement = null === $attribute ? null : match (true) {
                '' === $type, 'raw-string' === $type => '"'.addcslashes($attribute, '"\\').'"',
                'number' === $type => is_numeric(trim($attribute)) ? trim($attribute) : null,
                (bool) preg_match('/^type\(\s*(.+)\s*\)$/s', $type, $t) => !str_contains($attribute, 'var(') && !str_contains($attribute, 'attr(') && PropertySyntax::matches($t[1], $attribute, $this->media ?? MediaContext::fromEnvironment()) ? trim($attribute) : null,
                '%' === $type, (bool) preg_match('/^[a-z]+$/', $type) => is_numeric(trim($attribute)) ? trim($attribute).$type : null,
                default => throw new InvalidArgumentException(\sprintf('Invalid attr() type "%s".', $type)),
            };
            // A fallback can use attr() too, not the value of an attribute
            $offset = null !== $replacement ? $start + \strlen($replacement) : $start;
            $replacement ??= $fallback ?? throw new InvalidArgumentException(\sprintf('The "%s" attribute has no value of its type.', $a[1]));
            $value = substr($value, 0, $start).$replacement.substr($value, $end + 1);
        }

        return $value;
    }

    /**
     * The declarations of a widget with those of its animations at this time, like CSS animations: a later animation
     * of the list wins over an earlier one, and the children inherit them.
     */
    private function animate(AbstractWidget $widget, int $declarations): int
    {
        $computed = $this->declarations[$declarations];
        $animations = isset($computed['animation']) || isset($computed['animation-name']) ? Animation::listFromDeclarations($computed) : [];
        $animations = array_filter($animations, fn (Animation $animation) => (bool) ($this->keyframes[$animation->name] ?? null));
        if (!$animations) {
            unset($this->animationStarts[$widget]);
            if (!($this->transitions[$widget]['running'] ?? [])) {
                unset($this->moving[$widget]);
            }

            return $declarations;
        }

        // An animation starts when it applies, and again when it changes
        $now = ($this->clock)();
        $started = $this->animationStarts[$widget] ?? [];
        $starts = [];
        $running = false;
        $values = [];
        foreach ($animations as $id => $animation) {
            // A scroll-driven animation: where its timeline is, not the time
            if ($animation->isScrollDriven()) {
                $this->scrollAnimated[$widget] = true;
                if (null !== ($fraction = $this->timelineFraction($widget, $animation)) && null !== $progress = $animation->progressAt($fraction)) {
                    $values = $animation->interpolate($this->keyframes[$animation->name], round($progress * 256) / 256, $computed, $this->media ?? new MediaContext()) + $values;
                }
                continue;
            }
            $signature = serialize($animation);
            $start = ($started[$id][0] ?? null) === $signature ? $started[$id][1] : $now;
            $starts[$id] = [$signature, $start];
            $elapsed = $now - $start;
            $running = $running || $animation->isRunning($elapsed);
            if (null !== $progress = $animation->progress($elapsed)) {
                $values = $animation->interpolate($this->keyframes[$animation->name], round($progress * 256) / 256, $computed, $this->media ?? new MediaContext()) + $values;
            }
        }
        $this->animationStarts[$widget] = $starts;
        if ($running) {
            $this->animating = true;
            $this->moving[$widget] = true;
        } elseif (!($this->transitions[$widget]['running'] ?? [])) {
            unset($this->moving[$widget]);
        }
        if (!$values) {
            return $declarations;
        }
        $key = 'animation|'.$declarations.'|'.serialize($values);
        if (!isset($this->declarationIds[$key])) {
            $this->declarations[] = $values + $computed;
            $this->declarationIds[$key] = array_key_last($this->declarations);
        }

        return $this->declarationIds[$key];
    }

    /**
     * Where the timeline of a scroll-driven animation of a widget is: 0 where its range starts, 1 where it ends, from
     * how its scroll container is scrolled; null when it has none.
     */
    private function timelineFraction(AbstractWidget $widget, Animation $animation): ?float
    {
        $timeline = $animation->timeline;
        $axis = 'block';
        $view = false;
        $scroller = null;
        $inset = [0, 0];
        if (preg_match('/^scroll\(\s*(.*?)\s*\)$/', $timeline, $m)) {
            $parts = Value::split($m[1]);
            $kind = array_values(array_intersect($parts, ['nearest', 'root', 'self']))[0] ?? 'nearest';
            $axis = array_values(array_intersect($parts, ['block', 'inline', 'x', 'y']))[0] ?? 'block';
            $scroller = match ($kind) {
                'self' => $widget instanceof ContainerWidget ? $widget : null,
                'root' => $this->root instanceof ContainerWidget ? $this->root : null,
                default => $this->scrollerOf($widget),
            };
            $subject = $widget;
        } elseif (preg_match('/^view\(\s*(.*?)\s*\)$/', $timeline, $m)) {
            $parts = Value::split($m[1]);
            $axis = array_values(array_intersect($parts, ['block', 'inline', 'x', 'y']))[0] ?? 'block';
            $inset = self::viewInset(implode(' ', array_diff($parts, ['block', 'inline', 'x', 'y'])));
            $view = true;
            $subject = $widget;
            $scroller = $this->scrollerOf($widget);
        } elseif (str_starts_with($timeline, '--') && null !== $named = $this->namedTimeline($widget, $timeline)) {
            [$subject, $view, $axis, $inset] = $named;
            $scroller = $view ? $this->scrollerOf($subject) : ($subject instanceof ContainerWidget ? $subject : null);
        } else {
            return null;
        }
        if (null === $scroller || (!$view && !$this->isScrollContainer($scroller))) {
            return null;
        }
        $vertical = 'block' === $axis || 'y' === $axis;
        $at = $vertical ? $scroller->getScrollTop() : $scroller->getScrollLeft();
        $size = $vertical ? $scroller->getClientHeight() : $scroller->getClientWidth();
        $total = $vertical ? $scroller->getScrollHeight() : $scroller->getScrollWidth();
        if (!$view) {
            // How far it is scrolled, the range in percentages of it
            if ($total <= $size) {
                return null;
            }
            $fraction = $at / ($total - $size);
            [[, $from], [, $to]] = $animation->range;

            return $to !== $from ? ($fraction * 100 - $from) / ($to - $from) : 0.0;
        }

        // A view timeline: from where the subject enters the scroll container to where it leaves it, as laid out
        $offset = null !== $this->scrollStateOf ? (($this->scrollStateOf)($subject)['offset'] ?? null) : null;
        if (null === $offset || [] === ($offset[$vertical ? 0 : 1] ?? [])) {
            return null;
        }
        [$start, $length] = $offset[$vertical ? 0 : 1];
        $viewport = max(1, $size - $inset[0] - $inset[1]);
        // The scroll positions where each range starts and ends
        $ranges = [
            'cover' => [$start - $viewport - $inset[1], $start + $length - $inset[1]],
            'contain' => $length <= $viewport ? [$start + $length - $viewport - $inset[1], $start - $inset[1]] : [$start - $inset[1], $start + $length - $viewport - $inset[1]],
            'entry' => [$start - $viewport - $inset[1], $start + min($length, $viewport) - $viewport - $inset[1]],
            'exit' => [$start + max(0, $length - $viewport) - $inset[1], $start + $length - $inset[1]],
        ];
        $ranges['normal'] = $ranges['cover'];
        $ranges['entry-crossing'] = [$start - $viewport - $inset[1], $start + $length - $viewport - $inset[1]];
        $ranges['exit-crossing'] = [$start - $inset[1], $start + $length - $inset[1]];
        $position = static fn (array $edge): float => $ranges[$edge[0]][0] + ($ranges[$edge[0]][1] - $ranges[$edge[0]][0]) * $edge[1] / 100;
        $from = $position($animation->range[0]);
        $to = $position($animation->range[1]);

        return $to !== $from ? ($at - $from) / ($to - $from) : ($at >= $to ? 1.0 : 0.0);
    }

    /**
     * The insets of view() or view-timeline-inset, in cells: the start then the end.
     *
     * @return array{int, int}
     */
    private static function viewInset(string $value): array
    {
        $parts = array_map(static fn (string $part) => 'auto' === $part ? 0 : max(0, Length::toCells($part, new MediaContext(), vertical: true) ?? 0), Value::split($value));

        return [$parts[0] ?? 0, $parts[1] ?? $parts[0] ?? 0];
    }

    private function isScrollContainer(ContainerWidget $widget): bool
    {
        if (null !== ($this->styles[$widget] ?? null)?->getScroll()) {
            return true;
        }
        $declarations = $this->declarations[$this->widgetDeclarations[$widget] ?? -1] ?? [];
        foreach (['overflow', 'overflow-x', 'overflow-y', 'overflow-inline', 'overflow-block'] as $property) {
            if (\in_array('hidden', Value::split(strtolower($declarations[$property] ?? '')), true)) {
                return true;
            }
        }

        return false;
    }

    /**
     * The nearest scroll container around a widget.
     */
    private function scrollerOf(AbstractWidget $widget): ?ContainerWidget
    {
        for ($ancestor = $widget->getParent(); null !== $ancestor; $ancestor = $ancestor->getParent()) {
            if ($ancestor instanceof ContainerWidget && $this->isScrollContainer($ancestor)) {
                return $ancestor;
            }
        }

        return null;
    }

    /**
     * The element that defines a named scroll or view timeline a widget sees: itself or an ancestor, or inside an
     * ancestor whose timeline-scope names it; whether it is a view one, its axis and insets.
     *
     * @return array{AbstractWidget, bool, string, array{int, int}}|null
     */
    private function namedTimeline(AbstractWidget $widget, string $name): ?array
    {
        $defines = function (AbstractWidget $candidate) use ($name): ?array {
            $declarations = $this->declarations[$this->widgetDeclarations[$candidate] ?? -1] ?? [];
            foreach (['scroll' => false, 'view' => true] as $kind => $view) {
                $parts = Value::split($declarations[$kind.'-timeline'] ?? '');
                $names = isset($declarations[$kind.'-timeline-name']) ? array_map('trim', explode(',', $declarations[$kind.'-timeline-name'])) : [$parts[0] ?? ''];
                if (\in_array($name, $names, true)) {
                    $axis = strtolower(trim($declarations[$kind.'-timeline-axis'] ?? ($parts[1] ?? 'block')));

                    return [$candidate, $view, $axis, $view ? self::viewInset($declarations['view-timeline-inset'] ?? implode(' ', \array_slice($parts, 2))) : [0, 0]];
                }
            }

            return null;
        };
        $search = static function (AbstractWidget $root, int $depth = 0) use (&$search, $defines): ?array {
            if (null !== $found = $defines($root)) {
                return $found;
            }
            foreach ($depth < 32 && $root instanceof ParentInterface ? $root->all() : [] as $child) {
                if (null !== $found = $search($child, $depth + 1)) {
                    return $found;
                }
            }

            return null;
        };
        for ($current = $widget; null !== $current; $current = $current->getParent()) {
            if (null !== $found = $defines($current)) {
                return $found;
            }
            $scope = $this->declarations[$this->widgetDeclarations[$current] ?? -1]['timeline-scope'] ?? '';
            if ('all' === strtolower(trim($scope)) || \in_array($name, array_map('trim', explode(',', $scope)), true)) {
                if (null !== $found = $search($current)) {
                    return $found;
                }
            }
        }

        return null;
    }

    /**
     * The declarations of a widget with its running transitions, like CSS transitions: when the value of a transitioned
     * property changes, it goes from the old one to the new one over the duration; a value that cannot be in between
     * changes at once. An element shown, first or again after display: none, has no old values: its transitions start
     * from its @starting-style values, if any.
     *
     * @param int|null $starting The declarations with the @starting-style rules, null without any
     * @param bool     $rendered Whether it is displayed, its ancestors too
     */
    private function transition(AbstractWidget $widget, int $declarations, ?int $starting = null, bool $rendered = true): int
    {
        $computed = $this->declarations[$declarations];
        $transition = isset($computed['transition']) || isset($computed['transition-property']) || isset($computed['transition-duration']) ? Transition::fromDeclarations($computed) : null;
        if (null === $transition) {
            unset($this->transitions[$widget]);

            return $declarations;
        }

        $media = $this->media ?? new MediaContext();
        $now = ($this->clock)();
        $state = $this->transitions[$widget] ?? ['values' => [], 'running' => [], 'shown' => false];
        $shown = $rendered && !$state['shown'];
        $state['shown'] = $rendered;
        if (!$rendered) {
            $state['running'] = [];
        }
        $startingValues = $shown && null !== $starting ? $this->declarations[$starting] : [];
        $properties = isset($transition->properties['all']) ? array_keys($computed + $state['values']) : array_keys($transition->properties);
        $values = [];
        foreach ($properties as $property) {
            // A custom property only with a type, registered by @property, like CSS
            if ((str_starts_with($property, '--') && '*' === ($this->registered[$property][2] ?? '*')) || str_starts_with($property, 'transition') || str_starts_with($property, 'animation') || null === $timing = $transition->of($property)) {
                continue;
            }
            // The background color set by the shorthand
            if ('background-color' === $property && !isset($computed['background-color']) && isset($computed['background'])) {
                $property = 'background';
            }
            $to = $computed[$property] ?? null;
            if (null === $to) {
                unset($state['values'][$property], $state['running'][$property]);
                continue;
            }
            // Shown: from its starting style, or at once
            $previous = $shown ? $startingValues[$property] ?? null : $state['values'][$property] ?? null;
            if ($shown) {
                unset($state['running'][$property]);
            }
            if (null !== $previous && $previous !== $to) {
                // From where a running transition is, or from the old value
                $from = isset($state['running'][$property]) ? self::transitionValue($state['running'][$property], $now, $media) : $previous;
                // interpolate-size: allow-keywords, or calc-size(): a height from or to auto goes through the rows of the content
                $end = $to;
                if (\in_array($property, ['height', 'min-height', 'max-height', 'block-size', 'min-block-size', 'max-block-size'], true)) {
                    [$from, $end] = $this->sizeEnds($widget, $from, $to, 'allow-keywords' === strtolower(trim($computed['interpolate-size'] ?? '')), $media);
                }
                // allow-discrete: other values change halfway, visibility stays visible while one end is
                if ($timing[3] || Animation::isInterpolable($from, $end, $media)) {
                    $state['running'][$property] = [$from, $end, $now, $timing[0], $timing[1], $timing[2]];
                } else {
                    unset($state['running'][$property]);
                }
            }
            $state['values'][$property] = $to;
            if (isset($state['running'][$property])) {
                [, , $start, $duration, $delay] = $state['running'][$property];
                if (($now - $start - $delay) / $duration >= 1.0) {
                    unset($state['running'][$property]);
                } else {
                    $values[$property] = self::transitionValue($state['running'][$property], $now, $media);
                }
            }
        }
        $this->transitions[$widget] = $state;

        if (!$values) {
            if (!isset($this->animationStarts[$widget])) {
                unset($this->moving[$widget]);
            }

            return $declarations;
        }
        $this->animating = true;
        $this->moving[$widget] = true;
        $key = 'transition|'.$declarations.'|'.serialize($values);
        if (!isset($this->declarationIds[$key])) {
            $this->declarations[] = $values + $computed;
            $this->declarationIds[$key] = array_key_last($this->declarations);
        }

        return $this->declarationIds[$key];
    }

    /**
     * The ends of a height transition as rows, when one is a size keyword (auto, fit-content...) and interpolate-size
     * allows it, or a calc-size(): the keyword or the size of calc-size() is the height of the content in the last frame.
     *
     * @return array{string, string}
     */
    private function sizeEnds(AbstractWidget $widget, string $from, string $to, bool $keywords, MediaContext $media): array
    {
        $content = null !== $this->rowsOf ? ($this->rowsOf)($widget) : null;
        $rows = static function (string $value) use ($content, $keywords, $media): ?int {
            $value = strtolower(trim($value));
            $intrinsic = ['auto', 'fit-content', 'min-content', 'max-content', 'stretch'];
            if (\in_array($value, $intrinsic, true)) {
                return $keywords ? $content : null;
            }
            if (preg_match('/^calc-size\(\s*([^,()]+(?:\([^()]*\))?)\s*(?:,(.*))?\)$/s', $value, $m)) {
                $basis = trim($m[1]);
                $size = \in_array($basis, [...$intrinsic, 'any'], true) ? $content : Length::toCells($basis, $media, vertical: true);
                if (null === $size) {
                    return null;
                }
                $expression = trim($m[2] ?? '');

                return '' === $expression ? $size : Length::toCells('calc('.preg_replace('/(?<![\w-])size(?![\w-])/', $size.'lh', $expression).')', $media, vertical: true);
            }

            return Length::toCells($value, $media, vertical: true);
        };
        $isKeyword = static fn (string $value): bool => (bool) preg_match('/^(?:auto|fit-content|min-content|max-content|stretch|calc-size\(.*\))$/is', trim($value));
        if (!$isKeyword($from) && !$isKeyword($to)) {
            return [$from, $to];
        }
        $start = $rows($from);
        $end = $rows($to);

        // In lines: a bare number would be a color of the palette
        return null !== $start && null !== $end ? [$start.'lh', $end.'lh'] : [$from, $to];
    }

    /**
     * @param array{string, string, float, float, float, array{0: string, 1: int, 2: 'start'|'end'|'none'|'both', 3?: list<float>, points?: list<array{float, float}>}} $running
     */
    private static function transitionValue(array $running, float $now, MediaContext $media): string
    {
        [$from, $to, $start, $duration, $delay, $timing] = $running;
        $t = ($now - $start - $delay) / $duration;

        return $t <= 0.0 ? $from : Animation::between($from, $to, Animation::ease($timing, min(1.0, $t)), $media);
    }

    /**
     * The style of a text block: a line that a negative text-indent pulls out goes over the left padding.
     */
    private function textStyle(int $declarations): ?Style
    {
        if (\array_key_exists($declarations, $this->textStylesByDeclarations)) {
            return $this->textStylesByDeclarations[$declarations];
        }
        $style = $this->styleOf($declarations);
        $indent = isset($this->declarations[$declarations]['text-indent']) ? self::textIndent($this->substitute($this->declarations[$declarations]['text-indent'], $this->declarations[$declarations]), $this->media ?? new MediaContext()) : null;
        $padding = $style?->getPadding();
        // A percentage depends on the width: its line starts at the padding, the others pushed in
        if (null !== $style && null !== $padding && null !== $indent && $indent[0] < 0 && 0.0 === $indent[1] && ($taken = min(-$indent[0], $padding->left)) > 0) {
            $style = $style->withPadding($padding = new Padding($padding->top, $padding->right, $padding->bottom, $padding->left - $taken, $padding->percents));
        }
        // hanging-punctuation: first and last, the punctuation goes over the padding, as far as two columns
        $hang = self::hangingPunctuation($this->declarations[$declarations]['hanging-punctuation'] ?? '') ?? [];
        $this->hangs[$declarations] = [0, 0];
        $this->hangEnds[$declarations] = \in_array('allow-end', $hang, true) || \in_array('force-end', $hang, true);
        // allow-end and force-end: a stop or a comma ending a line, over the right padding too
        $end = \in_array('allow-end', $hang, true) || \in_array('force-end', $hang, true);
        if (null !== $style && null !== $padding && (\in_array('first', $hang, true) || \in_array('last', $hang, true) || $end)) {
            $this->hangs[$declarations] = [\in_array('first', $hang, true) ? min(2, $padding->left) : 0, \in_array('last', $hang, true) || $end ? min(2, $padding->right) : 0];
            if ([0, 0] !== $this->hangs[$declarations]) {
                $style = $style->withPadding(new Padding($padding->top, $padding->right - $this->hangs[$declarations][1], $padding->bottom, $padding->left - $this->hangs[$declarations][0], $padding->percents));
            }
        }

        return $this->textStylesByDeclarations[$declarations] = $style;
    }

    /**
     * The shadows of a text-shadow, up to 4, the first one over the others: their offsets in whole cells, from -2 to 2,
     * and their colors, null for that of the text dimmed; null for none, invalid, or only shadows under the text.
     *
     * @return list<array{int, int, ?Color}>|null
     */
    private static function textShadow(?string $value, MediaContext $media): ?array
    {
        if (null === $value || 'none' === strtolower(trim($value))) {
            return null;
        }
        // Each shadow of the list, up to 4; one that is not valid makes the whole list so, like CSS
        $shadows = [];
        foreach (\array_slice(Selector::splitTopLevel($value), 0, 4) as $layer) {
            $lengths = [];
            $color = null;
            foreach (Value::split($layer) as $part) {
                if (null !== $cells = Length::toCells($part, $media, 1 === \count($lengths))) {
                    $lengths[] = $cells;
                } elseif ('currentcolor' !== strtolower($part) && ($found = Value::color($part, $media)) instanceof Color) {
                    $color = $found;
                } elseif ('currentcolor' !== strtolower($part)) {
                    return null;
                }
            }
            if (\count($lengths) < 2 || \count($lengths) > 3) {
                return null;
            }
            [$x, $y] = [max(-2, min(2, $lengths[0])), max(-2, min(2, $lengths[1]))];
            // Under the text, one not moved shows nothing
            if (0 !== $x || 0 !== $y) {
                $shadows[] = [$x, $y, $color];
            }
        }

        return $shadows ?: null;
    }

    /**
     * Registers the texts of a custom highlight, styled by the ::highlight() rules of its name, or removes it.
     *
     * @param list<string>|null $texts
     */
    public function setHighlight(string $name, ?array $texts, bool $matchCase = false): void
    {
        // Registered again, it is drawn over the others
        $this->highlightsChanged = $this->highlightsChanged || isset($this->highlights[$name]) || [] !== ($texts ?? []);
        unset($this->highlights[$name]);
        $texts = array_values(array_filter($texts ?? [], static fn (string $text) => '' !== trim($text)));
        if ($texts) {
            $this->highlights[$name] = [$texts, $matchCase];
        }
    }

    /**
     * Sets the links visited, by their href as written, which :visited matches and :link no longer does.
     *
     * @param iterable<string> $hrefs
     */
    public function setVisitedLinks(iterable $hrefs): void
    {
        $visited = [];
        foreach ($hrefs as $href) {
            if ('' !== $href = trim($href)) {
                $visited[$href] = true;
            }
        }
        if ($visited !== $this->visited) {
            $this->visited = $visited;
            // Every link mirrored again, its state with it, the styles computed again
            $this->synced = new \WeakMap();
            $this->highlightsChanged = true;
        }
    }

    /**
     * Sets the URL relative links are resolved against, like the href of <base>: a link is visited by its address as
     * written or as resolved.
     */
    public function setBaseUrl(?string $baseUrl): void
    {
        if ($baseUrl !== $this->baseUrl) {
            $this->baseUrl = $baseUrl;
            $this->synced = new \WeakMap();
            $this->highlightsChanged = true;
        }
    }

    private function isVisited(string $href): bool
    {
        if ('' === $href = trim($href)) {
            return false;
        }

        return isset($this->visited[$href]) || (null !== $this->baseUrl && isset($this->visited[UrlResolver::resolve($href, $this->baseUrl)]));
    }

    /**
     * Marks a link as visited, which :visited then matches, like a link followed.
     */
    public function markVisited(string $href): void
    {
        if ('' !== $href = trim($href)) {
            $this->setVisitedLinks([...array_keys($this->visited), $href]);
        }
    }

    /**
     * @return list<string> The hrefs of the links visited, see setVisitedLinks()
     */
    public function getVisitedLinks(): array
    {
        return array_map(strval(...), array_keys($this->visited));
    }

    /**
     * Sets the texts a text fragment goes to, drawn as ::target-text: the first match of each pattern in its text block.
     *
     * @param list<array{AbstractWidget, string}> $texts
     */
    public function setTargetTexts(array $texts): void
    {
        $this->highlightsChanged = $this->highlightsChanged || $texts || $this->targetTexts;
        $this->targetTexts = $texts;
    }

    /**
     * The highlights of a text block: a pattern of their texts, the spaces in them matching any whitespace, and the
     * style its ::highlight() rules set, of the properties they declare only; those without rules are not drawn.
     *
     * @param list<int>             $elementRules
     * @param array<string, string> $computed
     *
     * @return list<array{0: string, 1: Style, 2?: int}>
     */
    private function highlights(AbstractWidget $widget, array $elementRules, array $computed): array
    {
        $highlights = [];
        // The text a text fragment goes to: its first match only, over the custom highlights
        $targetStyle = null;
        $targets = array_filter($this->targetTexts, static fn (array $target) => $target[0] === $widget);
        if ($targets) {
            $matching = array_values(array_filter($elementRules, fn (int $index) => 'target-text' === $this->rules[$index]->selector->pseudoElement));
            $declarations = $matching ? array_diff_key($this->cascadeDeclarations($matching, []), self::GENERATING) : [];
            $targetStyle = $declarations ? $this->computeStyle(array_intersect_key($this->inherit($declarations, $computed), $declarations), $this->media ?? new MediaContext()) : null;
        }
        foreach ($this->highlights as $name => [$texts, $matchCase]) {
            $matching = array_values(array_filter($elementRules, fn (int $index) => 'highlight('.$name.')' === $this->rules[$index]->selector->pseudoElement));
            $declarations = $matching ? array_diff_key($this->cascadeDeclarations($matching, []), self::GENERATING) : [];
            if (!$declarations) {
                continue;
            }
            $style = $this->computeStyle(array_intersect_key($this->inherit($declarations, $computed), $declarations), $this->media ?? new MediaContext());
            $words = array_map(static fn (string $text) => str_replace(' ', '\\s+', preg_quote(trim((string) preg_replace('/\s+/u', ' ', $text)), '/')), $texts);
            $highlights[] = ['/'.implode('|', $words).'/u'.($matchCase ? '' : 'i'), $style];
        }
        foreach (null !== $targetStyle ? $targets : [] as [, $pattern]) {
            $highlights[] = [$pattern, $targetStyle, 1];
        }

        return $highlights;
    }

    private function styleOf(int $declarations): ?Style
    {
        if (!\array_key_exists($declarations, $this->stylesByDeclarations)) {
            $this->stylesByDeclarations[$declarations] = $this->declarations[$declarations] ? $this->computeStyle($this->declarations[$declarations], $this->media) : null;
        }

        return $this->stylesByDeclarations[$declarations];
    }

    /**
     * Computes the formats of a text block and of its inline elements.
     *
     * @param list<int>                                                                                                                 $elementRules The pseudo-element rules matching the widget
     * @param array{array<int, list<int>>, array<int, list<int>>, array<int, array<int, list<int>>>, array<int, array<int, list<int>>>} $dynamic
     */
    private function formatText(AbstractWidget&InlineContentInterface $widget, int $id, int $declarations, array $elementRules, array $dynamic): void
    {
        $computed = self::fontLonghands($this->declarations[$declarations]);
        // The text of a block in an anonymous one, like a <div class="text-truncate"> of text: its lines are those of
        // the block, cut with its text-overflow, and clamped by its line-clamp when they are all it holds
        if (isset($this->anonymous[$widget]) && null !== ($block = $widget->getParent()) && null !== ($blockDeclarations = $this->declarations[$this->widgetDeclarations[$block] ?? -1] ?? null)) {
            $own = $block instanceof ParentInterface && [$widget] === $block->all() ? ['text-overflow', 'line-clamp', '-webkit-line-clamp'] : ['text-overflow'];
            foreach ($own as $property) {
                if (isset($blockDeclarations[$property]) && !isset($computed[$property])) {
                    $computed[$property] = $blockDeclarations[$property];
                }
            }
        }
        $this->quoteLanguage = $this->languages[(string) $id] ?? '';
        $attributes = $this->descriptions[$widget][1] ?? [];
        // The images of its generated content, like content: url() on ::before
        $this->contentImages = [];

        // In document order, so that counters count like CSS: ::before, the inline elements, ::after
        unset($this->positionedPseudos[$widget]);
        $this->pseudoOwner = $widget;
        $before = $this->generatedContent($elementRules, 'before', $declarations, $attributes) ?? $this->itemBefore($widget, $id, $computed);
        $after = $this->generatedContent($elementRules, 'after', $declarations, $attributes);
        // The text of a block, its first or last content, carries its ::before or ::after, inline, like CSS
        $blockBefore = null === $before ? $this->blockPseudo($widget, 'before') : null;
        $blockAfter = null === $after ? $this->blockPseudo($widget, 'after') : null;
        $before ??= $blockBefore[0] ?? null;
        $after ??= $blockAfter[0] ?? null;
        $this->pseudoOwner = null;
        $marker = null;
        $beforeStyle = null !== $before ? $this->pseudoStyle($blockBefore[1] ?? $elementRules, 'before', $computed) : null;
        // Hidden, before an inside marker joins it: the marker is still drawn
        $before = $this->unseenPseudo($before, $beforeStyle, $blockBefore[1] ?? $elementRules, 'before');
        $beforeStyle = null !== $before ? $beforeStyle : null;
        $markerStyle = null;
        $markerImage = null;
        // Its ::before too, like CSS: a page that draws its own bullet with it sets list-style: none
        if (null !== $marker = $this->marker($widget, $id, $computed, $elementRules, $declarations, $markerImage)) {
            $markerStyle = $this->pseudoStyle($elementRules, 'marker', $computed);
            // Inside, the marker is the start of the text, before its ::before; outside, the default, the lines are
            // aligned after it
            if (self::markerInside($computed) && null === $before) {
                [$before, $marker, $beforeStyle, $markerStyle] = [$marker, null, $markerStyle, null];
            } elseif (self::markerInside($computed)) {
                [$before, $marker, $markerStyle] = [$marker.$before, null, null];
            }
        }
        $afterStyle = null !== $after ? $this->pseudoStyle($blockAfter[1] ?? $elementRules, 'after', $computed) : null;
        // Drawn by their borders, like a caret after a link: a glyph
        if ('' === $before && null !== $triangle = $this->pseudoTriangle($blockBefore[1] ?? $elementRules, 'before', $computed)) {
            [$before, $beforeStyle] = $triangle;
        }
        if ('' === $after && null !== $triangle = $this->pseudoTriangle($blockAfter[1] ?? $elementRules, 'after', $computed)) {
            [$after, $afterStyle] = $triangle;
        }
        $after = $this->unseenPseudo($after, $afterStyle, $blockAfter[1] ?? $elementRules, 'after');
        // Their horizontal padding, in their background, like the padding-right of the divider of a breadcrumb
        $before = self::paddedPseudo($before, $beforeStyle);
        $after = self::paddedPseudo($after, $afterStyle);
        $formats = [];
        $index = 0;
        $this->formatNodes($widget->getInlineNodes(), $id, $declarations, $dynamic, $index, $formats);

        $indent = isset($computed['text-indent']) ? self::textIndent($this->substitute($computed['text-indent'], $computed), $this->media ?? new MediaContext()) : null;
        $firstLetter = array_values(array_filter($elementRules, fn (int $index) => 'first-letter' === $this->rules[$index]->selector->pseudoElement));
        $firstLine = array_values(array_filter($elementRules, fn (int $index) => 'first-line' === $this->rules[$index]->selector->pseudoElement));
        $block = new InlineFormat(
            script: self::script($computed, false),
            rtl: 'rtl' === strtolower(trim($computed['direction'] ?? '')),
            orphans: (int) ($computed['orphans'] ?? 2),
            widows: (int) ($computed['widows'] ?? 2),
            firstLine: $firstLine ? $this->computeStyle($this->inherit($this->cascadeDeclarations($firstLine, []), $computed), $this->media ?? new MediaContext()) : null,
            firstLetter: $firstLetter ? $this->computeStyle($this->inherit($this->cascadeDeclarations($firstLetter, []), $computed), $this->media ?? new MediaContext()) : null,
            before: $before,
            after: $after,
            textTransform: self::textTransform($computed),
            whiteSpace: self::whiteSpace($computed),
            ellipsis: $this->ellipsis($computed),
            indent: $indent[0] ?? 0,
            lineGap: isset($computed['line-height']) ? self::lineGap($this->substitute($computed['line-height'], $computed), $this->media ?? new MediaContext()) ?? 0 : 0,
            letterSpacing: isset($computed['letter-spacing']) ? self::letterSpacing($this->substitute($computed['letter-spacing'], $computed), $this->media ?? new MediaContext()) ?? 0 : 0,
            lineClamp: preg_match('/^\d{1,5}$/', trim($computed['line-clamp'] ?? $computed['-webkit-line-clamp'] ?? '')) ? (int) trim($computed['line-clamp'] ?? $computed['-webkit-line-clamp']) : 0,
            // line-break: anywhere breaks between any two characters too, there being no rules of languages to relax
            breakAll: 'break-all' === strtolower(trim($computed['word-break'] ?? '')) || 'anywhere' === strtolower(trim($computed['line-break'] ?? '')),
            keepAll: 'keep-all' === strtolower(trim($computed['word-break'] ?? '')),
            emphasis: ($emphasis = $this->emphasis($computed))[0],
            emphasisUnder: $emphasis[1],
            emphasisStyle: $emphasis[2],
            highlights: $this->highlights($widget, $elementRules, $computed),
            textShadow: self::textShadow($computed['text-shadow'] ?? null, $this->media ?? new MediaContext()),
            hyphens: 'none' !== strtolower(trim($computed['hyphens'] ?? '')),
            hyphenator: $this->hyphenator($computed),
            unicodeBidi: self::unicodeBidi($computed),
            hyphen: self::hyphenateCharacter($computed['hyphenate-character'] ?? '') ?? '-',
            // Like browsers, the limits are of hyphens: auto, which breaks words at their soft hyphens here
            hyphenLimits: 'auto' === strtolower(trim($computed['hyphens'] ?? '')) ? self::hyphenLimits($computed['hyphenate-limit-chars'] ?? 'auto') : null,
            // A space is a whole column, far more than the eighth of an em of browsers: off unless asked for
            autospace: self::autospace($computed['text-autospace'] ?? 'no-autospace') ?? [],
            hang: $this->hangs[$declarations] ?? [0, 0],
            hangEnd: $this->hangEnds[$declarations] ?? false,
            initialLetter: $firstLetter ? self::initialLetter($this->cascadeDeclarations($firstLetter, [])['initial-letter'] ?? '') : null,
            balance: \in_array('balance', Value::split(strtolower($computed['text-wrap'] ?? $computed['text-wrap-style'] ?? '')), true),
            pretty: \in_array('pretty', Value::split(strtolower($computed['text-wrap'] ?? $computed['text-wrap-style'] ?? '')), true),
            tabSize: Length::tabSize($computed['tab-size'] ?? '', $this->media ?? new MediaContext()) ?? 8,
            wordSpacing: isset($computed['word-spacing']) ? self::letterSpacing($this->substitute($computed['word-spacing'], $computed), $this->media ?? new MediaContext()) ?? 0 : 0,
            alignLast: self::alignLast($computed),
            justify: match (strtolower(trim($computed['text-justify'] ?? ''))) {
                'none' => 'none',
                'inter-word' => 'inter-word',
                'inter-character', 'distribute' => 'inter-character',
                default => 'auto',
            },
            marker: $marker,
            markerImage: null !== $marker ? $markerImage : null,
            indentPercent: $indent[1] ?? 0.0,
            hanging: $indent[2] ?? false,
            eachLine: $indent[3] ?? false,
            beforeStyle: $beforeStyle,
            afterStyle: $afterStyle,
            markerStyle: $markerStyle,
        );

        if ($widget instanceof RichTextWidget) {
            $widget->setGeneratedImages($this->contentImages);
        }
        $this->contentImages = null;
        $widget->setInlineFormats($block, $formats);
    }

    /**
     * @param list<InlineNode>                                                                                                          $nodes
     * @param array{array<int, list<int>>, array<int, list<int>>, array<int, array<int, list<int>>>, array<int, array<int, list<int>>>} $dynamic
     * @param array<int, InlineFormat>                                                                                                  $formats
     */
    private function formatNodes(array $nodes, int $id, int $parentDeclarations, array $dynamic, int &$index, array &$formats): void
    {
        $siblings = \count(array_filter($nodes, static fn (InlineNode $node) => !$node->isText()));
        $sibling = 0;
        foreach ($nodes as $node) {
            if ($node->isText()) {
                continue;
            }

            $nodeIndex = $index++;
            $this->quoteLanguage = $this->languages[$id.':'.$nodeIndex] ?? '';
            $position = ++$sibling;
            $rules = $this->passStyleQueries([...$this->staticInlineMatches[$id][$nodeIndex] ?? [], ...$dynamic[2][$id][$nodeIndex] ?? []], $parentDeclarations);
            $pseudoRules = [...$this->staticInlineElementMatches[$id][$nodeIndex] ?? [], ...$dynamic[3][$id][$nodeIndex] ?? []];
            // Presentational hints of inline elements too, like <font color>
            $declarations = $this->computeDeclarations($rules, $node->attributes['style'] ?? '', $parentDeclarations, self::presentationalHints((string) $node->tagName, $node->attributes), static fn () => [$node->attributes, $position, $siblings]);
            $computed = self::fontLonghands($this->declarations[$declarations]);
            $hidden = 'none' === ($computed['display'] ?? null);
            if (!$hidden) {
                $this->applyCounters($computed);
            }

            $this->enterCounterScope();
            $before = $hidden ? null : $this->generatedContent($pseudoRules, 'before', $declarations, $node->attributes);
            $this->formatNodes($node->children, $id, $declarations, $dynamic, $index, $formats);
            $after = $hidden ? null : $this->generatedContent($pseudoRules, 'after', $declarations, $node->attributes);
            $this->leaveCounterScope();

            $beforeStyle = null !== $before ? $this->pseudoStyle($pseudoRules, 'before', $computed) : null;
            $afterStyle = null !== $after ? $this->pseudoStyle($pseudoRules, 'after', $computed) : null;
            // Out of the flow: not seen, like the "#" of the headings of the Laravel docs until hovered, or moved by its
            // left margin wholly before its place (its right one moves it not), like that "#" in the margin of the page,
            // where the line cannot draw: no room in the line
            $outside = fn (?Style $style, ?string $content, string $pseudoElement): bool => null !== $style
                && (true === $style->getTransparent() || true === $style->getInvisible() || ($style->getMarginPull()->left ?? 0) >= max(1, mb_strwidth($content ?? '')))
                && (bool) preg_match('/^(?:absolute|fixed)\b/i', trim($this->cascadeDeclarations(array_values(array_filter($pseudoRules, fn (int $index) => self::pseudoElementMatches($this->rules[$index]->selector->pseudoElement, $pseudoElement))), [])['position'] ?? ''));
            if ($outside($beforeStyle, $before, 'before')) {
                [$before, $beforeStyle] = [null, null];
            }
            if ($outside($afterStyle, $after, 'after')) {
                [$after, $afterStyle] = [null, null];
            }
            // Drawn by its borders, like the caret of a dropdown of Bootstrap: a glyph
            foreach (['before', 'after'] as $pseudoElement) {
                if ('' === ${$pseudoElement} && null !== $triangle = $this->pseudoTriangle($pseudoRules, $pseudoElement, $computed)) {
                    [${$pseudoElement}, ${$pseudoElement.'Style'}] = $triangle;
                }
            }
            $before = self::paddedPseudo($this->unseenPseudo($before, $beforeStyle, $pseudoRules, 'before'), $beforeStyle);
            $after = self::paddedPseudo($this->unseenPseudo($after, $afterStyle, $pseudoRules, 'after'), $afterStyle);

            // Horizontal padding, margins and borders apply to inline elements too, like CSS
            $inlineStyle = $this->styleOf($declarations);
            $padding = $inlineStyle?->getPadding();
            $margin = $inlineStyle?->getMargin();
            $border = $inlineStyle?->getBorder();
            // An empty element drawn by its borders, like the arrow of a menu of the Node.js docs: a glyph, no border
            if (!$hidden && [] === $node->children && null === $before && null !== $triangle = $this->borderTriangle($this->declarations[$declarations], $computed)) {
                [$before, $border] = [$triangle[0], null];
                $beforeStyle = null !== $triangle[1] ? $this->computeStyle($this->inherit(['color' => $triangle[1]], $computed), $this->media ?? new MediaContext()) : null;
            }
            // The vertical line of the border pattern on that side (column 0 on the left, 2 on the right), in its color
            $line = static fn (int $width, int $side, int $column): ?string => $width > 0 && null !== $border ? (new Style(color: $border->sideColors[$side] ?? $border->color ?? $inlineStyle->getColor()))->apply($border->getSidePattern($side)->getChars()[1][$column] ?: '│') : null;
            $formats[$nodeIndex] = new InlineFormat(
                box: [min(20, $margin->left ?? 0), min(20, $padding->left ?? 0), min(20, $padding->right ?? 0), min(20, $margin->right ?? 0)],
                borderLeft: $line($border->left ?? 0, 3, 0),
                borderRight: $line($border->right ?? 0, 1, 2),
                style: $inlineStyle,
                hidden: $hidden,
                before: $before,
                after: $after,
                textTransform: self::textTransform($computed),
                whiteSpace: self::whiteSpace($computed),
                atomic: (bool) preg_match('/^inline-block\b|^inline\s+flow-root\b/i', trim($computed['display'] ?? '')),
                letterSpacing: isset($computed['letter-spacing']) ? self::letterSpacing($this->substitute($computed['letter-spacing'], $computed), $this->media ?? new MediaContext()) ?? 0 : 0,
                wordSpacing: isset($computed['word-spacing']) ? self::letterSpacing($this->substitute($computed['word-spacing'], $computed), $this->media ?? new MediaContext()) ?? 0 : 0,
                script: self::script($computed),
                cloneDecoration: 'clone' === strtolower(trim($computed['box-decoration-break'] ?? $computed['-webkit-box-decoration-break'] ?? '')),
                beforeStyle: $beforeStyle,
                afterStyle: $afterStyle,
                ruby: self::rubyDisplay($computed['display'] ?? ''),
                hyphenator: $this->hyphenator($computed),
                rubyUnder: 'under' === strtolower(trim($computed['ruby-position'] ?? '')),
                rubyAlign: self::rubyAlign($computed['ruby-align'] ?? ''),
                emphasis: ($emphasis = $this->emphasis($computed))[0],
                emphasisUnder: $emphasis[1],
                emphasisStyle: $emphasis[2],
                rtl: 'rtl' === strtolower(trim($computed['direction'] ?? '')),
                unicodeBidi: self::unicodeBidi($computed),
            );
        }
    }

    /**
     * @param array<string, string> $computed
     *
     * @return 'normal'|'embed'|'isolate'|'bidi-override'|'isolate-override'|'plaintext'
     */
    private static function unicodeBidi(array $computed): string
    {
        $value = strtolower(trim($computed['unicode-bidi'] ?? ''));

        return \in_array($value, ['embed', 'isolate', 'bidi-override', 'isolate-override', 'plaintext'], true) ? $value : 'normal';
    }

    /**
     * The patterns that break the words of text with hyphens: auto, those of its language (see quoteLanguage).
     *
     * @param array<string, string> $computed
     */
    private function hyphenator(array $computed): ?Hyphenator
    {
        return 'auto' === strtolower(trim($computed['hyphens'] ?? '')) && '' !== $this->quoteLanguage ? $this->hyphenators->get($this->quoteLanguage) : null;
    }

    /**
     * @return 'ruby'|'ruby-base'|'ruby-text'|'ruby-base-container'|'ruby-text-container'|null
     */
    private static function rubyDisplay(string $display): ?string
    {
        $display = strtolower(trim($display));

        return \in_array($display, ['ruby', 'ruby-base', 'ruby-text', 'ruby-base-container', 'ruby-text-container'], true) ? $display : null;
    }

    /**
     * The longhands the text-emphasis shorthand sets: its style and its color, currentcolor when it has none.
     *
     * @return array{text-emphasis-style: string, text-emphasis-color: string}
     */
    private static function emphasisLonghands(string $value): array
    {
        $keyword = strtolower(trim($value));
        if (\in_array($keyword, ['initial', 'inherit', 'unset', 'revert', 'revert-layer'], true)) {
            return ['text-emphasis-style' => $keyword, 'text-emphasis-color' => $keyword];
        }
        $style = [];
        $color = 'currentcolor';
        foreach (Value::split($value) as $part) {
            if (preg_match('/^(?:none|filled|open|dot|circle|double-circle|triangle|sesame)$/i', $part) || preg_match('/^["\']/', $part)) {
                $style[] = $part;
            } else {
                $color = $part;
            }
        }

        return ['text-emphasis-style' => [] !== $style ? implode(' ', $style) : 'none', 'text-emphasis-color' => $color];
    }

    /**
     * The emphasis mark of text-emphasis-style (and the shorthand), drawn over each character, whether it is under them
     * (text-emphasis-position), and its color (text-emphasis-color), the cascade having set them from the shorthand.
     *
     * @param array<string, string> $computed
     *
     * @return array{?string, bool, ?Style}
     */
    private function emphasis(array $computed): array
    {
        $style = $computed['text-emphasis-style'] ?? null;
        $color = $computed['text-emphasis-color'] ?? null;
        $style = trim($style ?? '');
        if ('' === $style || 'none' === strtolower($style)) {
            return [null, false, null];
        }

        $mark = null;
        if (preg_match('/^(["\'])(.*)\1$/s', $style, $m)) {
            // A string: its first character, like browsers
            $mark = grapheme_substr(StringUtils::stripControlBytes(StringUtils::sanitizeUtf8($m[2])), 0, 1) ?: null;
        } else {
            $words = array_map('strtolower', Value::split($style));
            $open = \in_array('open', $words, true);
            $shape = array_values(array_diff($words, ['filled', 'open']))[0] ?? 'circle';
            $mark = match ($shape) {
                'dot' => $open ? '◦' : '•',
                'circle' => $open ? '○' : '●',
                'double-circle' => $open ? '◎' : '◉',
                'triangle' => $open ? '△' : '▲',
                'sesame' => $open ? '﹆' : '﹅',
                default => null,
            };
        }
        $resolved = null !== $color && 'currentcolor' !== strtolower(trim($color)) ? Value::color($this->substitute($color, $computed), $this->media ?? new MediaContext()) : null;

        return [
            $mark,
            \in_array('under', Value::split(strtolower($computed['text-emphasis-position'] ?? '')), true),
            $resolved instanceof Color ? new Style(color: $resolved) : null,
        ];
    }

    /**
     * @return 'start'|'center'|'space-around'|'space-between'
     */
    private static function rubyAlign(string $align): string
    {
        $align = strtolower(trim($align));

        return \in_array($align, ['start', 'center', 'space-between'], true) ? $align : 'space-around';
    }

    /**
     * Returns the content generated by ::before or ::after rules.
     *
     * @param list<int>             $rules
     * @param array<string, string> $attributes Of the element, for attr()
     */
    private function generatedContent(array $rules, string $pseudoElement, int $parentDeclarations, array $attributes): ?string
    {
        $matching = array_values(array_filter($rules, fn (int $index) => self::pseudoElementMatches($this->rules[$index]->selector->pseudoElement, $pseudoElement)));
        if (!$matching) {
            return null;
        }

        $declarations = $this->inherit($this->cascadeDeclarations($matching, []), $this->declarations[$parentDeclarations] ?? [], isset($this->declarations[$parentDeclarations]) ? $parentDeclarations : null);
        if (!isset($declarations['content']) || 'none' === ($declarations['display'] ?? null)) {
            return null;
        }
        if ($this->keepsOutOfFlow($pseudoElement, $declarations, $attributes)) {
            return null;
        }
        // A pseudo-element is a child of its element: it counts too, like h2::before { counter-increment: section }
        $this->applyCounters($declarations);
        $this->quotes = self::quotePairs($declarations['quotes'] ?? 'auto', $this->quoteLanguage) ?? self::quotePairs('auto', $this->quoteLanguage) ?? [];

        return $this->parseContent($this->substitute($declarations['content'], $declarations), $attributes);
    }

    /**
     * Returns the style of generated content, like ::before { color: red }, drawn over that of its element; null
     * when its rules only generate it.
     *
     * @param list<int>             $rules
     * @param array<string, string> $computed Of the element, what the pseudo-element inherits
     */
    private function pseudoStyle(array $rules, string $pseudoElement, array $computed): ?Style
    {
        $matching = array_values(array_filter($rules, fn (int $index) => self::pseudoElementMatches($this->rules[$index]->selector->pseudoElement, $pseudoElement)));
        $declarations = $matching ? array_diff_key($this->cascadeDeclarations($matching, []), self::GENERATING) : [];
        if (!$declarations) {
            return null;
        }

        return $this->computeStyle($this->inherit($declarations, $computed), $this->media ?? new MediaContext());
    }

    /**
     * Whether the pseudo-element of a rule is the one asked for: ::scroll-button(*) is each of them.
     */
    private static function pseudoElementMatches(?string $rule, string $wanted): bool
    {
        return $rule === $wanted || ('scroll-button(*)' === $rule && str_starts_with($wanted, 'scroll-button('));
    }

    /**
     * Returns the content a ::before or ::after rule sets, '' for none, or
     * null when no rule sets any: the widget then keeps its own decoration.
     *
     * @param list<int>             $rules
     * @param array<string, string> $attributes
     */
    private function contentOverride(array $rules, string $pseudoElement, int $parentDeclarations, array $attributes): ?string
    {
        $matching = array_values(array_filter($rules, fn (int $index) => self::pseudoElementMatches($this->rules[$index]->selector->pseudoElement, $pseudoElement)));
        if (!$matching) {
            return null;
        }

        // Neither display nor content is inherited: what the element inherits only for the content it has
        $own = $this->cascadeDeclarations($matching, []);
        if ('none' === ($own['display'] ?? null)) {
            return '';
        }
        if (!isset($own['content'])) {
            return null;
        }
        $declarations = $this->inherit($own, $this->declarations[$parentDeclarations] ?? [], isset($this->declarations[$parentDeclarations]) ? $parentDeclarations : null);
        if ($this->keepsOutOfFlow($pseudoElement, $declarations, $attributes)) {
            return '';
        }
        $this->applyCounters($declarations);
        $this->quotes = self::quotePairs($declarations['quotes'] ?? 'auto', $this->quoteLanguage) ?? self::quotePairs('auto', $this->quoteLanguage) ?? [];

        return $this->parseContent($this->substitute($declarations['content'], $declarations), $attributes) ?? '';
    }

    /**
     * Returns the ::before content of an <li> holding blocks, drawn by its first text like its marker.
     */
    /**
     * The <li> a text block is the first text of, holding blocks after it, like a nested list; null when it is not.
     */
    private function itemOfFirstText(AbstractWidget $widget, int $id, bool $nested = false, array $computed = []): ?\Dom\Element
    {
        $item = $widget->getParent();
        // An anonymous text block is no element: the widgets say where it is
        if (isset($this->anonymous[$widget])) {
            // Nested, for the marker: the first text of the first box of the item too, like <li><div>text
            for ($current = $widget, $depth = 0; $nested && $depth < 4 && $item instanceof ParentInterface && 'li' !== ($this->descriptions[$item][0] ?? null) && $this->firstInFlow($item, $widget, $computed) === $current; ++$depth) {
                [$current, $item] = [$item, $item->getParent()];
            }

            return $item instanceof ParentInterface && 'li' === ($this->descriptions[$item][0] ?? null) && $this->firstInFlow($item, $widget, $computed) === $current ? $this->elements[spl_object_id($item)] ?? null : null;
        }
        $element = $this->elements[$id] ?? null;
        if (null === $element || 'li' === $element->localName || 'li' !== $element->parentElement?->localName) {
            return null;
        }
        // After text of the item, like <li>Inbox <span class="badge">, it is not the first of it; nor a float, out of
        // the line the marker is on, like an image floated at the start of the item: the text after it
        if ($item instanceof ParentInterface ? $this->firstInFlow($item, $widget, $computed) !== $widget : $element->parentElement->firstElementChild !== $element) {
            return null;
        }

        return $element->parentElement;
    }

    /**
     * The first child of a box in its flow, its floats before it skipped, like the first line of a list item that its
     * marker goes on.
     *
     * @param array<string, string> $computed The declarations of the widget computed now, whose own are not known yet
     */
    private function firstInFlow(ParentInterface $item, AbstractWidget $widget, array $computed): ?AbstractWidget
    {
        foreach ($item->all() as $child) {
            $declarations = $child === $widget ? $computed : $this->declarations[$this->widgetDeclarations[$child] ?? -1] ?? [];
            if (!preg_match('/^\s*(?:left|right|inline-start|inline-end)\b/i', $declarations['float'] ?? '')) {
                return $child;
            }
        }

        return null;
    }

    /**
     * The text of a block container that carries its ::before or ::after inline, like CSS: its first or its last child,
     * when it is text of its own (an anonymous block), in a block laid out in the flow, not a row nor a grid, whose
     * pseudo-elements would be items; null when the block starts, or ends, with a block, the pseudo-element then a line.
     */
    private function textCarryingPseudo(ContainerWidget $container, string $pseudoElement): ?AbstractWidget
    {
        $children = $container->all();
        $text = 'before' === $pseudoElement ? $children[0] ?? null : $children[\count($children) - 1] ?? null;
        if (!$text instanceof RichTextWidget || !$this->isAnonymous($text) || 'li' === ($this->descriptions[$container][0] ?? null) || $container->isInlineFlow()) {
            return null;
        }
        if (!isset($this->widgetDeclarations[$container], $this->elementRules[$container])) {
            return null;
        }
        $style = $this->styleOf($this->widgetDeclarations[$container]);
        // A flex column too, like the arrow over the score of the upvoter of lobste.rs: an item, a line of its own
        if (null !== $style && (null !== $style->getGridTemplate() || null !== $style->getDirection() || null !== $style->getColumns())) {
            return null;
        }
        // Inline, like by default; a block one, like the display: table of a clearfix, is a line of its own
        $matching = array_values(array_filter($this->elementRules[$container], fn (int $index) => self::pseudoElementMatches($this->rules[$index]->selector->pseudoElement, $pseudoElement)));
        $display = strtolower(trim($this->cascadeDeclarations($matching, [])['display'] ?? 'inline'));
        if (!\in_array($display, ['inline', 'contents'], true)) {
            return null;
        }

        return $text;
    }

    /**
     * The ::before or ::after of the block a text carries, see textCarryingPseudo(): its content and the rules of the block.
     *
     * @return array{string, list<int>}|null
     */
    private function blockPseudo(AbstractWidget $widget, string $pseudoElement): ?array
    {
        $container = $widget->getParent();
        if (!$container instanceof ContainerWidget || $widget !== $this->textCarryingPseudo($container, $pseudoElement) || !isset($this->elementRules[$container], $this->widgetDeclarations[$container])) {
            return null;
        }
        $content = $this->generatedContent($this->elementRules[$container], $pseudoElement, $this->widgetDeclarations[$container], $this->descriptions[$container][1] ?? []);

        return null !== $content ? [$content, $this->elementRules[$container]] : null;
    }

    /**
     * @param array<string, string> $computed
     */
    private function itemBefore(AbstractWidget $widget, int $id, array $computed): ?string
    {
        $item = $widget->getParent();
        if (null === $item || null === $this->itemOfFirstText($widget, $id, false, $computed)) {
            return null;
        }
        if (!isset($this->elementRules[$item], $this->widgetDeclarations[$item])) {
            return null;
        }

        return $this->generatedContent($this->elementRules[$item], 'before', $this->widgetDeclarations[$item], $this->descriptions[$item][1] ?? []);
    }

    /**
     * Returns the list item marker of an <li>, from li::marker, list-style-image and list-style-type.
     *
     * @param array<string, string>                                          $computed
     * @param list<int>                                                      $elementRules
     * @param list<array{?array{int, int, int}, ?array{int, int, int}}>|null $image        Set to the cells of a list-style-image drawn as the marker, outside the text, the marker being their room
     */
    private function marker(AbstractWidget $widget, int $id, array $computed, array $elementRules, int $declarations, ?array &$image = null): ?string
    {
        $element = $this->elements[$id] ?? null;
        // The first text of an item holding blocks, like a nested list, carries the marker of the item
        $element = $this->itemOfFirstText($widget, $id, true, $computed) ?? $element;
        if (null === $element || 'li' !== $element->localName) {
            return null;
        }
        // Only a list item has a marker, like CSS: an item displayed otherwise, like a block or a flex row, has none
        // The declarations of the item: its own, or those of the ancestor the first text is in
        for ($owner = $widget->getParent(), $depth = 0; null !== $owner && $depth < 5 && ($this->elements[spl_object_id($owner)] ?? null) !== $element; ++$depth) {
            $owner = $owner->getParent();
        }
        $item = $element === ($this->elements[$id] ?? null) ? $computed : $this->declarations[null !== $owner ? $this->widgetDeclarations[$owner] ?? -1 : -1] ?? [];
        if (isset($item['display']) && !preg_match('/\blist-item\b/i', $item['display'])) {
            return null;
        }

        if (null !== $content = $this->generatedContent($elementRules, 'marker', $declarations, $this->descriptions[$widget][1] ?? [])) {
            return $content;
        }

        // An image, drawn when it can be read, whatever the type; inside the text, where no image goes, the type
        if (!self::markerInside($computed) && null !== $image = $this->markerImage($computed)) {
            return str_repeat(' ', \count($image) + 1);
        }

        // The shorthand and the longhand compete: the one that comes last in the cascade wins
        $type = $computed['list-style-type'] ?? null;
        if (isset($computed['list-style']) && (null === $type || array_search('list-style', array_keys($computed), true) > array_search('list-style-type', array_keys($computed), true))) {
            // In any order: the type is what is not the position nor an image; none alone is no marker
            $type = null;
            foreach (Value::split($computed['list-style']) as $part) {
                if (!\in_array(strtolower($part), ['inside', 'outside'], true) && !preg_match('/^(?:url|image-set|-webkit-image-set|(?:repeating-)?(?:linear|radial|conic)-gradient)\(/i', $part)) {
                    $type = 'none' === strtolower($part) && null !== $type ? $type : $part;
                }
            }
            $type ??= 'disc';
        }
        if (null === $type || 'none' === $type) {
            return null;
        }
        if (preg_match('/^(["\']).*\1$/s', $type)) {
            return $this->parseContent($type, []);
        }

        // The prefix, the ordinal in the counter style and the suffix; an unknown style is decimal
        return InlineImages::strip($this->counterStyles->marker($this->ordinal($element), $type));
    }

    /**
     * The ordinal of a list item, counted like HTML: start, reversed and value attributes. The items of a list are all
     * counted on the first one asked, once per cascade.
     */
    private function ordinal(\Dom\Element $element): int
    {
        if (null === $list = $element->parentElement) {
            return is_numeric($element->getAttribute('value')) ? self::clampCounter((int) $element->getAttribute('value')) : 1;
        }
        $id = spl_object_id($list);
        if (!isset($this->listOrdinals[$id]) || $this->listOrdinals[$id][0] !== $list) {
            $items = [];
            for ($child = $list->firstElementChild; null !== $child; $child = $child->nextElementSibling) {
                if ('li' === $child->localName) {
                    $items[] = $child;
                }
            }
            $reversed = null !== $list->getAttribute('reversed');
            $ordinal = is_numeric($list->getAttribute('start')) ? self::clampCounter((int) $list->getAttribute('start')) : ($reversed ? \count($items) : 1);
            // The items are kept with their ordinals, so that their ids stay theirs while the cascade runs
            $ordinals = new \SplObjectStorage();
            foreach ($items as $item) {
                if (is_numeric($item->getAttribute('value'))) {
                    $ordinal = self::clampCounter((int) $item->getAttribute('value'));
                }
                $ordinals[$item] = $ordinal;
                $ordinal = self::clampCounter($reversed ? $ordinal - 1 : $ordinal + 1);
            }
            // What is not an item counts after them all
            $this->listOrdinals[$id] = [$list, $ordinals, $ordinal];
        }

        return $this->listOrdinals[$id][1][$element] ?? $this->listOrdinals[$id][2];
    }

    /**
     * The image of a list-style-image, or of the url() of list-style: its cells, two pixels each, a row tall and from
     * 1 to 4 columns wide, as its proportions say; null without one, or when it cannot be read.
     *
     * @param array<string, string> $computed
     *
     * @return list<array{?array{int, int, int}, ?array{int, int, int}}>|null
     */
    private function markerImage(array $computed): ?array
    {
        if ($this->media?->forcedColors) {
            return null;
        }
        $value = $computed['list-style-image'] ?? null;
        // The shorthand and the longhand compete: the one that comes last in the cascade wins
        if (isset($computed['list-style']) && (null === $value || array_search('list-style', array_keys($computed), true) > array_search('list-style-image', array_keys($computed), true))) {
            $value = null;
            foreach (Value::split($computed['list-style']) as $part) {
                if (preg_match('/^url\(/i', $part)) {
                    $value = $part;
                }
            }
        }
        if (null === $value || !preg_match('/^url\(\s*(?:"([^"]*)"|\'([^\']*)\'|([^)\s]*))\s*\)$/is', trim($value), $m)) {
            return null;
        }
        $url = trim($m[1].($m[2] ?? '').($m[3] ?? ''));
        if ('' === $url || null === $image = $this->loadImage($url)) {
            return null;
        }
        $image = $image->withRendering('none' !== strtolower(trim($computed['image-orientation'] ?? '')), \in_array(strtolower(trim($computed['image-rendering'] ?? '')), ['pixelated', 'crisp-edges'], true));
        $cell = ($this->media ?? MediaContext::fromEnvironment())->getCellSize();
        [$width, $height] = $image->peekSize() ?? [1, 1];
        $columns = max(1, min(4, (int) round($width / max(1, $height) * $cell->height / $cell->width)));

        return $image->cells($columns, 1)[0] ?? null ?: null;
    }

    /**
     * Formats a counter value, like counter(name, style); unknown styles are decimal.
     */
    private function counterStyle(int $value, string $style): string
    {
        return 'none' === strtolower($style) ? '' : $this->counterStyles->format($value, $style);
    }

    /**
     * Returns counter(name, style) or counters(name, "separator", style).
     */
    private function counterText(string $function, string $name, ?string $separator, ?string $style): string
    {
        $instances = $this->counters[$name] ?? [];
        // A counter not in scope is 0
        $values = $instances ? array_map(static fn (array $instance) => $instance[0], $instances) : [0];
        $style ??= 'decimal';

        if ('counter' === $function) {
            return $this->counterStyle($values[array_key_last($values)], $style);
        }

        $glue = null !== $separator ? self::unescape(substr($separator, 1, -1)) : '';

        return implode($glue, array_map(fn (int $value) => $this->counterStyle($value, $style), $values));
    }

    /**
     * Applies counter-reset, counter-set and counter-increment, in that order, like CSS.
     *
     * @param array<string, string> $declarations
     */
    private function applyCounters(array $declarations): void
    {
        foreach (['counter-reset' => 0, 'counter-set' => 0, 'counter-increment' => 1] as $property => $default) {
            if (!isset($declarations[$property])) {
                continue;
            }
            foreach (self::counterList($declarations[$property], $default) as $name => $value) {
                $top = isset($this->counters[$name]) ? array_key_last($this->counters[$name]) : null;
                if ('counter-reset' === $property) {
                    // A counter reset by a previous sibling is replaced, not nested
                    if (null !== $top && $this->counters[$name][$top][1] === $this->counterDepth) {
                        $this->counters[$name][$top][0] = $value;
                    } else {
                        $this->counters[$name][] = [$value, $this->counterDepth];
                    }
                    continue;
                }
                if (null === $top) {
                    // Counting a counter that is not in scope creates it on the element
                    $this->counters[$name] = [[0, $this->counterDepth]];
                    $top = 0;
                }
                $this->counters[$name][$top][0] = self::clampCounter('counter-set' === $property ? $value : $this->counters[$name][$top][0] + $value);
            }
        }
    }

    /**
     * Parses "name [integer]" pairs.
     *
     * @return array<string, int>
     */
    private static function counterList(string $value, int $default): array
    {
        $parts = Value::split($value);
        if (['none'] === $parts) {
            return [];
        }

        $counters = [];
        foreach ($parts as $i => $part) {
            if (preg_match('/^[+-]?\d+$/', $part)) {
                continue;
            }
            if (!preg_match('/^-?[a-z_][\w-]*$/i', $part) || \in_array(strtolower($part), ['none', 'inherit', 'initial', 'unset'], true)) {
                // An invalid declaration is ignored as a whole
                return [];
            }
            $next = $parts[$i + 1] ?? null;
            $counters[$part] = null !== $next && preg_match('/^[+-]?\d+$/', $next) ? self::clampCounter((int) $next) : $default;
        }

        return $counters;
    }

    /**
     * Returns the scheme a color-scheme value forces, null when it lets prefers-color-scheme decide.
     */
    private static function colorScheme(string $value): ?ColorScheme
    {
        $schemes = array_values(array_intersect(preg_split('/\s+/', strtolower(trim($value))) ?: [], ['light', 'dark']));

        return 1 === \count(array_unique($schemes)) ? ColorScheme::from($schemes[0]) : null;
    }

    /**
     * Returns the text of open-quote or close-quote, and follows how deep quotes are nested, like CSS.
     */
    private function quote(string $keyword): string
    {
        if ('open-quote' === $keyword || 'no-open-quote' === $keyword) {
            $pair = $this->quotes[min($this->quoteDepth, \count($this->quotes) - 1)] ?? null;
            ++$this->quoteDepth;

            return 'open-quote' === $keyword ? $pair[0] ?? '' : '';
        }

        // A close-quote without an open one draws nothing
        if (0 === $this->quoteDepth) {
            return '';
        }
        --$this->quoteDepth;
        $pair = $this->quotes[min($this->quoteDepth, \count($this->quotes) - 1)] ?? null;

        return 'close-quote' === $keyword ? $pair[1] ?? '' : '';
    }

    /**
     * Parses a quotes value: auto, none, or pairs of strings.
     *
     * @return list<array{string, string}>|null null when invalid
     */
    /**
     * The size and the sink of initial-letter, null for normal or when invalid.
     *
     * @return array{float, int}|null
     */
    private static function initialLetter(string $value): ?array
    {
        $parts = Value::split(strtolower(trim($value)));
        if (!$parts || \count($parts) > 2 || !preg_match('/^\d*\.?\d+$/', $parts[0]) || ($size = (float) $parts[0]) < 1) {
            return null;
        }
        $sink = match ($parts[1] ?? 'drop') {
            'drop' => (int) floor($size),
            'raise' => 1,
            default => preg_match('/^[1-9]\d{0,2}$/', $parts[1]) ? (int) $parts[1] : null,
        };

        return null !== $sink ? [min(100.0, $size), $sink] : null;
    }

    /**
     * The keywords of hanging-punctuation, [] for none; null when invalid.
     *
     * @return list<'first'|'last'|'allow-end'|'force-end'>|null
     */
    private static function hangingPunctuation(string $value): ?array
    {
        $parts = Value::split(strtolower(trim($value)));
        if (['none'] === $parts) {
            return [];
        }

        return $parts && \count($parts) === \count(array_unique($parts)) && !array_diff($parts, ['first', 'last', 'allow-end', 'force-end']) && !(\in_array('allow-end', $parts, true) && \in_array('force-end', $parts, true)) ? $parts : null;
    }

    /**
     * What text-autospace puts a space between ideographs and, null when invalid.
     *
     * @return list<'alpha'|'numeric'>|null
     */
    private static function autospace(string $value): ?array
    {
        $parts = Value::split(strtolower(trim($value)));
        if (['no-autospace'] === $parts) {
            return [];
        }
        if (['normal'] === $parts || ['auto'] === $parts) {
            return ['alpha', 'numeric'];
        }
        // ideograph-alpha || ideograph-numeric || punctuation, then insert or replace
        if (!$parts || \count($parts) !== \count(array_unique($parts)) || array_diff($parts, ['ideograph-alpha', 'ideograph-numeric', 'punctuation', 'insert', 'replace']) || \in_array('insert', $parts, true) && \in_array('replace', $parts, true)) {
            return null;
        }
        $kinds = [];
        foreach (['ideograph-alpha' => 'alpha', 'ideograph-numeric' => 'numeric'] as $keyword => $kind) {
            if (\in_array($keyword, $parts, true)) {
                $kinds[] = $kind;
            }
        }

        return $kinds || \in_array('punctuation', $parts, true) ? $kinds : null;
    }

    /**
     * The string of hyphenate-character, a hyphen for auto, null when invalid.
     */
    private static function hyphenateCharacter(string $value): ?string
    {
        $value = trim($value);
        if ('auto' === strtolower($value)) {
            return '-';
        }

        return preg_match('/^(?:"((?:\\\\.|[^"\\\\])*)"|\'((?:\\\\.|[^\'\\\\])*)\')$/s', $value, $m, \PREG_UNMATCHED_AS_NULL) ? self::unescape($m[1] ?? $m[2] ?? '') : null;
    }

    private static function quotePairs(string $value, string $language = ''): ?array
    {
        $value = trim($value);
        if ('auto' === strtolower($value) || 'initial' === strtolower($value) || 'match-parent' === strtolower($value)) {
            return self::LANGUAGE_QUOTES[strtolower(explode('-', $language)[0])] ?? [['“', '”'], ['‘', '’']];
        }
        if ('none' === strtolower($value)) {
            return [];
        }
        if (!preg_match_all('/"((?:\\\\.|[^"\\\\])*)"|\'((?:\\\\.|[^\'\\\\])*)\'|(\S+)/', $value, $tokens, \PREG_SET_ORDER | \PREG_UNMATCHED_AS_NULL)) {
            return null;
        }
        $strings = [];
        foreach ($tokens as $token) {
            if (null !== ($token[3] ?? null)) {
                return null;
            }
            $strings[] = self::unescape($token[1] ?? $token[2] ?? '');
        }
        if (0 !== \count($strings) % 2) {
            return null;
        }

        $pairs = [];
        for ($i = 0; $i < \count($strings); $i += 2) {
            $pairs[] = [$strings[$i], $strings[$i + 1]];
        }

        return $pairs;
    }

    /**
     * Returns the blank rows between lines for a line-height, null when it is not one.
     */
    /**
     * A text-indent: its columns, its percentage, hanging and each-line; null when invalid.
     *
     * @return array{int, float, bool, bool}|null
     */
    private static function textIndent(string $value, MediaContext $media): ?array
    {
        $cells = null;
        $percent = 0.0;
        $keywords = [];
        foreach (Value::split(strtolower(trim($value))) as $part) {
            if ('hanging' === $part || 'each-line' === $part) {
                if (isset($keywords[$part])) {
                    return null;
                }
                $keywords[$part] = true;
            } elseif (null !== $cells) {
                return null;
            } elseif (preg_match('/^([+-]?(?:\d+\.?\d*|\.\d+))%$/', $part, $m)) {
                [$cells, $percent] = [0, (float) $m[1]];
            } elseif (null === $cells = Length::toCells($part, $media)) {
                return null;
            }
        }

        return null !== $cells ? [$cells, $percent, isset($keywords['hanging']), isset($keywords['each-line'])] : null;
    }

    private static function lineGap(string $value, MediaContext $media): ?int
    {
        $value = strtolower(trim($value));
        if ('normal' === $value) {
            return 0;
        }
        if (is_numeric($value)) {
            $rows = (float) $value;
        } elseif (preg_match('/^(\d+(?:\.\d+)?)%$/', $value, $m)) {
            $rows = (float) $m[1] / 100;
        } elseif (null !== $columns = Length::toColumns($value, $media)) {
            // A row is two columns high: 24px is 1.5 rows, not 2
            $rows = $columns / 2;
        } else {
            return null;
        }

        // A blank row from 2 rows: the 1.5 to 1.75 of the text of a page, like the prose of Tailwind or VitePress, are
        // its usual spacing, not double spacing
        return $rows < 0 ? null : max(0, (int) floor($rows + 1e-9) - 1);
    }

    /**
     * Returns the columns between characters for a letter-spacing, null when it is not one.
     */
    private static function letterSpacing(string $value, MediaContext $media): ?int
    {
        $value = strtolower(trim($value));
        if ('normal' === $value) {
            return 0;
        }
        // A column from three quarters of one: the .1em of a page is none, a column apart being far more
        $cells = Length::toWholeCells($value, $media, false);

        return null === $cells ? null : max(0, $cells);
    }

    /**
     * Counters are 32-bit integers: big values cannot overflow, nor make styles explode.
     */
    private static function clampCounter(int|float $value): int
    {
        return (int) max(-2147483648, min(2147483647, $value));
    }

    private function enterCounterScope(): void
    {
        ++$this->counterDepth;
    }

    /**
     * Leaves an element: the counters its children created go out of scope.
     */
    private function leaveCounterScope(): void
    {
        foreach ($this->counters as $name => $instances) {
            while ($instances && $instances[array_key_last($instances)][1] === $this->counterDepth) {
                array_pop($instances);
            }
            if ($instances) {
                $this->counters[$name] = $instances;
            } else {
                unset($this->counters[$name]);
            }
        }
        --$this->counterDepth;
    }

    /**
     * @param array<string, string> $declarations
     */
    private function substitute(string $value, array $declarations): string
    {
        try {
            // var() takes the name of a custom property, which no other is: looked up among them all, not filtered out of
            // them for each value, an element of Tailwind having hundreds
            $value = Value::substitute($value, $declarations);
            if ((!$this->functions || !preg_match('/--[\w-]+\(/', $value)) && !str_contains($value, 'if(')) {
                return $value;
            }
            $custom = array_filter($declarations, static fn (string $property) => str_starts_with($property, '--'), \ARRAY_FILTER_USE_KEY);

            return Conditional::resolve($this->callFunctions($value, $custom, $this->media ?? new MediaContext()), $this->conditionalTest($custom, $this->media ?? new MediaContext()));
        } catch (InvalidArgumentException) {
            return 'none';
        }
    }

    /**
     * Tells whether a test of an if() condition passes: media() on the media, supports() like @supports, and style()
     * on the custom properties of the element.
     *
     * @param array<string, string> $custom
     *
     * @return \Closure(string, string): bool
     */
    /**
     * Replaces the calls of custom functions, like --double(2ch), by their results: the arguments bound to the
     * parameters, typed, over the custom properties of the element, the local ones in order, the last result passing
     * its @media and @supports tests.
     *
     * @param array<string, string> $custom
     *
     * @throws InvalidArgumentException when a call cannot be resolved: invalid at computed-value time
     */
    private function callFunctions(string $value, array $custom, MediaContext $media, int $depth = 0): string
    {
        if (!$this->functions || !str_contains($value, '--') || !preg_match('/--[\w-]+\(/', $value)) {
            return $value;
        }
        if ($depth > 16) {
            throw new InvalidArgumentException('Custom functions call each other in a cycle.');
        }

        $offset = 0;
        while (null !== $start = Value::findOutsideStrings($value, '/(?<![\w-])(--[\w-]+)\(/', $offset)) {
            preg_match('/--[\w-]+/', $value, $name, 0, $start);
            $name = $name[0];
            $open = $start + \strlen($name);
            $end = Value::closingParenthesis($value, $open);
            if (null === $function = $this->functions[$name] ?? null) {
                $offset = $open;
                continue;
            }
            $inner = substr($value, $open + 1, $end - $open - 1);
            $arguments = '' === trim($inner) ? [] : array_map(fn (string $argument) => trim($this->callFunctions($argument, $custom, $media, $depth + 1)), Selector::splitTopLevel($inner));
            if (\count($arguments) > \count($function['params'])) {
                throw new InvalidArgumentException(\sprintf('Too many arguments for "%s()".', $name));
            }
            // The custom properties of the element, then the parameters, then the local ones
            $scope = $custom;
            foreach ($function['params'] as $index => [$param, $type, $default]) {
                $argument = $arguments[$index] ?? (null !== $default ? $this->callFunctions(Value::substitute($default, $scope), $scope, $media, $depth + 1) : null);
                // Without an argument nor a default, the parameter is not set: var() of it takes its fallback
                if (null === $argument) {
                    unset($scope[$param]);
                    continue;
                }
                if (null !== $type && !PropertySyntax::matches($type, $argument, $media)) {
                    throw new InvalidArgumentException(\sprintf('Invalid argument "%s" of "%s()".', $param, $name));
                }
                $scope[$param] = $argument;
            }
            $test = $this->conditionalTest($scope, $media);
            $result = null;
            foreach ($function['body'] as [$conditions, $property, $declared]) {
                foreach ($conditions as [$kind, $condition]) {
                    if (!$test($kind, $condition)) {
                        continue 2;
                    }
                }
                try {
                    $resolved = Conditional::resolve($this->callFunctions(Value::substitute($declared, $scope), $scope, $media, $depth + 1), $test);
                } catch (InvalidArgumentException) {
                    // An invalid descriptor is ignored, like an invalid declaration
                    continue;
                }
                if ('result' === $property) {
                    $result = $resolved;
                } else {
                    $scope[$property] = $resolved;
                }
            }
            if (null === $result || (null !== $function['returns'] && !PropertySyntax::matches($function['returns'], $result, $media))) {
                throw new InvalidArgumentException(\sprintf('"%s()" has no result of its type.', $name));
            }
            $value = substr($value, 0, $start).$result.substr($value, $end + 1);
            $offset = $start + \strlen($result);
        }

        return $value;
    }

    private function conditionalTest(array $custom, MediaContext $media): \Closure
    {
        return function (string $test, string $argument) use ($custom, $media): bool {
            try {
                return match ($test) {
                    'media' => MediaQuery::parse(str_starts_with($argument, '(') ? $argument : '('.$argument.')')->matches($media),
                    'supports' => $this->parser->supports(preg_match('/^(?:--[\w-]+|[a-z-]+)\s*:/i', $argument) ? '('.$argument.')' : $argument),
                    'style' => ContainerQuery::parse('style('.$argument.')')->matchesStyle($custom),
                    default => false,
                };
            } catch (InvalidArgumentException) {
                return false;
            }
        };
    }

    /**
     * Parses a content value: strings, attr() and quotes.
     *
     * @param array<string, string> $attributes
     */
    private function parseContent(string $content, array $attributes): ?string
    {
        $content = trim($content);
        if ('none' === $content || 'normal' === $content || '' === $content) {
            return null;
        }

        $text = '';
        preg_match_all('/"((?:\\\\.|[^"\\\\])*)"|\'((?:\\\\.|[^\'\\\\])*)\'|attr\(\s*([\w-]+)\s*\)|(open-quote|close-quote|no-open-quote|no-close-quote)|(counters?)\(\s*([\w-]+)\s*(?:,\s*("(?:[^"\\\\]|\\\\.)*"|\'(?:[^\'\\\\]|\\\\.)*\'))?\s*(?:,\s*([\w-]+)\s*)?\)|url\(\s*(?:"([^"]*)"|\'([^\']*)\'|([^)\s"\']*))\s*\)/i', $content, $tokens, \PREG_SET_ORDER | \PREG_UNMATCHED_AS_NULL);
        foreach ($tokens as $token) {
            if (null !== ($token[9] ?? $token[10] ?? $token[11] ?? null)) {
                $text .= $this->contentImage(trim($token[9] ?? $token[10] ?? $token[11] ?? ''));
            } elseif (null !== ($token[5] ?? null)) {
                $text .= InlineImages::strip($this->counterText($token[5], $token[6] ?? '', $token[7] ?? null, $token[8] ?? null));
            } elseif (null !== ($token[4] ?? null)) {
                $text .= InlineImages::strip($this->quote($token[4]));
            } elseif (null !== ($token[3] ?? null)) {
                $text .= InlineImages::strip($attributes[$token[3]] ?? '');
            } else {
                // The characters standing for the cells of images are not text, only those of url() draw them
                $text .= InlineImages::strip(self::unescape($token[1] ?? $token[2] ?? ''));
            }
        }

        return $text;
    }

    /**
     * The characters standing for the cells of an image of generated content, like content: url(icon.png), drawn in
     * the line a row tall, as wide as its proportions say, up to 16 columns; nothing when it cannot be drawn, like an
     * image that cannot be loaded in a browser, or outside the text of a text block, like in a marker.
     */
    private function contentImage(string $url): string
    {
        if (null === $this->contentImages || '' === $url || $this->media?->forcedColors || \count($this->contentImages) >= RichTextWidget::MAX_INLINE_IMAGES || null === $image = $this->loadImage($url)) {
            return '';
        }
        // Each address counted once, the image read once for it
        if (!isset($this->contentPixels[$url])) {
            [$width, $height] = $image->peekSize() ?? [0, 0];
            $pixels = $width * $height;
            if (array_sum($this->contentPixels) + $pixels > 4 * RasterImage::MAX_PIXELS) {
                return '';
            }
            $this->contentPixels[$url] = $pixels;
        }
        [$width, $height] = $image->peekSize() ?? [1, 1];
        $columns = max(1, min(16, (int) round($width / max(1, $height) * 2)));
        $this->contentImages[] = [$image, $columns, ''];

        return RichTextWidget::inlineImageText(\count($this->contentImages) - 1, $columns, true);
    }

    /**
     * Resolves CSS escapes: \2192 is →, \" is ".
     */
    private static function unescape(string $string): string
    {
        return preg_replace_callback('/\\\\([0-9a-fA-F]{1,6})\s?|\\\\(.)/s', static fn (array $m) => '' !== $m[1] ? (mb_chr(min(\intval($m[1], 16), 0x10FFFF)) ?: "\u{FFFD}") : $m[2], $string) ?? $string;
    }

    /**
     * @param array<string, string> $computed
     *
     * @return string|null The transforms joined by +, in the order they apply: a case, full-width, full-size-kana, the numeric variants (diagonal-fractions, ordinal, slashed-zero), then small-caps for small capitals
     */
    private static function textTransform(array $computed): ?string
    {
        $keywords = self::transformKeywords($computed['text-transform'] ?? 'none') ?? [];
        // font-variant-numeric: fractions, ordinals and slashed zeros, before small capitals take the letters after a number
        $numeric = isset($computed['font-variant-numeric']) ? self::numericVariant($computed['font-variant-numeric']) ?? [] : explode(' ', self::fontVariant($computed['font-variant'] ?? '')['numeric'] ?? '');
        $keywords = [...$keywords, ...array_values(array_intersect(self::NUMERIC_VARIANTS, $numeric))];
        // font-variant: small-caps draws the lowercase letters as small capitals, after the case is transformed
        $variant = isset($computed['font-variant-caps']) ? strtolower(trim($computed['font-variant-caps'])) : (self::fontVariant($computed['font-variant'] ?? '')['caps'] ?? '');
        if (\in_array($variant, ['small-caps', 'all-small-caps'], true)) {
            if ('all-small-caps' === $variant) {
                $keywords = ['lowercase', ...array_diff($keywords, ['uppercase', 'lowercase', 'capitalize'])];
            }
            $keywords[] = 'small-caps';
        }

        return $keywords ? implode('+', $keywords) : null;
    }

    /**
     * The keywords of a text-transform: a case, full-width and full-size-kana, in the order they apply; null when invalid.
     *
     * @return list<string>|null
     */
    private static function transformKeywords(string $value): ?array
    {
        $parts = Value::split(strtolower(trim($value)));
        if (['none'] === $parts) {
            return [];
        }
        $case = $width = $kana = null;
        foreach ($parts as $part) {
            if (\in_array($part, ['uppercase', 'lowercase', 'capitalize'], true) && null === $case) {
                $case = $part;
            } elseif ('full-width' === $part && null === $width) {
                $width = $part;
            } elseif ('full-size-kana' === $part && null === $kana) {
                $kana = $part;
            } else {
                return null;
            }
        }

        return $parts ? array_values(array_filter([$case, $width, $kana])) : null;
    }

    /**
     * @param array<string, string> $computed
     *
     * @return 'normal'|'nowrap'|'pre'|'pre-wrap'|'pre-line'
     */
    private static function whiteSpace(array $computed): string
    {
        // The longhands: white-space-collapse and text-wrap-mode
        if (!isset($computed['white-space']) && isset($computed['white-space-collapse'])) {
            $wrap = !\in_array('nowrap', Value::split(strtolower($computed['text-wrap-mode'] ?? $computed['text-wrap'] ?? '')), true);

            return match (strtolower(trim($computed['white-space-collapse']))) {
                'preserve', 'break-spaces', 'preserve-spaces' => $wrap ? 'pre-wrap' : 'pre',
                'preserve-breaks' => $wrap ? 'pre-line' : 'pre',
                default => $wrap ? 'normal' : 'nowrap',
            };
        }
        // text-wrap: nowrap, or its longhand, keeps a block on one line like white-space: nowrap
        if ((!isset($computed['white-space']) || 'normal' === $computed['white-space']) && \in_array('nowrap', Value::split(strtolower($computed['text-wrap-mode'] ?? $computed['text-wrap'] ?? '')), true)) {
            return 'nowrap';
        }

        return self::whiteSpaceValue($computed['white-space'] ?? 'normal') ?? 'normal';
    }

    /**
     * The white-space keyword of a value: one of them, break-spaces like pre-wrap, or a white-space-collapse and a
     * text-wrap-mode, in any order; null when it is not valid.
     *
     * @return 'normal'|'nowrap'|'pre'|'pre-wrap'|'pre-line'|null
     */
    private static function whiteSpaceValue(string $value): ?string
    {
        $keywords = preg_split('/\s+/', strtolower(trim($value))) ?: [];
        if (1 === \count($keywords) && \in_array($keywords[0], ['normal', 'nowrap', 'pre', 'pre-wrap', 'pre-line'], true)) {
            return $keywords[0];
        }
        if (['break-spaces'] === $keywords) {
            return 'pre-wrap';
        }
        $collapse = array_values(array_intersect($keywords, ['collapse', 'preserve', 'preserve-breaks', 'preserve-spaces', 'break-spaces']));
        $mode = array_values(array_intersect($keywords, ['wrap', 'nowrap']));
        if (!$keywords || \count($collapse) > 1 || \count($mode) > 1 || \count($collapse) + \count($mode) !== \count($keywords)) {
            return null;
        }
        $wrap = 'nowrap' !== ($mode[0] ?? 'wrap');

        return match ($collapse[0] ?? 'collapse') {
            'preserve', 'break-spaces', 'preserve-spaces' => $wrap ? 'pre-wrap' : 'pre',
            'preserve-breaks' => $wrap ? 'pre-line' : 'pre',
            default => $wrap ? 'normal' : 'nowrap',
        };
    }

    /**
     * The limits of hyphenate-limit-chars: the word, before and after; null for auto, which sets none.
     *
     * @return array{int, int, int}|null
     */
    public static function hyphenLimits(string $value): ?array
    {
        $parts = preg_split('/\s+/', strtolower(trim($value))) ?: [];
        if (!$parts || \count($parts) > 3 || array_filter($parts, static fn (string $part) => 'auto' !== $part && !preg_match('/^\d{1,3}$/', $part))) {
            return null;
        }
        if (['auto'] === $parts) {
            return null;
        }
        // The after limit is the before one when missing; auto, and a missing before limit, is no limit
        $limits = array_map(static fn (string $part) => 'auto' === $part ? 0 : (int) $part, $parts);

        return [$limits[0], $limits[1] ?? 0, $limits[2] ?? $limits[1] ?? 0];
    }

    /**
     * The image of a url() of the style sheets: a data: URL, or what the loader reads; null when it cannot be read.
     */
    private function loadImage(string $url): ?RasterImage
    {
        if (\array_key_exists($url, $this->images)) {
            return $this->images[$url];
        }
        // Each address read once, never again: past as many as a page may read, or as many bytes, none
        if (\count($this->images) >= self::MAX_IMAGES || $this->imageBytes >= self::MAX_IMAGES_BYTES) {
            return null;
        }
        $data = null;
        if (preg_match('#^data:([^,]*?)(;base64)?,(.*)$#is', $url, $m)) {
            if (\strlen($m[3]) <= self::MAX_IMAGE_BYTES * 4 / 3 + 4) {
                $data = '' !== $m[2] ? base64_decode(preg_replace('/\s+/', '', $m[3]) ?? '', true) : rawurldecode($m[3]);
            }
        } elseif (null !== $this->loader) {
            try {
                $data = $this->loader->load($url);
            } catch (\Throwable) {
            }
        }
        $image = \is_string($data) && '' !== $data && \strlen($data) <= self::MAX_IMAGE_BYTES ? new RasterImage($data) : null;
        // An SVG, like the cross of the close button of Bootstrap: drawn once at up to four pixels per CSS pixel
        if (null !== $image && null === $image->peekSize() && null !== $svg = self::svgToPng($data, 4)) {
            $image = new RasterImage($svg[0])->withResolution($svg[1]);
        }

        // Its header read, the image decoded when it is first drawn: those of rules that never apply cost nothing
        $image = null !== $image?->peekSize() ? $image : null;
        $this->imageBytes += \strlen($image->data ?? '');

        return $this->images[$url] = $image;
    }

    /**
     * Draws an SVG document into a PNG image of its size times a scale, or less for a large one, in black: the image
     * and its pixels per CSS pixel; null when it is not one, or draws nothing.
     *
     * @return array{string, float}|null
     */
    private static function svgToPng(string $data, int $scale): ?array
    {
        if (null === ($vector = VectorImage::fromString($data)) || null === ($png = $vector->toPng((int) ceil($vector->width * $scale), (int) ceil($vector->height * $scale), [0, 0, 0])) || false === $size = @getimagesizefromstring($png)) {
            return null;
        }

        return [$png, $size[0] / $vector->width];
    }

    /**
     * The declarations with the longhands of the font shorthand its text uses: font-variant (small-caps) and
     * line-height, after a slash; reset by it when it does not set them, unless they come after it.
     *
     * @param array<string, string> $computed
     *
     * @return array<string, string>
     */
    private static function fontLonghands(array $computed): array
    {
        if (!isset($computed['font'])) {
            return $computed;
        }
        $order = array_flip(array_keys($computed));
        $value = strtolower(trim($computed['font']));
        // The caps of the last of font, font-variant and font-variant-caps, like CSS
        $last = 'font';
        foreach (['font-variant', 'font-variant-caps'] as $property) {
            if (isset($computed[$property]) && $order[$property] > $order[$last]) {
                $last = $property;
            }
        }
        if ('font' === $last) {
            $computed['font-variant'] = $computed['font-variant-caps'] = preg_match('/(?<![\w-])small-caps(?![\w-])/', $value) ? 'small-caps' : 'normal';
        } elseif ('font-variant' === $last) {
            unset($computed['font-variant-caps']);
        }
        if (!isset($computed['line-height']) || $order['line-height'] < $order['font']) {
            $computed['line-height'] = preg_match('#/\s*([^\s,]+)#', $value, $m) ? $m[1] : 'normal';
        }

        return $computed;
    }

    /**
     * The keywords of a font-variant-numeric, at most one of each group; null when invalid.
     *
     * @return list<string>|null
     */
    private static function numericVariant(string $value): ?array
    {
        $tokens = preg_split('/\s+/', strtolower(trim($value))) ?: [];
        if (['normal'] === $tokens) {
            return [];
        }
        $groups = [];
        foreach ($tokens as $token) {
            $group = match ($token) {
                'lining-nums', 'oldstyle-nums' => 'figure',
                'proportional-nums', 'tabular-nums' => 'spacing',
                'diagonal-fractions', 'stacked-fractions' => 'fraction',
                'ordinal', 'slashed-zero' => $token,
                default => null,
            };
            if (null === $group || isset($groups[$group])) {
                return null;
            }
            $groups[$group] = $token;
        }

        return array_values($groups);
    }

    /**
     * The caps and the position the font-variant shorthand sets; null when it is not valid. Its other values are
     * accepted: the terminal chooses the glyphs of its font.
     *
     * @return array{caps?: string, position?: string, numeric?: string}|null
     */
    private static function fontVariant(string $value): ?array
    {
        $tokens = preg_split('/\s+(?![^(]*\))/', strtolower(trim($value))) ?: [];
        if (['normal'] === $tokens || ['none'] === $tokens) {
            return [];
        }
        $variant = [];
        foreach ($tokens as $token) {
            if (\in_array($token, ['small-caps', 'all-small-caps', 'petite-caps', 'all-petite-caps', 'unicase', 'titling-caps'], true)) {
                // Petite capitals are as small as small ones here
                $variant['caps'] = str_contains($token, 'petite') ? str_replace('petite', 'small', $token) : ('unicase' === $token || 'titling-caps' === $token ? 'normal' : $token);
            } elseif ('super' === $token || 'sub' === $token) {
                $variant['position'] = $token;
            } elseif (\in_array($token, self::NUMERIC_VARIANTS, true)) {
                $variant['numeric'] = ltrim(($variant['numeric'] ?? '').' '.$token);
            } elseif (!\in_array($token, ['common-ligatures', 'no-common-ligatures', 'discretionary-ligatures', 'no-discretionary-ligatures', 'historical-ligatures', 'no-historical-ligatures', 'contextual', 'no-contextual', 'lining-nums', 'oldstyle-nums', 'proportional-nums', 'tabular-nums', 'diagonal-fractions', 'stacked-fractions', 'ordinal', 'slashed-zero', 'historical-forms', 'jis78', 'jis83', 'jis90', 'jis04', 'simplified', 'traditional', 'full-width', 'proportional-width', 'ruby', 'emoji', 'text', 'unicode'], true)
                && !preg_match('/^(stylistic|styleset|character-variant|swash|ornaments|annotation)\([^()]*\)$/', $token)) {
                return null;
            }
        }

        return $variant;
    }

    /**
     * Whether clip or clip-path leaves nothing of the box to see: clip: rect() without width or height, or
     * clip-path: inset() of half the box or more.
     *
     * @param array<string, string> $declarations
     */
    public static function clippedToNothing(array $declarations): bool
    {
        if (isset($declarations['clip']) && preg_match('/^rect\(\s*(-?[\d.]+)(?:px)?[\s,]+(-?[\d.]+|auto)(?:px)?[\s,]+(-?[\d.]+|auto)(?:px)?[\s,]+(-?[\d.]+)(?:px)?\s*\)$/i', trim($declarations['clip']), $m)
            && 'auto' !== $m[2] && 'auto' !== $m[3] && ((float) $m[2] - (float) $m[4] <= 0 || (float) $m[3] - (float) $m[1] <= 0)) {
            return true;
        }
        if (isset($declarations['clip-path']) && preg_match('/^inset\(\s*([\d.]+)%(?:\s+([\d.]+)%)?\s*(?:round\b.*)?\)$/i', trim($declarations['clip-path']), $m)) {
            // One value for every side, or the top and bottom then the left and right: half of either axis is all
            return (float) $m[1] >= 50 || (float) ($m[2] ?? $m[1]) >= 50;
        }
        // clip-path: rect() of its top, right, bottom and left edges, without width or height, like the rect(0 0 0 0) of
        // the .sr-only of GitHub
        if (isset($declarations['clip-path']) && preg_match('/^rect\(\s*(-?[\d.]+)(px|%)?\s+(-?[\d.]+|auto)(px|%)?\s+(-?[\d.]+|auto)(px|%)?\s+(-?[\d.]+)(px|%)?\s*(?:round\b.*)?\)$/i', trim($declarations['clip-path']), $m)) {
            $m += array_fill(0, 9, '');
            $same = static fn (string $a, string $b): bool => strtolower($a) === strtolower($b) || ('' === $a && 'px' === strtolower($b)) || ('px' === strtolower($a) && '' === $b);

            return ('auto' !== $m[5] && $same($m[2], $m[6]) && (float) $m[5] <= (float) $m[1]) || ('auto' !== $m[3] && $same($m[4], $m[8]) && (float) $m[3] <= (float) $m[7]);
        }

        return false;
    }

    /**
     * The script text is written in: superscript or subscript characters, from vertical-align on an inline element,
     * else from font-variant-position, which is inherited; null for the line.
     *
     * @param array<string, string> $computed
     *
     * @return 'super'|'sub'|null
     */
    private static function script(array $computed, bool $inline = true): ?string
    {
        if ($inline) {
            $align = strtolower(trim($computed['vertical-align'] ?? ''));
            if ('super' === $align || 'sub' === $align) {
                return $align;
            }
            // Another position of the element: on the line, whatever it inherits
            if ('' !== $align && 'baseline' !== $align) {
                return null;
            }
        }
        $position = isset($computed['font-variant-position']) ? strtolower(trim($computed['font-variant-position'])) : (self::fontVariant($computed['font-variant'] ?? '')['position'] ?? '');

        return 'super' === $position || 'sub' === $position ? $position : null;
    }

    /**
     * Whether a value is one of color-scheme, or of the content of <meta name="color-scheme">.
     */
    public static function isColorScheme(string $value): bool
    {
        return 1 === preg_match('/^(?:normal|(?:only\s+)?(?:light|dark)(?:\s+(?:light|dark))?(?:\s+only)?)$/i', trim($value));
    }

    /**
     * The declarations some HTML attributes stand for, like HTML presentational hints: any CSS rule wins over them.
     *
     * @param array<string, string> $attributes
     */
    private static function presentationalHints(string $tagName, array $attributes): string
    {
        $hints = [];
        $length = static fn (string $value): ?string => match (true) {
            1 === preg_match('/^\s*(\d{1,4})\s*$/', $value, $m) => $m[1].'px',
            1 === preg_match('/^\s*(\d{1,3}(?:\.\d+)?)%\s*$/', $value, $m) => $m[1].'%',
            default => null,
        };
        $cells = ['td', 'th'];
        // Inline code, which the user agent draws in reverse video, see computeDeclarations()
        if (\in_array(strtolower($tagName), ['code', 'kbd', 'samp'], true)) {
            $hints[] = self::CODE.': 1';
        }
        // The document, which scrolls past its height, see computeDeclarations()
        if (\in_array(strtolower($tagName), ['html', 'body'], true)) {
            $hints[] = self::DOCUMENT.': 1';
        }
        if ('textarea' === $tagName && preg_match('/^\s*(\d{1,4})\s*$/', $attributes['cols'] ?? '', $m) && (int) $m[1] > 0) {
            $hints[] = 'width: '.$m[1].'ch';
        }
        // The characters a text field shows, with room for the cursor
        if ('input' === $tagName && \in_array(strtolower($attributes['type'] ?? 'text'), ['text', 'search', 'email', 'url', 'tel', 'password', 'number'], true) && preg_match('/^\s*(\d{1,4})\s*$/', $attributes['size'] ?? '', $m) && (int) $m[1] > 0) {
            $hints[] = 'width: '.((int) $m[1] + 1).'ch';
        }
        if (\in_array($tagName, ['table', ...$cells], true) && isset($attributes['width']) && null !== $width = $length($attributes['width'])) {
            $hints[] = 'width: '.$width;
        }
        // The size of a frame, like browsers: CSS pixels or a percentage
        if ('iframe' === $tagName) {
            foreach (['width', 'height'] as $side) {
                if (isset($attributes[$side]) && null !== $size = $length($attributes[$side])) {
                    $hints[] = $side.': '.$size;
                }
            }
        }
        if (\in_array($tagName, ['p', 'div', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'caption', 'tr', 'thead', 'tbody', 'tfoot', ...$cells], true) && \in_array($align = strtolower(trim($attributes['align'] ?? '')), ['left', 'right', 'center', 'justify'], true)) {
            $hints[] = 'text-align: '.$align;
        }
        // A table centered, like the button of an email, or floated, like an image beside the text, by its align
        $align = strtolower(trim($attributes['align'] ?? ''));
        if ('table' === $tagName && 'center' === $align) {
            $hints[] = 'margin-left: auto';
            $hints[] = 'margin-right: auto';
        } elseif (\in_array($tagName, ['table', 'img'], true) && \in_array($align, ['left', 'right'], true)) {
            $hints[] = 'float: '.$align;
        }
        if (\in_array($tagName, $cells, true) && isset($attributes['nowrap'])) {
            $hints[] = 'white-space: nowrap';
        }
        // <font color>, like browsers: a color only
        if ('font' === $tagName && preg_match('/^\s*(#[0-9a-f]{3,8}|[a-z]{3,20})\s*$/i', $attributes['color'] ?? '', $m)) {
            $hints[] = 'color: '.$m[1];
        }
        // <font size>: 1 to 7, or more or less than 3, a keyword of font-size; <font face>: the families
        if ('font' === $tagName && preg_match('/^\s*([+-]?)(\d+)/', $attributes['size'] ?? '', $m)) {
            $size = max(1, min(7, '' === $m[1] ? (int) $m[2] : 3 + ('+' === $m[1] ? 1 : -1) * (int) $m[2]));
            $hints[] = 'font-size: '.['x-small', 'small', 'medium', 'large', 'x-large', 'xx-large', 'xxx-large'][$size - 1];
        }
        if ('font' === $tagName && preg_match('/^[\w\s,"\'-]+$/u', $face = trim($attributes['face'] ?? ''))) {
            $hints[] = 'font-family: '.$face;
        }
        // A color only: nothing else can go through
        if (\in_array($tagName, ['body', 'table', 'tr', ...$cells], true) && preg_match('/^\s*(#[0-9a-f]{3,8}|[a-z]{3,20})\s*$/i', $attributes['bgcolor'] ?? '', $m)) {
            $hints[] = 'background-color: '.$m[1];
        }
        // <meta name="color-scheme">, given to <html> by the builder: the color schemes of the page
        if ('html' === $tagName && self::isColorScheme($scheme = $attributes[self::COLOR_SCHEME_ATTRIBUTE] ?? '')) {
            $hints[] = 'color-scheme: '.trim($scheme);
        }
        if ('table' === $tagName && preg_match('/^\s*(\d{1,3})?\s*$/', $attributes['border'] ?? 'x', $m)) {
            $hints[] = '' === ($m[1] ?? '') || (int) $m[1] > 0 ? 'border: 1px solid' : 'border: none';
        }
        // The cells of a table take its border, cellpadding and cellspacing, like browsers, through the user agent style
        // sheet; those of a table nested in a cell do not inherit them
        if ('table' === $tagName) {
            $border = preg_match('/^\s*(\d{1,3})?\s*$/', $attributes['border'] ?? 'x', $m) && ('' === ($m[1] ?? '') || (int) $m[1] > 0);
            $hints[] = '--tui-cell-border: '.($border ? '1px solid' : 'initial');
            $hints[] = '--tui-cell-padding: '.(preg_match('/^\s*(\d{1,3})\s*$/', $attributes['cellpadding'] ?? '', $m) ? $m[1].'px' : 'initial');
            $spacing = preg_match('/^\s*(\d{1,3})\s*$/', $attributes['cellspacing'] ?? '', $m) ? (int) $m[1] : null;
            $hints[] = '--tui-cell-spacing: '.(null !== $spacing ? $spacing.'px' : 'initial');
            // The borders of its cells as one grid, their lines not doubled by the 2px browsers put between them, unless
            // a cellspacing sets them apart
            if ($border && !$spacing) {
                $hints[] = 'border-collapse: collapse';
            }
        }
        // A rule of a width, placed by its align, like <hr width="50%" align="left">
        if ('hr' === $tagName && null !== $width = $length($attributes['width'] ?? '')) {
            $hints[] = 'width: '.$width;
            $hints[] = match (strtolower(trim($attributes['align'] ?? ''))) {
                'left' => 'margin-right: auto',
                'right' => 'margin-left: auto',
                default => 'margin-left: auto; margin-right: auto',
            };
        }

        return implode('; ', $hints);
    }

    /**
     * Whether the marker of a list item is in its text, like list-style-position: inside; the last of the longhand and
     * the shorthand wins.
     *
     * @param array<string, string> $computed
     */
    private static function markerInside(array $computed): bool
    {
        $position = $computed['list-style-position'] ?? null;
        if (isset($computed['list-style']) && (null === $position || array_search('list-style', array_keys($computed), true) > array_search('list-style-position', array_keys($computed), true))) {
            $parts = Value::split(strtolower($computed['list-style']));
            $position = \in_array('inside', $parts, true) ? 'inside' : 'outside';
        }

        return 'inside' === strtolower(trim($position ?? 'outside'));
    }

    /**
     * The alignment of the last line of each paragraph, like text-align-last: null for auto.
     *
     * @param array<string, string> $computed
     */
    private static function alignLast(array $computed): ?TextAlign
    {
        $rtl = 'rtl' === strtolower(trim($computed['direction'] ?? ''));

        return match (strtolower(trim($computed['text-align-last'] ?? 'auto'))) {
            'left' => TextAlign::Left,
            'right' => TextAlign::Right,
            'start' => $rtl ? TextAlign::Right : TextAlign::Left,
            'end' => $rtl ? TextAlign::Left : TextAlign::Right,
            'center' => TextAlign::Center,
            'justify' => TextAlign::Justify,
            default => null,
        };
    }

    /**
     * @param array<string, string> $computed
     */
    private function ellipsis(array $computed): ?string
    {
        $value = $computed['text-overflow'] ?? null;

        return match (true) {
            null === $value => null,
            'clip' === $value => '',
            'ellipsis' === $value => '…',
            default => $this->parseContent($value, []) ?? '',
        };
    }

    /**
     * Applies the cascade: inline beats rules, !important beats both, then
     * specificity and source order decide.
     *
     * @param list<int>         $ruleIndexes
     * @param list<Declaration> $inline
     * @param list<Declaration> $hints       Presentational hints, under every rule
     *
     * @return array<string, string>
     */
    private function cascadeDeclarations(array $ruleIndexes, array $inline, array $hints = []): array
    {
        // Like CSS: origin and importance, then the style attribute, then layers, specificity and order
        $entries = [];
        foreach ($ruleIndexes as $index) {
            $rule = $this->rules[$index];
            foreach ($rule->declarations as $position => $declaration) {
                $priority = match (true) {
                    $rule->userAgent => $declaration->important ? 5 : 0,
                    default => $declaration->important ? 3 : 1,
                };
                // Important declarations of earlier layers win: the layer order is reversed
                $layer = $declaration->important ? strtr($rule->layer, '0123456789', '9876543210') : $rule->layer;
                // The context of shadow trees: the outer one wins, the inner one for important declarations
                $context = $declaration->important ? $rule->context : -$rule->context;
                $entries[] = [$priority, $context, $layer, $rule->selector->specificity, $rule->scoped ? 1 : 0, $rule->order, $position, $declaration];
            }
        }
        foreach ($inline as $position => $declaration) {
            $entries[] = [$declaration->important ? 4 : 2, 0, '', 0, 0, 0, $position, $declaration];
        }
        // Presentational hints, like the align attribute: the author's, before any rule of any layer or tree
        foreach ($hints as $position => $declaration) {
            $entries[] = [1, \PHP_INT_MIN, '', -1, 0, -1, $position, $declaration];
        }

        // The context, then layers, specificity, scope proximity (in a @scope or not), then order: sorted by columns, not
        // by comparing arrays, an element taking hundreds of declarations, like those of the preflight of Tailwind
        if (\count($entries) > 1) {
            $columns = [[], [], [], [], [], [], []];
            foreach ($entries as $entry) {
                for ($key = 0; $key < 7; ++$key) {
                    $columns[$key][] = $entry[$key];
                }
            }
            $order = array_keys($entries);
            array_multisort($columns[0], $columns[1], $columns[2], $columns[3], $columns[4], $columns[5], $columns[6], $order);
            $entries = array_map(static fn (int $index) => $entries[$index], $order);
        }

        // What the user agent alone gives, which "revert" goes back to in an author rule
        $userAgent = [];
        foreach ($entries as [$priority, , , , , , , $declaration]) {
            if (0 === $priority && 'all' !== $declaration->property) {
                $userAgent[$declaration->property] = $declaration->value;
            }
        }

        $declarations = [];
        foreach ($entries as [$priority, , , , , , , $declaration]) {
            $property = $declaration->property;
            $keyword = strtolower(trim($declaration->value));
            // all: a CSS-wide keyword for every property but the custom ones, direction and unicode-bidi
            if ('all' === $property) {
                if (!\in_array($keyword, ['initial', 'inherit', 'unset', 'revert', 'revert-layer'], true)) {
                    continue;
                }
                // appearance too: its initial value, none, is not the look the widgets have without it
                $affected = array_keys($declarations + array_flip(DeclarationMapper::INHERITED) + array_flip(self::TEXT_INHERITED) + $userAgent + ['appearance' => true]);
                foreach ($affected as $name) {
                    if (!str_starts_with($name, '--') && !\in_array($name, ['direction', 'unicode-bidi'], true)) {
                        unset($declarations[$name]);
                        $declarations[$name] = self::cssWideKeyword($keyword, $name, 0 !== $priority && 5 !== $priority, $userAgent);
                    }
                }
                continue;
            }
            // font sets its size and its families too, a length or a keyword before the line height and what follows
            if ('font' === $property) {
                unset($declarations['font']);
                $declarations['font'] = $declaration->value;
                if (preg_match('#(?:^|\s)((?:[+-]?(?:\d+\.?\d*|\.\d+)(?:[a-z]+|%)|(?:xx?-|xxx-)?(?:small|large)|medium|larger|smaller))(?:\s*/\s*[^\s,]+)?\s+(\S.*)$#i', trim($declaration->value), $m, \PREG_OFFSET_CAPTURE)) {
                    // Its style and its weight before the size, normal unless set, which the children inherit: not a
                    // bold declared before it, like a font: 12px sans-serif after a font-weight: bold
                    $style = $weight = 'normal';
                    foreach (preg_split('/\s+/', strtolower(substr(trim($declaration->value), 0, $m[1][1]))) ?: [] as $token) {
                        if (\in_array($token, ['italic', 'oblique'], true)) {
                            $style = $token;
                        } elseif (\in_array($token, ['bold', 'bolder', 'lighter'], true) || preg_match('/^\d+$/', $token)) {
                            $weight = $token;
                        }
                    }
                    $variant = preg_match('/(?<![\w-])small-caps(?![\w-])/i', $declaration->value) ? 'small-caps' : 'normal';
                    $lineHeight = preg_match('#^\s*/\s*([^\s,]+)#', substr(trim($declaration->value), $m[1][1] + \strlen($m[1][0])), $l) ? $l[1] : 'normal';
                    foreach (['font-size' => $m[1][0], 'font-family' => $m[2][0], 'font-style' => $style, 'font-weight' => $weight, 'font-variant' => $variant, 'font-variant-caps' => $variant, 'line-height' => $lineHeight] as $name => $value) {
                        unset($declarations[$name]);
                        $declarations[$name] = $value;
                    }
                }
                continue;
            }
            // text-emphasis sets its longhands, which the children inherit, and which its own longhands replace
            if ('text-emphasis' === $property) {
                foreach (self::emphasisLonghands($declaration->value) as $name => $value) {
                    unset($declarations[$name]);
                    $declarations[$name] = \in_array(strtolower(trim($value)), ['unset', 'revert', 'revert-layer'], true) ? self::cssWideKeyword(strtolower(trim($value)), $name, 0 !== $priority && 5 !== $priority, $userAgent) : $value;
                }
                continue;
            }
            // Later wins: keep the insertion order of the winner
            unset($declarations[$property]);
            $declarations[$property] = \in_array($keyword, ['unset', 'revert', 'revert-layer'], true) && !str_starts_with($property, '--')
                ? self::cssWideKeyword($keyword, $property, 0 !== $priority && 5 !== $priority, $userAgent)
                : $declaration->value;
        }

        return $declarations;
    }

    /**
     * Resolves unset and revert to inherit, initial or the value of the user agent, like CSS; revert-layer is like
     * revert.
     *
     * @param bool                  $author    Whether the declaration is the author's, not the user agent's
     * @param array<string, string> $userAgent The values the user agent gives
     */
    private static function cssWideKeyword(string $keyword, string $property, bool $author, array $userAgent): string
    {
        if (\in_array($keyword, ['revert', 'revert-layer'], true) && $author && isset($userAgent[$property])) {
            return $userAgent[$property];
        }
        // The look of the controls, which browsers give them in their stylesheet, like all: revert on a button
        if (\in_array($keyword, ['revert', 'revert-layer'], true) && 'appearance' === $property) {
            return 'auto';
        }
        if (\in_array($keyword, ['initial', 'inherit'], true)) {
            return $keyword;
        }

        return \in_array($property, DeclarationMapper::INHERITED, true) || \in_array($property, self::TEXT_INHERITED, true) ? 'inherit' : 'initial';
    }

    /**
     * Resolves "inherit", inherited properties and custom properties.
     *
     * @param array<string, string> $parent
     *
     * @return array<string, string>
     */
    /**
     * The declarations of a parent its children inherit: the inherited properties, and the custom properties but for
     * those registered as not inherited.
     *
     * @param array<string, string> $parent
     *
     * @return array<string, string>
     */
    private function inheritableOf(array $parent): array
    {
        static $names = null;
        $names ??= array_flip([...DeclarationMapper::INHERITED, ...self::TEXT_INHERITED]);
        $values = [];
        foreach ($parent as $property => $value) {
            if (isset($names[$property]) || (str_starts_with($property, '--') && ($this->registered[$property][0] ?? true))) {
                $values[$property] = $value;
            }
        }

        return $values;
    }

    private function inherit(array $declarations, array $parent, ?int $parentId = null): array
    {
        // Inherited values come first, so that the element's own declarations come last, like the cascade; those of a
        // parent found once for its children, like the thousands of custom properties of the root of a page of GitHub
        if (null !== $parentId) {
            $values = $this->inheritableById[$parentId] ??= $this->inheritableOf($parent);
        } else {
            if (null === $this->inheritable || $parent !== $this->inheritable[0]) {
                $this->inheritable = [$parent, $this->inheritableOf($parent)];
            }
            $values = $this->inheritable[1];
        }
        $declarations = array_diff_key($values, $declarations) + $declarations;
        // text-align: match-parent, the alignment of the parent, its start and end on the sides of its direction
        if (isset($declarations['text-align']) && 'match-parent' === strtolower(trim($declarations['text-align']))) {
            $rtl = 'rtl' === strtolower(trim($parent['direction'] ?? 'ltr'));
            $declarations['text-align'] = match ($value = strtolower(trim($parent['text-align'] ?? 'start'))) {
                'start', 'match-parent' => $rtl ? 'right' : 'left',
                'end' => $rtl ? 'left' : 'right',
                default => $value,
            };
        }
        // Registered custom properties have their initial value where nothing sets them; a value not of their syntax is
        // invalid at computed-value time: inherited, or the initial one
        foreach ($this->registered as $property => [$inherits, $initial, $syntax]) {
            if (null !== $initial && (!isset($declarations[$property]) || 'initial' === $declarations[$property])) {
                $declarations[$property] = $initial;
            } elseif ('*' !== $syntax && isset($declarations[$property]) && $initial !== $declarations[$property]
                && !$this->ofSyntax($syntax, $declarations[$property], $declarations)) {
                $fallback = $inherits ? $parent[$property] ?? $initial : $initial;
                if (null === $fallback) {
                    unset($declarations[$property]);
                } else {
                    $declarations[$property] = $fallback;
                }
            }
        }

        // What is behind the element, for translucent colors: the closest background around it
        if (null !== $backdrop = $parent['background-color'] ?? $parent['background'] ?? null) {
            $declarations = [self::BACKDROP => $backdrop] + $declarations;
            // Blending with that background happens inside any group around it
            unset($declarations[self::ISOLATED]);
        } elseif (self::isGroup($parent)) {
            // A group of its own without a background: what it contains blends with nothing behind it
            $declarations[self::ISOLATED] = '1';
        }

        // Flex items are sized by their content unless they grow, like the initial "flex: 0 1 auto"; the children of an
        // element without a box (display: contents) in a flex row are flex items too
        $flexRow = self::isFlexRow($parent) || ('contents' === strtolower(trim($parent['display'] ?? '')) && isset($parent[self::IN_FLEX_ROW]));
        if (!isset($declarations['flex']) && !isset($declarations['flex-grow']) && $flexRow) {
            $declarations = ['flex-grow' => '0'] + $declarations;
        }
        if ($flexRow && 'contents' === strtolower(trim($declarations['display'] ?? ''))) {
            $declarations[self::IN_FLEX_ROW] = '1';
        }
        unset($declarations[self::IN_VERTICAL_GRID], $declarations[self::IN_ITEMS]);
        // An element without a box in a flex or grid container, through others: its children are the items
        $parentDisplay = strtolower(trim($parent['display'] ?? ''));
        if ('contents' === strtolower(trim($declarations['display'] ?? '')) && self::holdsItems($parent)) {
            $declarations[self::IN_ITEMS] = '1';
        }
        // An item of a grid written vertically, placed in it with its rows and columns the other way around; the
        // children of an element without a box (display: contents) in it too
        if ((\in_array($parentDisplay, ['grid', 'inline-grid'], true) && self::isVertical($parent)) || ('contents' === $parentDisplay && isset($parent[self::IN_VERTICAL_GRID]))) {
            $declarations[self::IN_VERTICAL_GRID] = '1';
        }

        foreach ($declarations as $property => $value) {
            // unset of a custom property, which is inherited: inherit
            if ('inherit' === $value || ('unset' === $value && str_starts_with($property, '--'))) {
                // Nothing to inherit: the initial value
                $declarations[$property] = self::parentValue($parent, $property) ?? 'initial';
            }
        }

        // bolder and lighter: a weight relative to that of the parent, which its children inherit
        if (isset($declarations['font-weight']) && \in_array($relative = strtolower(trim($declarations['font-weight'])), ['bolder', 'lighter'], true)) {
            $declarations['font-weight'] = (string) self::relativeFontWeight($relative, self::fontWeight($parent['font-weight'] ?? 'normal'));
        }
        // A light weight is dim: an element of a heavier one inside it is not, whatever it inherits
        unset($declarations[self::UNDIM]);
        if (self::fontWeight($parent['font-weight'] ?? 'normal') <= 300 && self::fontWeight($declarations['font-weight'] ?? 'normal') > 300) {
            $declarations[self::UNDIM] = '1';
        }

        return $declarations;
    }

    /**
     * The glyph of a ::before or ::after of no content drawn by its borders, and its style: see borderTriangle().
     *
     * @param list<int>             $rules
     * @param array<string, string> $computed
     *
     * @return array{string, Style}|null
     */
    private function pseudoTriangle(array $rules, string $pseudoElement, array $computed): ?array
    {
        $matching = array_values(array_filter($rules, fn (int $index) => self::pseudoElementMatches($this->rules[$index]->selector->pseudoElement, $pseudoElement)));
        if (!$matching || null === $triangle = $this->emptyBoxGlyph($own = $this->cascadeDeclarations($matching, []), $computed)) {
            return null;
        }
        // Absolute or fixed of an element drawn over the frame, not in the line too (see keepsOutOfFlow()); that of an
        // inline element in its line
        if (null !== $this->pseudoOwner && preg_match('/^(?:absolute|fixed)\b/i', trim($own['position'] ?? ''))) {
            return null;
        }
        // Its margins and colors, not its box, which the glyph stands for
        $declarations = self::withoutGlyphBox(array_diff_key($own, self::GENERATING));
        if (null !== $triangle[1]) {
            $declarations['color'] = $triangle[1];
        }

        $style = $this->computeStyle($this->inherit($declarations, $computed), $this->media ?? new MediaContext());
        // An inline block takes no line of the text it is in, like the caret of a dropdown of Bootstrap in its link:
        // not underlined with it
        if (preg_match('/^inline-(?:block|flex|grid)\b/i', trim($this->substitute($own['display'] ?? '', $computed)))) {
            $style = $style->withUnderline(false)->withStrikethrough(false)->withOverline(false);
        }
        // A margin of less than a column, like the .255em of Bootstrap, or one not drawn on a background: a column still,
        // a glyph not touching the text
        $glyph = $triangle[0];
        foreach (['left' => 'margin-left', 'right' => 'margin-right'] as $side => $property) {
            if (0 === (null === $style->getBackground() ? $style->getMargin()->{$side} ?? 0 : 0) && preg_match('/^\s*(?:\d*\.)?\d*[1-9]/', $own[$property] ?? '') && !preg_match('/^\s*-/', $own[$property] ?? '')) {
                $glyph = 'left' === $side ? ' '.$glyph : $glyph.' ';
            }
        }

        return [$glyph, $style];
    }

    /**
     * The top padding of the element whose ::before or ::after is being styled, in CSS pixels: the last of its
     * properties that set it, 0 when none does.
     */
    private function topPadding(): float
    {
        $owner = $this->declarations[$this->widgetDeclarations[$this->pseudoOwner] ?? -1] ?? [];
        $padding = 0.0;
        foreach ($owner as $property => $value) {
            $value = $this->substitute($value, $owner);
            $length = match ($property) {
                'padding-top', 'padding-block-start' => $value,
                'padding', 'padding-block' => Value::split($value)[0] ?? '',
                default => null,
            };
            if (null !== $length) {
                $padding = self::pixels($length) ?? $padding;
            }
        }

        return $padding;
    }

    /**
     * The declarations of a box drawn as a glyph without what the glyph stands for: its borders, background, padding,
     * size and font size; its margins, insets and colors kept.
     *
     * @param array<string, string> $declarations
     *
     * @return array<string, string>
     */
    private static function withoutGlyphBox(array $declarations): array
    {
        return array_filter($declarations, static fn (string $property) => !str_starts_with($property, 'border') && !str_starts_with($property, 'background') && !str_starts_with($property, 'padding') && !\in_array($property, ['width', 'height', 'font-size'], true), \ARRAY_FILTER_USE_KEY);
    }

    /**
     * A length in CSS pixels: px, and em and rem of a font of 16px, and 0; null for another one.
     */
    private static function pixels(string $length): ?float
    {
        if (!preg_match('/^([+-]?(?:\d+\.?\d*|\.\d+))(px|r?em|)$/', strtolower(trim(preg_replace('/\s*!important\s*$/i', '', $length) ?? $length)), $m)) {
            return null;
        }

        return match ($m[2]) {
            'px' => (float) $m[1],
            'em', 'rem' => (float) $m[1] * CellSize::REFERENCE_HEIGHT,
            // 0 without a unit too
            default => 0.0 === (float) $m[1] ? 0.0 : null,
        };
    }

    /**
     * Whether an empty box draws something of its own: a background color and a width and a height of a cell at least.
     *
     * @param array<string, string> $declarations
     */
    private function paintsBox(array $declarations): bool
    {
        $media = $this->media ?? new MediaContext();
        foreach (['width' => false, 'height' => true] as $property => $vertical) {
            $value = $this->substitute($declarations[$property] ?? '', $declarations);
            if (null === ($cells = $vertical ? Length::toRows($value, $media) : Length::toColumns($value, $media)) || $cells < 1) {
                return false;
            }
        }
        // Of a color mostly opaque, shown: a tint over the text, like an overlay of 5%, drawn as cells would erase it
        $color = $this->backgroundColor($declarations, $declarations);

        return null !== $color && Value::alpha($color, $media) >= .5 && !self::hasImage($declarations) && Value::color($color, $media) instanceof Color
            && !\in_array(strtolower(trim($declarations['visibility'] ?? '')), ['hidden', 'collapse'], true) && !preg_match('/^\s*0*(?:\.0*)?%?\s*$/', $declarations['opacity'] ?? '1');
    }

    /**
     * The background color of a box: the last of background-color and of a background of one color; null for none.
     *
     * @param array<string, string> $declarations In the order of the cascade
     * @param array<string, string> $computed     What custom properties are substituted from
     */
    private function backgroundColor(array $declarations, array $computed): ?string
    {
        $color = null;
        foreach ($declarations as $property => $value) {
            if ('background-color' === $property || 'background' === $property) {
                $value = strtolower(trim(preg_replace('/\s*!important\s*$/i', '', $this->substitute($value, $computed)) ?? ''));
                $layers = 'background' === $property ? Value::split($value) : [$value];
                $color = 1 === \count($layers) ? $layers[0] : null;
            }
        }

        return $color;
    }

    /**
     * Whether a box has an image over its background or masking it, like an icon.
     *
     * @param array<string, string> $declarations
     */
    private static function hasImage(array $declarations): bool
    {
        foreach ($declarations as $property => $value) {
            if (preg_match('/^(?:-webkit-)?mask(?:-image)?$/', $property) && 'none' !== strtolower(trim($value))) {
                return true;
            }
            if (('background-image' === $property || 'background' === $property) && preg_match('/\b(?:url|(?:repeating-)?(?:linear|radial|conic)-gradient|image-set)\(/i', $value)) {
                return true;
            }
        }

        return false;
    }

    /**
     * The glyph a box of no content stands for: a triangle drawn by its borders, or a dot drawn by its background.
     *
     * @param array<string, string> $declarations In the order of the cascade
     * @param array<string, string> $computed     What custom properties are substituted from
     *
     * @return array{string, ?string}|null The glyph, and its color, null for the color of the text
     */
    private function emptyBoxGlyph(array $declarations, array $computed): ?array
    {
        return $this->borderTriangle($declarations, $computed) ?? $this->smallDot($declarations, $computed);
    }

    /**
     * A box of no content smaller than a cell, its background a color, like the 5px bullets of the lists of VitePress:
     * a dot of that color where it is round, a bar where it is thin, a small square else, where its size would round
     * to nothing.
     *
     * @param array<string, string> $declarations In the order of the cascade
     * @param array<string, string> $computed     What custom properties are substituted from
     *
     * @return array{string, string}|null
     */
    private function smallDot(array $declarations, array $computed): ?array
    {
        $value = fn (string $property): string => strtolower(trim(preg_replace('/\s*!important\s*$/i', '', $this->substitute($declarations[$property] ?? '', $computed)) ?? ''));
        // Its size, in pixels, from px, em and rem, of a font of 16px
        $width = self::pixels($value('width'));
        $height = self::pixels($value('height'));
        if (null === $width || null === $height || $width <= 0 || $height <= 0 || $width > 12 || $height > 12) {
            return null;
        }
        // Round, a radius of half its size at least, a dot; a thin line, a bar, like the separators of the links of a
        // footer of GitHub; else a small square
        $radius = Value::split($value('border-radius'))[0] ?? '';
        $round = preg_match('/^(\d*\.?\d+)%$/', $radius, $m) ? (float) $m[1] >= 50 : (null !== ($length = self::pixels($radius)) && 2 * $length >= min($width, $height));
        // An icon, its image masking its color, like the arrow of a dropdown of Wikipedia, or a square speck of 2px,
        // like a star twinkling: no shape a glyph stands for
        if (self::hasImage($declarations) || (!$round && max($width, $height) < 3)) {
            return null;
        }
        $glyph = match (true) {
            $round => '•',
            3 * $width <= $height => '│',
            3 * $height <= $width => '─',
            default => '▪',
        };
        $color = $this->backgroundColor($declarations, $computed);
        if (null === $color || !Value::color($color, $this->media ?? new MediaContext()) instanceof Color) {
            return null;
        }

        return [$glyph, $color];
    }

    /**
     * The glyph a box of no content draws with its borders, like the caret of a dropdown of Bootstrap: one side of a
     * color, a side beside it of a transparent one, the triangle pointing away from the colored side, like ▾ under a
     * colored top; with that color, null for the color of the text.
     *
     * @param array<string, string> $declarations In the order of the cascade
     * @param array<string, string> $computed     What custom properties are substituted from
     *
     * @return array{string, ?string}|null
     */
    private function borderTriangle(array $declarations, array $computed): ?array
    {
        $names = ['top' => [0], 'right' => [1], 'bottom' => [2], 'left' => [3]];
        // Each side: its style, whether it has a width, and its color; none, medium and the color of the text unless set
        $sides = array_fill(0, 4, ['none', true, 'currentcolor']);
        $found = false;
        $assign = static function (array &$side, string $part, string $value): void {
            match ($part) {
                'style' => $side[0] = $value,
                'width' => $side[1] = !self::isZeroWidth($value),
                default => $side[2] = $value,
            };
        };
        foreach ($declarations as $property => $value) {
            if (!preg_match('/^border(?:-(top|right|bottom|left))?(?:-(width|style|color))?$/', $property, $m)) {
                continue;
            }
            $found = true;
            $value = strtolower(trim(preg_replace('/\s*!important\s*$/i', '', $this->substitute($value, $computed)) ?? $value));
            $which = '' !== ($m[1] ?? '') ? $names[$m[1]] : [0, 1, 2, 3];
            $part = $m[2] ?? '';
            if ('' === $part) {
                // A shorthand: its style, width and color, the others reset
                [$style, $width, $color] = ['none', 'medium', 'currentcolor'];
                $rest = [];
                foreach (Value::split($value) as $token) {
                    if (\in_array($token, ['none', 'hidden', 'solid', 'dashed', 'dotted', 'double', 'groove', 'ridge', 'inset', 'outset'], true)) {
                        $style = $token;
                    } elseif (\in_array($token, ['thin', 'medium', 'thick'], true) || preg_match('/^[+-]?(?:\d+\.?\d*|\.\d+)[a-z%]*$/', $token)) {
                        $width = $token;
                    } else {
                        $rest[] = $token;
                    }
                }
                $color = $rest ? implode(' ', $rest) : $color;
                foreach ($which as $side) {
                    $sides[$side] = [$style, !self::isZeroWidth($width), $color];
                }
                continue;
            }
            // One side, or the four of border-width, border-style or border-color: top, right, bottom, left
            $values = 1 === \count($which) ? [$value] : Value::split($value);
            $values = match (\count($values)) {
                1 => array_fill(0, 4, $values[0]),
                2 => [$values[0], $values[1], $values[0], $values[1]],
                3 => [$values[0], $values[1], $values[2], $values[1]],
                default => \array_slice($values, 0, 4),
            };
            foreach ($which as $i => $side) {
                $assign($sides[$side], $part, 1 === \count($which) ? $values[0] : $values[$i]);
            }
        }
        // Rounded, like a spinner of one colored side: a box
        if (!$found || preg_match('/[1-9]/', $declarations['border-radius'] ?? '')) {
            return null;
        }
        $drawn = static fn (array $side): bool => !\in_array($side[0], ['none', 'hidden'], true) && $side[1];
        // Transparent, or of an alpha of 0: the fourth of its values, or the one after a slash, like rgb(0 0 0 / 0)
        $clear = static fn (array $side): bool => 'transparent' === $side[2] || (bool) preg_match('/^(?:rgba?|hsla?)\((?:(?:[^,()]*,){3}|[^\/()]*\/)\s*0*(?:\.0*)?%?\s*\)$/', $side[2]);
        $colored = array_keys(array_filter($sides, static fn (array $side) => $drawn($side) && !$clear($side)));
        if (1 !== \count($colored)) {
            return null;
        }
        $side = $colored[0];
        $beside = [$sides[($side + 1) % 4], $sides[($side + 3) % 4]];
        if (!array_any($beside, static fn (array $other) => $drawn($other) && $clear($other))) {
            return null;
        }

        return [['▾', '◂', '▴', '▸'][$side], 'currentcolor' === $sides[$side][2] ? null : $sides[$side][2]];
    }

    /**
     * The content of a ::before or an ::after with visibility: hidden, which keeps its room and draws nothing: spaces
     * as wide; none when it is a block, which the line does not hold, like the bold copy of the label of a tab of
     * GitHub, a block of no height that keeps the width the label takes once bold.
     *
     * @param list<int> $rules
     */
    private function unseenPseudo(?string $content, ?Style $style, array $rules, string $pseudoElement): ?string
    {
        if (null === $content || true !== $style?->getInvisible()) {
            return $content;
        }
        $matching = array_values(array_filter($rules, fn (int $index) => self::pseudoElementMatches($this->rules[$index]->selector->pseudoElement, $pseudoElement)));
        // A block, in one keyword or two, like display: block flow
        if (preg_match('/^\s*(?:block|flex|grid|table|flow-root|list-item)\b/i', $this->cascadeDeclarations($matching, [])['display'] ?? '')) {
            return null;
        }

        return str_repeat(' ', AnsiUtils::visibleWidth($content));
    }

    /**
     * The content of a ::before or an ::after with its horizontal padding, spaces in its style, like CSS; its margins
     * too when it has no background to leave them out of, like the margin around the slash of a breadcrumb of Foundation.
     */
    private static function paddedPseudo(?string $content, ?Style $style): ?string
    {
        if (null === $content || null === $style) {
            return $content;
        }
        $padding = $style->getPadding();
        $margin = null === $style->getBackground() ? $style->getMargin() : null;
        $left = max(0, $padding->left ?? 0) + max(0, $margin->left ?? 0);
        $right = max(0, $padding->right ?? 0) + max(0, $margin->right ?? 0);

        return str_repeat(' ', min(20, $left)).$content.str_repeat(' ', min(20, $right));
    }

    /**
     * Whether the declarations of a button give it a look of its own: a background color or image, or a border, even
     * none, which browsers draw instead of the native look of the button. Only those of rules of every state, like
     * browsers but for a look that comes and goes with the focus, which would move what follows the button.
     *
     * @param array<string, string> $declarations
     */
    private function authorLook(\Dom\Element $element, array $declarations): bool
    {
        foreach (['background' => true, 'background-color' => true, 'background-image' => true, 'border' => false, 'border-width' => false, 'border-style' => false, 'border-top' => false, 'border-bottom' => false, 'border-block' => false, 'border-inline' => false] as $property => $painted) {
            if (isset($declarations[$property]) && !preg_match($painted ? '/^\s*(?:none|transparent|initial|unset|inherit|revert(?:-layer)?)\s*$/i' : '/^\s*(?:initial|unset|inherit|revert(?:-layer)?)\s*$/i', $declarations[$property]) && $this->mayDeclare($element, $property, '')) {
                return true;
            }
        }

        return false;
    }

    /**
     * The value of a property of the parent, a side or a corner taken from the shorthand that sets it last, like the
     * border-top-left-radius: inherit of the first item of a list group of Bootstrap, under its border-radius.
     *
     * @param array<string, string> $parent
     */
    private static function parentValue(array $parent, string $property): ?string
    {
        [$shorthand, $index] = self::SIDE_SHORTHANDS[$property] ?? [null, 0];
        if (null === $shorthand || !isset($parent[$shorthand])) {
            return $parent[$property] ?? null;
        }
        // The one declared last wins, like in the cascade
        $keys = array_flip(array_keys($parent));
        if (isset($parent[$property]) && $keys[$property] > $keys[$shorthand]) {
            return $parent[$property];
        }
        $value = $parent[$shorthand];
        if (preg_match('/^\s*(?:inherit|initial|unset|revert|revert-layer)\s*$/i', $value)) {
            return $value;
        }
        // One to four values, for the top, right, bottom and left, or the corners from the top left; for a radius, the
        // horizontal ones, then the vertical ones after a slash
        $halves = [];
        foreach ('border-radius' === $shorthand ? explode('/', $value, 2) : [$value] as $half) {
            $parts = Value::split(trim($half));
            $halves[] = match (\count($parts)) {
                0 => '',
                1 => $parts[0],
                2 => $parts[$index % 2],
                3 => $parts[[0, 1, 2, 1][$index]],
                default => $parts[$index],
            };
        }

        return implode(' / ', $halves);
    }

    /**
     * The numeric weight of a font-weight value, 400 for an unknown one.
     */
    private static function fontWeight(string $value): float
    {
        $value = strtolower(trim($value));

        return match (true) {
            'bold' === $value => 700,
            is_numeric($value) && $value >= 1 && $value <= 1000 => (float) $value,
            default => 400,
        };
    }

    /**
     * The weight bolder or lighter than the one of the parent, from the table of CSS Fonts.
     */
    private static function relativeFontWeight(string $relative, float $parent): float
    {
        if ('bolder' === $relative) {
            return match (true) {
                $parent < 350 => 400,
                $parent < 550 => 700,
                default => 900,
            };
        }

        return match (true) {
            $parent < 100 => $parent,
            $parent < 550 => 100,
            $parent < 750 => 400,
            default => 700,
        };
    }

    /**
     * Whether an element is a group of its own, which what it contains blends inside of, like isolation: isolate,
     * opacity below 1, a filter, a transform or a blend mode.
     *
     * @param array<string, string> $declarations
     *
     * @internal
     */
    public static function isGroup(array $declarations): bool
    {
        $opacity = rtrim(trim($declarations['opacity'] ?? '1'), '%');

        return 'isolate' === strtolower(trim($declarations['isolation'] ?? ''))
            || (is_numeric($opacity) && (float) $opacity < (str_ends_with(trim($declarations['opacity'] ?? ''), '%') ? 100 : 1))
            || !\in_array(strtolower(trim($declarations['filter'] ?? 'none')), ['none', ''], true)
            || !\in_array(strtolower(trim($declarations['transform'] ?? 'none')), ['none', ''], true)
            || !\in_array(strtolower(trim($declarations['mix-blend-mode'] ?? 'normal')), ['normal', ''], true);
    }

    /**
     * Whether the declarations write vertically, like writing-mode: vertical-rl.
     *
     * @param array<string, string> $declarations
     */
    private static function isVertical(array $declarations): bool
    {
        return !\in_array(DeclarationMapper::writingMode($declarations['writing-mode'] ?? ''), [null, 'horizontal-tb'], true);
    }

    /**
     * @param array<string, string> $declarations
     */
    private static function isFlexRow(array $declarations): bool
    {
        // A block written vertically lays its blocks out in a row, each as wide as its columns, and so does a flex
        // column, its row running down
        if (self::isVertical($declarations)) {
            $display = strtolower(trim($declarations['display'] ?? 'block'));
            if (\in_array($display, ['block', 'flow-root', 'list-item'], true)) {
                return true;
            }
            if (\in_array($display, ['flex', 'inline-flex'], true)) {
                return (bool) preg_match('/\bcolumn\b/i', $declarations['flex-direction'] ?? $declarations['flex-flow'] ?? '');
            }
        }
        if (!preg_match('/^(?:inline-)?flex\b/i', trim($declarations['display'] ?? ''))) {
            return false;
        }
        $direction = $declarations['flex-direction'] ?? $declarations['flex-flow'] ?? '';

        return !preg_match('/\bcolumn\b/i', $direction);
    }

    /**
     * @param array<string, string> $declarations
     */
    private function computeStyle(array $declarations, MediaContext $media): Style
    {
        // color-scheme: an element supporting a single scheme resolves light-dark() with it
        if (null !== $scheme = self::colorScheme($declarations['color-scheme'] ?? '')) {
            $media = $media->withColorScheme($scheme);
        }
        $custom = [];
        foreach ($declarations as $property => $value) {
            if (str_starts_with($property, '--')) {
                $custom[$property] = $value;
                unset($declarations[$property]);
            }
        }

        $test = $this->conditionalTest($custom, $media);
        // The system colors AccentColor and AccentColorText follow accent-color, like a browser
        $accent = null;
        if (isset($declarations['accent-color']) && 'auto' !== strtolower(trim($declarations['accent-color']))) {
            try {
                $accent = Value::substitute($declarations['accent-color'], $custom);
            } catch (InvalidArgumentException) {
            }
        }
        foreach ($declarations as $property => $value) {
            if (null !== $accent && 'accent-color' !== $property && false !== stripos($value, 'accentcolor')) {
                $value = preg_replace_callback('/(?<![\w-])accentcolor(text)?(?![\w-])/i', static fn (array $m) => isset($m[1]) ? 'contrast-color('.$accent.')' : $accent, $value) ?? $value;
            }
            try {
                $declarations[$property] = Conditional::resolve($this->callFunctions(Value::substitute($value, $custom), $custom, $media), $test);
                // Nothing left, like color: var(--none,), is invalid at computed-value time too; a custom property may be empty
                if ('' === trim($declarations[$property]) && !str_starts_with($property, '--') && '' !== trim($value)) {
                    unset($declarations[$property]);
                }
            } catch (InvalidArgumentException) {
                // An invalid var(), or an if() without a branch passing, makes the declaration invalid at computed-value time
                unset($declarations[$property]);
            }
        }

        $backdrop = null;
        try {
            $backdropValue = Value::substitute($custom[self::BACKDROP] ?? '', $custom);
        } catch (InvalidArgumentException) {
            // A background of an invalid var(): none behind
            $backdropValue = '';
        }
        foreach (Value::split($backdropValue) as $part) {
            if (($color = Value::color($part, $media)) instanceof Color) {
                $backdrop = $color;
                break;
            }
        }

        $style = $this->mapper->map($declarations, $media, $backdrop, isset($custom[self::IN_VERTICAL_GRID]))->withCssCascade();
        if (isset($custom[self::ISOLATED]) && null !== $style->getBlend()) {
            $style = $style->withBlend(null);
        }

        // Not dim like the light weight around it, unless translucent itself
        return isset($custom[self::UNDIM]) && !isset($declarations['opacity']) ? $style->withDim(false) : $style;
    }

    /**
     * @return list<Declaration>
     */
    /**
     * @param bool $validate Whether to drop the invalid declarations, like a browser in a style attribute, not in the
     *                       presentation attributes of an <svg>
     */
    private function parseInline(string $style, bool $validate = false): array
    {
        return $this->inlineCache[($validate ? "\1" : '').$style] ??= $this->parser->parseDeclarations($style, $validate);
    }

    public function setScrollStateResolver(\Closure $scrollStateOf): void
    {
        $this->scrollStateOf = $scrollStateOf;
    }

    public function setIntrinsicSizeResolver(\Closure $rowsOf): void
    {
        $this->rowsOf = $rowsOf;
    }

    public function afterLayout(AbstractWidget $root, \Closure $rectOf): bool
    {
        if ($root !== $this->root || null === $this->media) {
            return false;
        }

        $resized = false;
        foreach ($this->containerWidgets as $widget) {
            // scroll-state(): stuck, snapped, scrollable, scrolled
            if (null !== $this->scrollStateOf && \in_array('scroll-state', self::containerTypesOf($this->declarations[$this->widgetDeclarations[$widget] ?? -1] ?? []), true)
                && ($this->scrollStates[$widget] ?? null) !== $state = ($this->scrollStateOf)($widget)) {
                $this->scrollStates[$widget] = $state;
                $resized = true;
            }
            if (null === $rect = $rectOf($widget)) {
                continue;
            }
            // Queries measure the content box: the room inside the margin, border and padding
            $style = $this->styles[$widget] ?? new Style();
            $margin = $style->getMargin();
            $border = $style->getBorder();
            $padding = $style->getPadding();
            $size = [
                max(0, $rect->columns - ($margin->left ?? 0) - ($margin->right ?? 0) - ($border->left ?? 0) - ($border->right ?? 0) - ($padding->left ?? 0) - ($padding->right ?? 0)),
                max(0, $rect->rows - ($margin->top ?? 0) - ($margin->bottom ?? 0) - ($border->top ?? 0) - ($border->bottom ?? 0) - ($padding->top ?? 0) - ($padding->bottom ?? 0)),
            ];
            if (($this->containerSizes[$widget] ?? null) !== $size) {
                $this->containerSizes[$widget] = $size;
                $resized = true;
            }
        }
        // Scroll-driven animations whose timelines moved since they were computed, like on the first frame
        foreach (null !== $this->scrollStateOf ? $this->scrollAnimated : [] as $animated => $_) {
            $computed = $this->declarations[$this->widgetDeclarations[$animated] ?? -1] ?? [];
            $where = [];
            foreach (Animation::listFromDeclarations($computed) as $animation) {
                if ($animation->isScrollDriven()) {
                    $where[] = $this->timelineFraction($animated, $animation);
                }
            }
            if (($this->timelineFractions[$animated] ?? null) !== $key = serialize($where)) {
                $this->timelineFractions[$animated] = $key;
                $resized = true;
                $this->timelinesMoved = true;
            }
        }
        // :target-current, the children whose ::scroll-marker is the current one
        $current = false;
        foreach (null !== $this->scrollStateOf ? $this->markerScrollers : [] as $scroller) {
            foreach ($scroller->getScrollMarkers()[1] as [$child]) {
                $wanted = true === (($this->scrollStateOf)($child)['targetCurrent'] ?? false);
                if ($wanted !== $child->hasState('target-current')) {
                    $child->setState('target-current', $wanted);
                    $current = true;
                }
            }
        }
        if ($current) {
            $this->sync($root, $this->media);
        } elseif (!$resized || (!$this->timelinesMoved && !$this->hasContainerRules())) {
            return false;
        }
        $this->timelinesMoved = false;

        $this->restyled = false;
        $this->cascade($root, $this->media);
        $this->rootRevision = $root->getRenderRevision();

        return $this->restyled;
    }

    private function hasContainerRules(): bool
    {
        if ($this->usesContainerUnits) {
            return true;
        }
        foreach ($this->rules as $rule) {
            foreach ($rule->containers as $query) {
                // Style queries do not depend on the layout
                if (null !== $query->condition || [] !== $query->scroll) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Whether the value of a registered custom property, var() substituted, is of its syntax.
     *
     * @param array<string, string> $declarations The declarations of the element, its custom properties among them
     */
    private function ofSyntax(string $syntax, string $value, array $declarations): bool
    {
        // A value without var() is of a syntax or not whatever the element: found once
        if (!str_contains($value, 'var(')) {
            return $this->syntaxMatches[$syntax][$value] ??= PropertySyntax::matches($syntax, $value, $this->media ?? new MediaContext());
        }
        try {
            $value = Value::substitute($value, $declarations);
        } catch (InvalidArgumentException) {
            return false;
        }

        return PropertySyntax::matches($syntax, $value, $this->media ?? new MediaContext());
    }

    /**
     * Keeps the rules whose style queries pass: on the computed values of the nearest ancestor, or of the nearest one
     * with the container name, like @container card style(--theme: dark).
     *
     * @param list<int> $rules
     *
     * @return list<int>
     */
    private function passStyleQueries(array $rules, int $parentDeclarations): array
    {
        if (!$this->hasStyleQueries) {
            return $rules;
        }

        return array_values(array_filter($rules, function (int $index) use ($parentDeclarations): bool {
            foreach ($this->rules[$index]->containers as $query) {
                if ([] === $query->styles) {
                    continue;
                }
                $container = $this->declarations[$parentDeclarations] ?? null;
                if (null !== $query->name) {
                    $container = null;
                    for ($i = \count($this->ancestry) - 1; $i >= 0; --$i) {
                        if (\in_array($query->name, self::containerNames($this->declarations[$this->ancestry[$i]]), true)) {
                            $container = $this->declarations[$this->ancestry[$i]];
                            break;
                        }
                    }
                }
                if (null === $container || !$query->matchesStyle($container)) {
                    return false;
                }
            }

            return true;
        }));
    }

    /**
     * @param list<ContainerQuery> $queries
     */
    private function matchesContainers(\Dom\Element $element, array $queries): bool
    {
        foreach ($queries as $query) {
            // A scroll-state query: on the state of the nearest scroll-state container in the last frame
            if ([] !== $query->scroll) {
                $container = $this->containerOf($element, $query->name, 'scroll-state');
                if (null === $container || !$query->matchesScrollState($this->scrollStates[$container] ?? [])) {
                    return false;
                }
                continue;
            }
            // A style query is for the cascade, which knows the values of the ancestors
            if (null === $query->condition) {
                continue;
            }
            // An inline-size container cannot answer a query on the block axis: one further up that can is queried
            $container = $this->containerOf($element, $query->name, $query->condition->usesBlockAxis() ? 'size' : 'inline-size');
            $size = null !== $container ? ($this->containerSizes[$container] ?? null) : null;
            // No container, or one not laid out yet: the condition is unknown, and fails
            if (null === $size || !$query->condition->matches(($this->media ?? new MediaContext())->withSize($size[0], $size[1]))) {
                return false;
            }
        }

        return true;
    }

    /**
     * Returns the nearest ancestor that is a container of a type, with that name if one is given: inline-size for a
     * size container of the inline axis or both, size for one of both axes, scroll-state for a scroll-state one.
     */
    /**
     * The width and the height of the nearest containers, in pixels, for container units: an inline-size or size one
     * for the width, a size one for the height; the screen without one, like CSS.
     *
     * @return array{float, float}
     */
    private function containerUnits(?\Dom\Element $element): array
    {
        $media = $this->media ?? new MediaContext();
        $cell = $media->getCellSize();
        [$columns, $rows] = [$media->columns, $media->rows];
        if (null !== $element) {
            if (null !== ($inline = $this->containerOf($element, null, 'inline-size')) && null !== $size = $this->containerSizes[$inline] ?? null) {
                $columns = $size[0];
            }
            if (null !== ($block = $this->containerOf($element, null, 'size')) && null !== $size = $this->containerSizes[$block] ?? null) {
                $rows = $size[1];
            }
        }

        return [$columns * $cell->width, $rows * $cell->height];
    }

    private function containerOf(\Dom\Element $element, ?string $name, string $type): ?AbstractWidget
    {
        for ($ancestor = $element->parentElement; null !== $ancestor; $ancestor = $ancestor->parentElement) {
            if (null === $widget = $this->widgetFor($ancestor)) {
                continue;
            }
            $declarations = $this->declarations[$this->widgetDeclarations[$widget] ?? -1] ?? [];
            $types = self::containerTypesOf($declarations);
            if (!\in_array($type, $types, true) && ('inline-size' !== $type || !\in_array('size', $types, true))) {
                continue;
            }
            if (null === $name || \in_array($name, self::containerNames($declarations), true)) {
                return $widget;
            }
        }

        return null;
    }

    /**
     * The container types of an element, from container-type or the container shorthand.
     *
     * @param array<string, string> $declarations
     *
     * @return list<'size'|'inline-size'|'scroll-state'>
     */
    private static function containerTypesOf(array $declarations): array
    {
        $type = $declarations['container-type'] ?? (isset($declarations['container']) && str_contains($declarations['container'], '/') ? explode('/', $declarations['container'], 2)[1] : null);

        return null !== $type ? self::containerTypes($type) ?? [] : [];
    }

    /**
     * The types of a container-type value, [] for normal; null when invalid.
     *
     * @return list<'size'|'inline-size'|'scroll-state'>|null
     */
    private static function containerTypes(string $value): ?array
    {
        $parts = Value::split(strtolower(trim($value)));
        if (['normal'] === $parts) {
            return [];
        }
        // [size | inline-size] || scroll-state
        $sizes = array_intersect($parts, ['size', 'inline-size']);
        if (!$parts || \count($parts) !== \count(array_unique($parts)) || \count($sizes) > 1 || array_diff($parts, ['size', 'inline-size', 'scroll-state'])) {
            return null;
        }

        return array_values($parts);
    }

    /**
     * @param array<string, string> $declarations
     *
     * @return list<string>
     */
    private static function containerNames(array $declarations): array
    {
        $names = $declarations['container-name'] ?? (isset($declarations['container']) ? explode('/', $declarations['container'], 2)[0] : '');

        return array_values(array_filter(preg_split('/\s+/', trim($names)) ?: [], static fn (string $name) => '' !== $name && 'none' !== $name));
    }

    private function widgetFor(\Dom\Element $element): ?AbstractWidget
    {
        $id = (int) $element->getAttribute(self::OID_ATTRIBUTE);

        return ($this->widgetsById[$id] ?? null)?->get();
    }

    private static function defaultTagName(AbstractWidget $widget): string
    {
        foreach (self::DEFAULT_TAGS as $class => $tagName) {
            if ($widget instanceof $class) {
                return $tagName;
            }
        }

        // Anonymous classes are named after the class they extend
        $class = $widget::class;
        while (str_contains($class, '@anonymous') && false !== $parent = get_parent_class($class)) {
            $class = $parent;
        }
        if (str_contains($class, '@anonymous') || AbstractWidget::class === $class) {
            return 'tui-widget';
        }

        $short = substr($class, (int) strrpos($class, '\\') + 1);
        $short = preg_replace('/Widget$/', '', $short) ?: $short;

        return 'tui-'.strtolower(preg_replace('/(?<!^)[A-Z]/', '-$0', $short));
    }
}
