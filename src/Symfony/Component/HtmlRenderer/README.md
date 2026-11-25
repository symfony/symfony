HtmlRenderer Component
======================

HtmlRenderer computes a visual representation of HTML and CSS in PHP without a terminal, a window server or a graphical toolkit.
The component is experimental and the migration from TuiHtml is in progress.

The rendering pipeline is:

```text
HTML + CSS -> native DOM -> computed styles -> boxes -> layout fragments -> display list
```

The document owns semantic structure. Boxes describe visual structure; hidden elements produce no boxes, `display: contents` contributes its children, and text can produce several line fragments.
Display lists contain ordered PHP arrays of drawing operations, with no DOM nodes, terminal escape sequences, widgets or native handles.
Their in-memory representation is experimental; there is no public wire protocol.

Usage
-----

```php
use Symfony\Component\HtmlRenderer\HtmlRenderer;
use Symfony\Component\HtmlRenderer\RenderingContext;

$document = \Dom\HTMLDocument::createFromString('<!DOCTYPE html><p style="width: 120.5px">Hello</p>');
$renderer = new HtmlRenderer();
$result = $renderer->render($document, new RenderingContext(800, 600));

$operations = $result->getDisplayList()->getOperations();
$paragraph = $document->querySelector('p');
$bounds = $result->getBounds($paragraph);
$style = $result->getComputedStyle($paragraph);
```

Geometry uses floating-point CSS pixels, including physical CSS length units (`pt`, `pc`, `in`, `cm`, `mm`, `q`) in layout, font sizes and math expressions. Absolute-unit conversion is shared with the terminal adapter; fractional geometry is retained before device constraints. A backend converts these coordinates to physical pixels or terminal cells.
`TextMeasurerInterface` supplies text advance, ascent and descent for `FontStyle`; measurement and painting must use compatible fonts.
An optional context color-alias map lets a host resolve additional named colors without adding platform vocabularies to the engine. Standard CSS color names retain their meaning and can remain declarative tokens for a backend.
The default `MonospaceTextMeasurer` supplies deterministic test metrics, not a production graphical font or shaping engine.
Soft hyphens create discretionary breaks and remain invisible on unbroken lines. An optional `HyphenatorInterface` in the rendering context supplies dictionary break positions for `hyphens: auto`; no dictionary is bundled. Language comes from native `lang` attributes. Dictionary positions are bounded, cached and filtered to grapheme boundaries; editing and hit testing retain original source offsets.

A host can retain a session:

```php
$session = $renderer->createSession($document, new RenderingContext(800, 600));
$session->focus($document->querySelector('input'));
$session->input('Ada');
$result = $session->render();

$session->setContext(new RenderingContext(1024, 768));
$document->querySelector('p')->textContent = 'Changed';
$result = $session->render();
```

`input()` replaces the focused control value. `getValue()` reads it. Applications can assign live values with `setValue()`, checked state with `setChecked()`, and option selection with `select()`; these do not modify the native default attributes. Value and checked setters dispatch no input events unless requested. `focusNext()` advances document focus. Datalist suggestions preserve typed case; Tab or Right accepts a completion at the end of the value, subject to the editing limit. Hosts can override a control's suggestions with `setSuggestions()` without changing the DOM; null restores its datalist. Suggestion storage is bounded by 2,000 entries and the configured text-byte limit. `tab($backward, false)` reports an unhandled document focus boundary so an embedding runtime can transfer focus, while cancellation and completion remain document behavior.
`pointer()` accepts document coordinates; `scroll()` requests a document element's scroll offset. Its immediate `scroll` event describes the clamped request. After explicit rendering, `getScroll()` and the result's scroll geometry expose the actual snapped offset.
`on()` listens for semantic events at an element or the session; events bubble through ancestor listeners and can stop propagation or cancel default submission.
`submit()` returns a `FormSubmission`, or null when validation or an event cancels submission. A `method="dialog"` form closes its dialog without producing a transport submission.
The application owns physical input, scheduling and its event loop. Fragment navigation uses `navigate()` and `getTarget()`, reveals the target through enclosing scroll ports and retains native node identity. External links emit a semantic `link` event; the engine opens no URL. Visited styles use the session's explicit visited-link set. Text-fragment directives are not implemented.

Size, style and supported scroll-state container queries use explicit ancestor content-box metrics. Layout and cascade iterate for at most eight passes; repeated or non-converging states fall back to the frame without container queries. Snap state uses actual chosen targets on each physical axis. Vertical writing, fragmented query-container sizing, sticky state and complete browser query semantics remain unsupported. Trial animation state is isolated until a frame is chosen.

Scroll snapping supports physical x/y axes, start/center/end alignment, resolved scroll padding and margins, mandatory/proximity selection and `scroll-snap-stop: always`. Relative scrolling starts from the last actual snapped offset. Generated scroll buttons page their source port; before/after marker groups collect supported direct child targets and expose `:target-current`. Pointer activation retains document identity and uses the same scroll geometry. Browser marker focus groups, deeper descendant marker collection and complete scroll-snap semantics are not implemented.

`setUserAgentStyle()` can supply normal UA declarations for a native element or generated role without modifying its attributes. Author CSS retains priority; declaration order is preserved. This supports host presentation policies without building synthetic selectors.

`onInvalidation()` lets an embedding host subscribe to session changes and returns an unsubscribe closure. Notifications coalesce until the next computed frame; the host schedules rendering through its own runtime. After directly mutating the native DOM, a host with an external render cache must call `invalidate()` to notify that cache.

Native DOM mutations are detected conservatively by a serialization fingerprint. An unchanged session reuses the previous result; changes currently recompute styles and layout.
Results retain native element wrappers because native DOM documents do not keep those PHP wrappers alive themselves.

Declarative `template shadowrootmode` content uses a retained native rendering mirror. The caller's document and light nodes remain unchanged; cached native fragments give shadow content stable source-node identities for styles, bounds, hit testing and animation. Styles support the existing host, slot and exported-part selectors. Focus, hover, inert state and event propagation follow composed ancestors; IDs, labels, radio groups and form ownership remain scoped to native roots. Replacing a template or detaching its host revokes the previous nodes. Unchanged frames reuse the mirror and result; fragment mutations invalidate rendering, and replaced mirrors release their SVG resource views.

This provides declarative composition, not the browser `ShadowRoot` API or custom-element scripting. Closed roots render, but browser closed-root access and event-retargeting semantics are not implemented.

Embedded iframe documents retain their own native DOM, cascade and sessions. `getEmbeddedSession()` exposes a child session explicitly, while `getFocusedSession()` identifies the session receiving input. Child display lists are clipped and translated into the parent frame; focus, keyboard and pointer input, submissions and explicit time progression remain scoped to their owning document. Source changes replace the child session; viewport changes retain it. Depth, document count and aggregate parsed HTML bytes are bounded, and resource reads share a cache. Cached child inspection does not recompute parent geometry: call the parent's `render()` after directly changing its layout DOM.

External resources are not loaded by default. Data URLs are supported; an injected `ResourceLoader` callback can resolve application-authorized resources. Resource reads and retained image data are bounded. HTML strings and mutated documents have a configurable 4 MiB limit. Custom-function evaluation preserves the existing recursion limit and now rejects expanded values above 64 KiB before allocation, matching the custom-property value budget.

Atomic content integration
--------------------------

A context's optional `replacedContentResolver` receives an original native element and its computed styles. It may supply a `ReplacedContentInterface` for an embedded application's content. The provider reports intrinsic dimensions and records local drawing operations for an allocated content width and available height. Image-like providers can additionally declare a finite positive intrinsic aspect ratio; ordinary controls omit it. With both CSS axes automatic at the natural preferred width, the reported intrinsic height is retained; an explicitly sized axis uses the source ratio. The engine transfers definite heights through that ratio, resolves object fit and position against the final content box, and clips oversized drawing content. It must remain stable throughout a frame: layout may query it at several sizes. Native preparation and lifecycle work belong to the host before CSS computation.

Replacement is atomic: DOM children and generated pseudo-elements are suppressed. Natural preferred width is used unless CSS allocates another size. The engine retains outer sizing, borders, padding, clipping, scrolling and stacking. `ContentLayout` separates natural flow height from drawing extent. Local drawing is clipped to its bounds by default; a provider can set `clipToBounds` to false for overflow such as an embedded popup. CSS clipping on the host and its ancestors still applies. Results retain only `ContentLayout` drawing data, not the provider. Drawing state must be balanced; values are copied without external references, objects or resources. No native handle or backend registry token is needed to replay the result. This is an experimental in-memory contract, not a serialization protocol.

Image and gradient backgrounds retain numeric tile size, position, origin and repetition rather than expanding into drawing operations per tile. Text clipping carries the actual laid-out text runs and their transforms and clips. Non-normal background blend modes form an isolated group: only that element's background layers participate in the blend backdrop. The shared compositor uses floating-point RGBA values and delegates image pixels to the backend. A group accepts at most 128 drawing operations. The terminal backend additionally reserves at most 16,777,216 decoded/resampled pixels, 65,536 small cached sample cells and 64 MiB of encoded raster data per group before decoding; exceeding these budgets throws a `LengthException`. Its text masks retain the existing terminal limitations for scaled or rotated glyphs.

An optional `colorLookup` resolves color names for a host palette during computed-color resolution, gradient operand resolution and column-rule painting. Omitting it preserves CSS color semantics. The terminal bridge exposes an explicit legacy palette lookup for compatibility consumers; the new document widget keeps CSS-standard color-function math by default.

Twig and terminal integration
----------------------------

Twig remains optional and evaluates templates before rendering:

```php
$result = $renderer->render($twig->render('dashboard.html.twig', $parameters), $context);
```

The terminal adapter lives in `symfony/tui-html-bridge`, under `Symfony\Bridge\TuiHtml`.
It depends on both HtmlRenderer and Tui. HtmlRenderer requires PHP 8.4.1, ext-dom, PSR logging contracts and the ctype/mbstring polyfills; it has no Tui, Twig or FrameworkBundle dependency.
The bridge owns terminal measurement, cell quantization, ANSI output and Tui lifecycle integration.
The existing template/component integration remains available there during migration.

Current migration limits
-----------------------

The standalone path shares the original CSS tokenizer, selector parser, specificity, conditional grammar, length arithmetic, color/gradient calculations, counters, form constraints, easing and vector geometry. It resolves cascade layers, inherited/custom properties, logical dimensions and presentation hints against explicit viewport, font and media metrics. Parsing a CSS feature does not mean its layout or painting is fully implemented.

Independent layout covers block and inline flow, rectangular floats, flex and named grid tracks, table spans, multicolumn fragmentation, positioning, fractional geometry and text line breaking. Simple inline text can fragment across an existing line and continue on full subsequent lines; undecorated inline ancestors also split around in-flow block descendants without changing semantic DOM ancestry. Decorated inline fragmentation remains incomplete. Generated content and list markers produce boxes independently of DOM elements. A measurement policy can reserve an outside-marker gutter during layout; the engine retains grouping and sizing semantics. Ruby annotations and first-letter fragments share the extracted segmentation algorithms. First-line styling currently covers color and decoration, not changes to font metrics. Named text highlights retain native text identity and participate in measured fragmentation. Painting includes text runs, borders, backgrounds, gradients, transforms, clipping, opacity groups, color-filter groups, images and inert SVG payloads. Color-filter groups retain ordered grayscale, sepia, saturation, hue rotation, brightness, contrast, inversion and opacity functions around an element and its descendants. Spatial blur and drop-shadow painting remain migration work. Background layers support border-box, padding-box and content-box clipping without rescaling gradients. Rectangular outlines preserve fractional width and offset without affecting layout. They paint after ordinary flow in each stacking context and before positioned layers, matching the existing terminal overlay order and the early outline placement allowed by CSS 2.2; clipping from ancestors remains active. Rounded outline contours and box-shadow-derived terminal rings remain unsupported in the independent pipeline. Rounded borders carry resolved radii; rounded background and descendant clipping remain unsupported. Image bytes and SVG source remain declarative data; a backend supplies their device rendering. Failed image resources preserve valid width and height attributes and plain alternative text. With only one declared dimension, the engine does not reproduce a browser's broken-image icon or its fallback intrinsic ratio; the undeclared axis follows the current text fallback layout. The terminal bridge keeps its native bracketed alternatives, decorative-image suppression and device-specific natural image sizing.

Sessions provide normalized keyboard editing, grapheme selection, pointer hit testing, focus navigation, control values, listbox options, validation, submission, reset, details disclosure, modal dialogs, popovers, scrolling, host-maintained states and explicit animation time progression. `key()` accepts a `KeyboardEvent`; `advance()` progresses animations, and `isAnimating()` tells a host whether more frames are needed. `setState()` exposes host states through selector matching without modifying source attributes; session-managed states such as focus cannot be overridden this way. The application supplies physical input and scheduling. `handlePointer()` accepts normalized `PointerEvent` press, movement and release records in document coordinates; it supports control selection gestures, active state, range dragging and overlay dismissal. `showModal()`/`close()` and `showPopover()`/`hidePopover()` control overlays. Invoker buttons support dialog/popover commands and custom `--name` commands through cancelable `command` events. Interest targets open popovers while their invoker or popup is focused or hovered. Implicit anchors use floating-point invoker bounds and containing-layer geometry; cyclic anchor dependencies retain their initial placement. Top-layer fragments escape ancestor clipping and transforms without changing DOM inheritance; modal focus remains contained until dismissal.

Named anchors are resolved from computed principal styles within their document and rendering tree. The lookup honors `anchor-scope`, prefers an eligible ancestor, then the last eligible name in native tree order, and rebuilds after document mutations. Scope buckets and binary search avoid repeatedly scanning all positioned names. Cross-shadow-tree name provenance, pseudo-element anchors and complete containing-block eligibility remain unsupported. Anchor-based sizes, single `anchor-size()` calls within math expressions, size fallbacks and simple `anchor()` edge insets reuse the extracted grammar. Named references can address separate size and inset anchors. Dependent layers relayout after their anchors; stable box identity survives changes to fragment paths. Position areas constrain the available layout region and preserve natural content sizing. Anchor centering, block/inline flip tactics and named `@position-try` alternatives support position areas, anchors, physical insets and width/height overrides. Alternatives use first-fit or the requested most-width/most-height ordering. Complete anchor alignment, inset adjustments and logical-axis sizing remain incomplete. Translation refreshes only moved subtree geometry; size changes conservatively rebuild the geometry index.

The terminal bridge retains the previous widget rendering path and its compliance fixtures while the display-list path is developed. Moving those files is not evidence that all their algorithms have migrated. Outstanding work includes complete advanced grid/table/multicolumn behavior, full container-query and containment coverage, richer native control behavior, full painting support and dirty-region layout updates. Native DOM mutation detection currently triggers a complete frame recomputation. The retained path and new path must converge before this refactor meets its acceptance criteria.

Graphical desktop/mobile, PDF and image backends are architecture scenarios only. None is implemented here.
Native hosts can supply viewport/font metrics, consume display lists, translate input and schedule frames externally; a stable process protocol and native control mapping remain separate work.

Testing
-------

The component's tests use a local autoloader that loads no Tui package:

```bash
php /path/to/phpunit -c phpunit.xml.dist
```

The bridge preserves the original HTML fixtures and terminal snapshots. Headless fixture execution checks crashes and resource use; it does not establish visual or CSS compliance.
See the bridge's migration notes for baseline and validation details.

Static analysis can run with `phpstan analyse -c phpstan.neon.dist` from the component directory. The configuration treats DOM queries as potentially mutable across callbacks; event-listener revalidation and parser depth limits remain enforced.
