CHANGELOG
=========

8.2
---

 * Preserve case-sensitive animation names and custom properties in keyframes, and resolve scroll timelines for hidden overflow, including overflowing descendants
 * Keep empty implicit grid tracks at zero width, allow row-flow placement past the template, and preserve the inherited track widths of wide subgrids
 * Collapse adjoining margins through chains of empty blocks
 * Align transparent-bordered flex items with bordered siblings and paint centered content past both edges of fixed-height flex rows
 * Lay out tables beside floats in their own formatting context
 * Draw relative elements with a negative z-index below the flow and preserve visible overflow past anchor-sized heights
 * Add `TerminalInterface::setProgramStatus()` and `TerminalInterface::clearProgramStatus()` to report the status of the program and its tasks with the Program Status Protocol (OSC 7501)
 * Add `TerminalInterface::pushTitle()` and `TerminalInterface::popTitle()` to save the title of the terminal and put it back, which `Html\HtmlView::unmount()` does
 * Add `Html\HtmlBuilder` to build widget trees from HTML: block, text and form elements map to widgets, custom elements to application widgets, `<style>` and `<link rel="stylesheet">` feed the stylesheet
 * Add `Css\CssStyleSheet` to style widgets with CSS: Selectors Level 4 matched by the native DOM engine, the cascade, inheritance, custom properties and `var()`, `light-dark()`, `color-mix()`, and `@media` on width, height, color scheme, contrast, reduced motion, forced colors, resolution and density
 * Add `Css\FilesystemStylesheetLoader` and `Css\AssetMapperStylesheetLoader` to resolve `<link>` and `@import`
 * Add `Style\StyleProviderInterface` and `StyleSheet::addProvider()` to contribute styles a selector map cannot express
 * Add `Style\MediaContext`, `Tui::setMediaContext()` and `Tui::getMediaContext()` to describe the color scheme, density, contrast, motion and cell pixel size media queries are evaluated against
 * Add `AbstractWidget::setState()` and `AbstractWidget::hasState()` for custom state flags, matched by state selectors like `:hover`
 * Add `FocusManager::setTabIndex()` and `FocusManager::getCycle()`; disabled widgets are skipped by the focus cycle
 * Add `RichTextWidget` for text made of inline elements formatted by CSS (generated content, list markers, text transforms, whitespace, OSC 8 links), and `LiveRegionChangedEvent` to announce the changes of live regions
 * Add `TextWidget::setTruncate()`
 * Add `ButtonWidget` and `PressEvent`, mapped from `<button>`
 * Add `CheckboxWidget`, `RadioWidget` and `RadioGroup`, mapped from `<input type="checkbox">` and `<input type="radio">`
 * Add `HorizontalRuleWidget`
 * Map `<img>` to its alt text, the table elements to flex rows and cells, `<meter>` to a progress bar, and `<address>` and `<hgroup>` to containers
 * Add `HtmlView::computeStyles()` to compute the styles of a view outside a frame
 * Add margins to `Style`: `Style::getMargin()`, `Style::withMargin()` and the `$margin` constructor argument, drawn around the border like the parent
 * Add percentage widths and row bounds to `Style`: `withWidthPercent()` and `withRows()`, from CSS `width`/`max-width` percentages and `height`, `min-height` and `max-height`
 * Add `HtmlView::widgetAt()`, `HtmlView::hover()`, `HtmlView::click()` and `HtmlView::titleAt()` to route pointer events to a view: hover states, focus and activation, tooltips
 * Add `ActivatableInterface` for widgets with a default action, implemented by `ButtonWidget`, `CheckboxWidget`, `RadioWidget` and `CollapsibleWidget`
 * Add a shrink factor to `Style` (`withShrink()`, CSS `flex-shrink`), weighing how intrinsic widths give way in a row that is too narrow
 * Add the `$ellipsis` argument to `TextWidget::setTruncate()`, from CSS `text-overflow`
 * Add `GeneratedContentInterface` for widgets whose decorations CSS `::before` and `::after` replace, like the brackets of `ButtonWidget`
 * Add the `$parent` argument to `HtmlView::mount()`, to mount a view in a container of the application
 * Add `WidgetContext::resolveStyle()`
* Add `InputWidget::setPlaceholder()` (styled by the `placeholder` sub-element) and `InputWidget::setReadOnly()`
* Add the `placeholder-shown`, `open` and `indeterminate` states to `InputWidget`, `CollapsibleWidget` and `ProgressBarWidget`
* Add `Style\Justify` and `Style::withJustify()` to share the free columns of a row, from CSS `justify-content: space-between|space-around|space-evenly`
* Add `RichTextWidget::setLink()` to make a whole text block a hyperlink
* Add `LinkedTextWidget` and `LinkEvent`: HTML text with links takes the focus, Tab and Shift+Tab move between its links, Enter follows the current one, going to a fragment of the page or leaving the link to the application
* CSS heights include the padding and the border, like widths (`box-sizing: border-box`, the initial value here, like the common reset); `box-sizing: content-box` adds them to the widths and heights set
* Support CSS `@property`: a registered custom property has its `initial-value` where nothing sets it, does not inherit with `inherits: false`, and transitions when its `syntax` is typed; a value not of the syntax is invalid at computed-value time, inherited or initial
* Support CSS `all`; `unset` inherits an inherited property, `revert` goes back to the user agent value, `inherit` without a parent value and `initial` give the initial text look over the default classes (`color: initial` is the terminal color)
* Support the CSS `grid-template` and `grid` shorthands (area strings with their row heights, `auto-flow [dense]`); the inline children of a grid container are grid items, like those of a flex container
* Support CSS `word-spacing`, `text-align-last` and `white-space-collapse` (with `text-wrap-mode`); add `InlineFormat::$wordSpacing` and `$alignLast`
* Add `Style\Gradient` and `Style::withGradient()`: CSS `linear-gradient()` and `radial-gradient()` in `background-image` and `background` paint a color per cell over the background, what has its own background staying as it is
* Add `Style::withScrollMargin()` and the top and bottom padding of `Style\Scroll`: CSS `scroll-margin` and `scroll-padding` keep rows clear when a target or the focus is scrolled into view, like under a sticky header
* Add `InlineFormat::$marker` and `RichTextWidget::getMarker()`: list markers are outside the text by default, like CSS `list-style-position: outside`, the lines of an item that wraps aligned after it; `inside` keeps the marker in the text
* Add `Style::withColumnBreaks()` and `Scroll::$gutter`: CSS `break-before` and `break-after: column` start a column in a multi-column container, `scrollbar-gutter: stable` keeps the column of the scrollbar when the content fits
* Add `Scroll::$smooth` and `Renderer::isScrolling()`: with CSS `scroll-behavior: smooth`, a scroll container glides to where it is scrolled, by the wheel, the pages, a target or the focus, at once with `prefers-reduced-motion`
* Add `NativeAppearanceInterface`, implemented by `ButtonWidget`, `CheckboxWidget` and `DropdownWidget`: CSS `appearance: none` draws a button without its brackets, a checkbox or radio without its box and a select without its marker; checkboxes and radios draw their `::before` and `::after` content
* Support HTML presentational hints, under any CSS rule: `textarea[cols]`, `width` on tables and cells, `align`, `nowrap`, `bgcolor` and `table[border]`; the width of a table cell is a least width of its column
* `<picture>` draws the image of its first `<source>` whose media query matches, else its `<img>`, chosen again as the media change; sources are read through the factory of `<img>` and its loader
* In a flex column, `align-items` and `align-self` place each item across: an item not stretched is as wide as its content, at the start, the center or the end; `baseline` is like `start`
* `<progress>` draws its bar and percentage without a step count; without a value it moves, still with `prefers-reduced-motion`; a hidden widget has no rect
* Support the `closedby` attribute of `<dialog>`: `any` closes it on Escape or a click outside, `closerequest` (the default of a modal one) on Escape, `none` (the default of the others) only from the application
* Add `PartsInterface`, implemented by `CollapsibleWidget`: the `<summary>` of a `<details>` is matched like an element, its style applied, its marker following `list-style: none`, `::marker` content and `::-webkit-details-marker`
* Support `popover="hint"` and `interestfor`: pointing at or focusing an element shows the popover it names, anchored to it, like a tooltip; a hint popover closes the other hints, not the auto ones
* Support container style queries: `@container style(--theme: dark)` tests the computed values of the nearest ancestor, or of the nearest container of a name, with `not`, `and` and `or`
* `<form>` and `<fieldset>` match `:invalid` when a control inside them is invalid, `:valid` otherwise
* Add `Position::$translate`, `withTranslate()` and `translation()`: CSS `transform: translate()` and `translate` take percentages of the element itself, like `translate(-50%, -50%)` to center it
* An inline element with `position: absolute` or `fixed`, like a badge in a paragraph, is a widget of its own, placed like a block
* Add `FieldSizingInterface`, implemented by `EditorWidget`: a `<textarea rows>` is that many lines, scrolling past them, unless CSS `field-sizing: content` lets it grow; `<input size>` is a width hint
* Support negative and percentage `text-indent`, with `hanging` and `each-line`; a line pulled out goes over the left padding
* Support `<textarea wrap="off">` and `white-space: pre`/`nowrap` on textareas: each line on one row, scrolled sideways to the cursor (`LineWrappingInterface`)
* Support CSS `hwb()`, angle units (`deg`, `grad`, `rad`, `turn`) for hues and `none` channels in colors; the alpha of every color function comes after a slash
* Support CSS `lab()`, `lch()`, `oklab()`, `oklch()` (the colors of Tailwind 4) and `color()` in the predefined spaces; a color out of the sRGB gamut has its OKLCH chroma lowered, like CSS gamut mapping
* CSS `color-mix()` interpolates in the color space it names, oklab by default, along the hue its method says (`shorter`, `longer`, `increasing`, `decreasing`), premultiplied by the alpha; percentages summing to less than 100% make the mix translucent
* Support CSS relative colors, like `oklch(from var(--brand) calc(l + 0.1) c h)`, in every color function, and the system colors (`Canvas`, `CanvasText`, `LinkText`, `Field`...), Canvas and CanvasText being the terminal background and text of the color scheme
* CSS animations and transitions follow the curves of `cubic-bezier()` and the named easings, and the stops of `linear()`; numbers go past their keyframes when a curve does, colors stay between
* Run every CSS animation of a list, the longhands repeating like CSS lists: a later animation wins on the properties both animate, and an animation keeps going when the list changes around it
* CSS `:is()`, `:where()`, `:not()`, `:has()` and `:nth-child(… of …)` match before a combinator whatever their arguments and however deep the element is, like `:is(nav a, footer) p` and nested rules (`.card { footer { span {} } }`): PHP's engine tested the nearest ancestor only
* Add `Style::withFixedLayout()`, from CSS `table-layout: fixed`: a table of a set width takes the column widths of its `<col>` elements, then of its first row, the other columns sharing what is left evenly, without measuring the rows after the first, collapsed or not
* Add `Style::withHideEmpty()`, from CSS `empty-cells: hide`: in a separate table, a cell without content keeps its room and draws no border nor background
* CSS `vertical-align: top | middle | bottom` places the content of a table cell in the height of its row, or of the rows it spans, collapsed or not; `td` and `th` are in the middle by default, like HTML
* Add `Style\Filter` and `Style::withFilter()`, from CSS `filter`: `grayscale()`, `sepia()`, `saturate()`, `hue-rotate()`, `brightness()`, `contrast()`, `invert()` and `opacity()`, one after the other, filter every color of an element and of what it contains, cell by cell
* Add the `$percents` argument to `Style\Padding`, `Padding::resolve()` and `RenderContext::withContainingColumns()`: CSS `padding` and `margin` percentages, `calc()` mixing them with lengths, are shares of the width of the containing block, for every side, an item of a row taking them of the row
* `Style::withFlex()` and `Style::withShrink()` take fractions, `getFlex()` and `getShrink()` returning `int|float|null`: CSS `flex-grow` and `flex-shrink` are not rounded anymore, and factors summing to less than 1 share only that part of the free columns
* Add `GridTemplate::$columnFlow` and `$autoColumns`, from CSS `grid-auto-flow: column`, `grid-auto-columns` and the `grid: rows / auto-flow [dense] [columns]` shorthand: items fill the explicit rows column after column, the columns added as needed taking the width of `grid-auto-columns` or of their content
* CSS `:lang()` and `:dir()` follow the closest element setting `lang` or `dir`: an inner `lang="en"` in a French section is not French anymore
* CSS `:read-only` matches every element that is not `:read-write`, not only form controls
* Add `RadioGroup::updateIndeterminate()`: the radios of a group none of which is checked match CSS `:indeterminate`, a radio without a name being its own group; `<input type="radio">` sharing a name are grouped per form
* Support CSS `:default`: the first submit button of a form, the one implicit submission presses, and the checkboxes and radios with the `checked` attribute
* Add `InlineFormat::$firstLine`, from CSS `::first-line`: the first line of a text block, as wrapped, takes its colors and text attributes, under those of the inline elements on it
* CSS `font-weight` of 300 and below is dim (SGR 2), what is heavier inside it is not; `bolder` and `lighter` are relative to the weight of the parent, like the table of CSS Fonts
* Add `Length::tabSize()`: CSS `tab-size` takes lengths, rounded to columns, and fractional numbers of spaces
* CSS `word-spacing` applies to inline elements too, `InlineFormat::$wordSpacing` set per element: the spaces inside one take its spacing, the space before it the spacing around it
* Support CSS `text-transform: full-size-kana` and combined values, like `uppercase full-width`: `InlineFormat::$textTransform` lists the transforms joined by `+`, in the order they apply
* Add `Length::MATH_FUNCTIONS` and `Length::isMath()`: CSS `round()` (with its strategies and step), `mod()`, `rem()`, `abs()`, `sign()`, `pow()`, `sqrt()`, `hypot()`, `exp()`, `log()`, `sin()`, `cos()`, `tan()`, `asin()`, `acos()`, `atan()` and `atan2()`, the constants `e`, `pi`, `infinity` and `NaN`, exponents and angle units are evaluated wherever `calc()` is
* CSS `steps()` with `jump-none` holds the first and last values for a step each, and `jump-both` jumps at both ends, instead of being like `jump-end`
* Support CSS `@counter-style`, every system with its descriptors (`symbols`, `additive-symbols`, `negative`, `prefix`, `suffix`, `range`, `pad`, `fallback`), for `list-style-type`, `::marker` and `counter()`; add the `lower-greek`, `cjk-decimal` and `disclosure-*` predefined styles; an unknown style is decimal, like CSS, instead of a bullet
* Add `MediaQuery::usesBlockAxis()`: a container query on the block axis (`height`, `block-size`, `aspect-ratio`, `orientation`) skips the `inline-size` containers for a `size` one further up, like CSS; support the `inline-size` and `block-size` container features
* Add `Style::withAlignContent()`, from CSS `align-content` and the first value of `place-content`: the wrapped lines of a flex row and the rows of a grid of a set height share its free rows (`start`, `center`, `end`, `space-*`), the lines stretching by default, like CSS
* Add `Border::$sidePatterns`, `Border::withSidePatterns()`, `Border::getSidePattern()` and `BorderPattern::corner()`: CSS `border-*-style`, the side shorthands and `border-style` with up to four values give each side its style, the corners joining light, heavy and double lines; thick and rounded apply by side
* Add `Style\Outline` and `Style::withOutline()`: CSS `outline` is drawn around a box without a border, over what is next to it, `outline-offset` cells further out or in, in its style (solid, dashed, dotted, double, thick ones heavy); on a border with no offset out, it still repaints the border
* Add `Style::withMarginPull()`: a negative CSS `margin-top` or `margin-bottom` pulls a block over what is before or after it, in block and flex column layouts, collapsing with the other margins like CSS, its spaces letting what is under them show; in `CellBuffer` transparent writes, a space with a foreground color only is transparent too
* The channels and the alpha of every CSS color function take math functions, like `hsl(calc(45 * 2) 100% 50%)`, not only those of relative colors
* In a row, a negative CSS `margin-left` or `margin-right` pulls an item over the one before it, like stacked avatars, never before the row; the columns it frees go after the last item
* Add `Length::angle()`: a hue made of math functions takes angle units, like `hsl(calc(30deg + 0.25turn) 100% 50%)`, and the functions nested in `sin()`, `cos()` and `tan()` take them too
* An `@container` condition joins size and `style()` conditions with `and`, like `@container card (min-width: 30ch) and style(--featured: true)`: both must pass
* Support CSS `transition-behavior: allow-discrete`: the values that cannot be interpolated change halfway through the transition instead of at once; `visibility` stays `visible` while one end is, in transitions and animations
* Add `HtmlView::press()` and `HtmlView::release()`: the widget a pointer button is pressed on and its ancestors match CSS `:active` until the button is released or `click()` is called
* Add `Scroll::$thin`, from CSS `scrollbar-width: thin`: the scrollbar is drawn with light lines
* Add `Shadow::$inset`, `Style::withInsetShadow()` and `CellBuffer::tint()`: the first inset CSS `box-shadow` shades the edges inside the border it is offset from, top and left, or bottom and right when negative, the text staying
* CSS `caption-side: bottom` draws the caption below a table with separate borders too, not only a collapsed one
* `CellBuffer` keeps the OSC 8 hyperlinks of the cells it is written, instead of dropping them: links stay where boxes overlap, under an inset shadow and in composited layers
* With no border nor padding between them, the negative CSS margin of a first or last block collapses into that of its parent, pulling the parent over its siblings, like CSS
* Support CSS `:heading` and `:heading()`: the `h1` to `h6` elements, or those of the levels listed, like `:heading(2, 3)`
* Support CSS `contrast-color()`: white or black, whichever has the higher contrast ratio with the color, like text over a background set by a custom property
* Add the `$customMedia` argument to `MediaQuery::parse()`: CSS `@custom-media --name <query>` names a media query list, tested by `(--name)` in `@media` and `@import`, defined before or after its use, in the style sheet or an earlier one
* Support CSS `sibling-index()`, `sibling-count()` and `attr()` in any property, typed with `type()`, `number` or a unit, with a fallback: they are resolved for each element, its children inheriting the values; `order`, `z-index` and the times of animations and transitions take math functions, like `calc(sibling-index() * 100ms)`
* Support CSS `if()`: the value of the first branch whose condition passes, `else` or `media()`, `supports()` and `style()` tests joined by `not`, `and` and `or`, like `padding: if(media(width < 50): 0; else: 4ch)`; without one passing, the declaration is invalid at computed-value time
* Add `Position::$anchorSizes` and `Position::anchorSizes()`: CSS `anchor-size()` in `width`, `height` and their `min-` and `max-` sizes an absolute or fixed element after its anchor, alone or in a math function, like `min-width: calc(anchor-size(width) + 4ch)` for a menu at least as wide as its button
* Add `Position::$tries` and `CssParser::takePositionTries()`: CSS `position-try-fallbacks` names `@position-try` rules and lists `position-area` values, tried in order when an anchored element would leave the screen, the first that fits placing it
* Add `Scroll::$contained`, from CSS `overscroll-behavior: contain | none`: the wheel and the page keys stop at a scroll container that cannot scroll further, instead of scrolling the ones around it
* Add `Scroll::$snap`, `Style::withSnapAlign()` and `Style::getSnapAlign()`, from CSS `scroll-snap-type`, `scroll-snap-align` and `scroll-snap-stop`: a scroll container scrolled by the wheel, the pages or `scrollTo()` rests on the snap position of a child nearest where it was scrolled to, in the way it went, not past a child with `scroll-snap-stop: always`
* Add `ContainerWidget::scrollToColumn()`, `scrollByColumns()`, `getScrollLeft()`, `getScrollWidth()` and `getClientWidth()`, `Scroll::$vertical`, `$horizontal`, `$alwaysSideways`, `$paddingLeft` and `$paddingRight`, and `RichTextWidget::wrapsLines()`: with CSS `overflow-x: auto | scroll`, what does not fit sideways (`<pre>`, `white-space: nowrap`, rows of items, tables as wide as their longest words) scrolls through a window with a scrollbar on its last row, the blocks that fit keeping its width; the focus and targets are scrolled into view sideways too, with the left and right `scroll-padding` and `scroll-margin`
* Add the `$columns` argument to `HtmlView::scrollAt()`, for a horizontal wheel or Shift and the wheel; Left and Right scroll the scroll container around the focus sideways, unless the focused widget moves with them
* With CSS `scroll-behavior: smooth`, a scroll container scrolled sideways glides there too, its scrollbar along
* Add `Scroll::$containedSideways`, from CSS `overscroll-behavior-x: contain | none` and the first value of `overscroll-behavior`: the wheel and the arrow keys stop at a container scrolled sideways
* CSS `:open` matches an `<input list>` showing its suggestions, like a `<select>` showing its list
* Support CSS `::picker(select)`: the list of a `<select>` drawn as a dropdown is styled like a box, its border, padding, background and colors, as wide as its widest option with them
* Add `Text\ElementContentInterface`, implemented by `DropdownWidget` and `SelectListWidget`, and `ButtonWidget::setIcon()` and `getIcon()`: CSS `::picker-icon` styles the ▾ of a `<select>` drawn as a dropdown and `::checkmark` the → of its selected option, their `content` replacing them, `none` hiding them; the field of a `DropdownWidget` has the choice as its text and the ▾ as its icon
* CSS `appearance: base-select` draws a `<select>` as a dropdown whose field has no brackets, its `::picker-icon` kept, for a select styled from scratch
* A customizable `<select>`, with a `<button>` and its `<selectedcontent>`, shows the content of the selected option in its field
* Add `Selector::DETAILS_CONTENT_ATTRIBUTE`: CSS `::details-content` styles the content of a `<details>` like a box, its border, padding and colors
* `CompatibilityReport` lists the CSS properties and selectors that have no meaning in a terminal as ignored, like `writing-mode`, `text-shadow`, `clip-path`, `::selection` and `:visited`, and their declarations are not reported as unsupported
* Add `TextWrapper::wrapTextWithAnsiWidths()`: the text of a paragraph beside CSS floats flows around them, its lines narrower beside them and under them once they end
* Add `InlineFormat::$cloneDecoration`, from CSS `box-decoration-break: clone`: an inline element that wraps has its padding and border on each of its lines, its padding kept with its first and last words
* Add `Scroll::$snapSideways` and the `$sideways` argument of `Style::withSnapAlign()`: CSS `scroll-snap-type: x` and the inline value of `scroll-snap-align` snap a container scrolled sideways
* Support CSS `text-align: match-parent`: the alignment of the parent, its `start` and `end` on the sides of the direction of the parent
* The CSS system colors `AccentColor` and `AccentColorText` are the `accent-color` of the element, when it sets one, and the color that contrasts with it
* CSS `object-position` takes lengths and offsets from an edge, like `right 2ch bottom 1`: cells further than the fraction of the room left, the third and fourth items of `Style::getObjectPosition()`
* Add `Style::withStickyBottom()` and `getStickyBottom()`, from CSS `position: sticky` with `bottom`: in a scroll container, the widget stays that many rows above the bottom while its parent is in view, like a sticky footer
* Add `WidgetRendererInterface::measureMinContentWidth()`: the columns of a table too wide for its room give way down to their longest words, like CSS, before they are cut evenly; a table scrolled sideways is as wide as they are
* Add `Style::getWrapReverse()` and the `$reverse` argument of `Style::withWrap()`: with CSS `flex-wrap: wrap-reverse`, the lines of a wrapping row go from the bottom up
* `ContainerWidget` implements `GeneratedContentInterface`: CSS `::before` and `::after` of a block holding blocks, like `.note::before { content: "Note " counter(note) }`, are lines of their own before and after its children, in the style of the pseudo-element
* Add `InlineFormat::$pretty`, from CSS `text-wrap: pretty`: the last line of a paragraph does not hold a single word when a narrower width brings another one down without adding a line
* The CSS background of a `<col>` or `<colgroup>` is under the cells of its columns that have none of their own, like a browser
* CSS `break-before` and `break-after: avoid` keep an element in the column of the one before or after it, like a heading kept with its text; the third and fourth items of `Style::getColumnBreaks()` say so
* Add `DropdownWidget::setOptionContents()` and the `$styled` argument of `ButtonWidget::setText()`: the elements of the `<option>`s of a dropdown are styled by CSS, like `option .status { color: red }`, in its list and in its field, the `<selectedcontent>`
* The options of a customizable `<select>` match `:default` when they have the `selected` attribute, and draw in their own colors and text attributes, like `option:default { font-weight: bold }`
* Support CSS `hyphenate-character`: the string ending a line broken at a soft hyphen; add the `$hyphen` argument to `TextWrapper::wrapTextWithAnsi()` and `wrapTextWithAnsiWidths()`, and `InlineFormat::$hyphen`
* Support CSS `anchor-scope`: the anchor names it lists, or all of them, are seen only by the absolute and fixed elements inside the element, like repeated cards each anchoring their own tooltip; add `Style::withAnchorScope()`
* Support CSS `env()`: the safe area and keyboard insets are `0px`, the other variables take their fallback
* Support CSS `corner-shape` and its longhands, the radii of each corner in `border-radius` and the `border-*-radius` longhands: a corner with a radius is round (╭), square, or beveled (╱) for bevel, notch and scoop; add `Border::withCornerShapes()` and `Border::$cornerShapes`
* Support CSS `position-visibility`: by default (`anchors-visible`) an anchored element is not drawn while its anchor is not, like scrolled out of view; `anchors-valid` hides it without an anchor, `no-overflow` when it does not fit on the screen; add `Position::$visibility`
* Support CSS `text-autospace`: `normal`, `ideograph-alpha` and `ideograph-numeric` put a space between ideographs and Latin letters or digits, across inline elements too; a space being a whole column, the initial value is `no-autospace`; add `InlineFormat::$autospace`
* Support CSS `hanging-punctuation`: with `first`, an opening bracket or quote starting a text block goes over its left padding; with `last`, a closing one ending it goes over its right padding when that saves a line; add `InlineFormat::$hang`
* Support CSS `initial-letter` on `::first-letter`: a drop cap drawn in a bundled FIGlet font as tall as its size, or the one its `font-family` names, the first lines of the text beside it; add `InlineFormat::$initialLetter`
* Support CSS `reading-flow` and `reading-order`: the focus cycle goes through the children of a reading-flow container as they are drawn (`flex-visual`, `grid-rows`, `grid-columns`), by `order` (`flex-flow`, `grid-order`) or as written (`source-order`), the children of a smaller `reading-order` first; add `Style::withReadingFlow()` and `Style::withReadingOrder()`
* Support CSS `scroll-state()` container queries and `container-type: scroll-state`: `stuck`, `snapped`, `scrollable` and `scrolled`, as the containers were drawn in the last frame; add `Style\ScrollStateAwareInterface` and `Renderer::getScrollState()`
* Support CSS `::scroll-button()`: the buttons of a scroll container with content are drawn on rows inside it, dim when it cannot scroll that way, and `HtmlView::click()` on one scrolls a page; add `Tui::getScrollControlAt()` and `Renderer::getScrollControlAt()`
* Support CSS `scroll-marker-group`, `::scroll-marker`, `::scroll-marker-group` and `:target-current`: the markers of the children of a scroll container are drawn on a row inside it, the current one matching `:target-current`, and `HtmlView::click()` on one scrolls to its child
* Support CSS `@function`: custom functions with typed parameters and defaults, local custom properties, `result` in `@media` and `@supports` rules too, and a return type, called in any property; add `CssParser::takeFunctions()`
* Support CSS `interpolate-size: allow-keywords` and `calc-size()`: a height transitions from or to `auto` through the height of the content in the last frame; add `Style\IntrinsicSizeAwareInterface` and `Renderer::getIntrinsicRows()`
* Support CSS scroll-driven animations: `animation-timeline` with `scroll()`, `view()` and named `scroll-timeline` and `view-timeline`, `animation-range`, `view-timeline-inset` and `timeline-scope`; an animation goes with where its scroll container is scrolled
* Accept and report as ignored the CSS properties, at-rules and selectors, the HTML elements and the global attributes without meaning in a terminal (SVG painting, masks, 3D transforms, motion paths, font variations, view transitions, shadow DOM, `@font-face`, `@page`...); support the namespace prefixes of selectors (`*|p`), and draw the content of `<map>` and `<slot>`
* The root of an `HtmlView` is always the widget of `<html>`, holding the one of `<body>`: their rules and their attributes (`lang`, `dir`, `class`...) apply, even with a single element in the body; add `HtmlView::getBody()` and `ContainerWidget::setPassesDefiniteHeight()`, which keeps the percentage heights of their children of the screen
* Support `<title>`: mounted at the root of the Tui, a view sets the title of the terminal; add `HtmlView::getDocumentTitle()`
* Support `<meta name="color-scheme">`: the first valid one is the `color-scheme` of `<html>`, under any CSS rule; add `CssStyleSheet::isColorScheme()`
* Support `<base href>`: relative links resolve against the first absolute one, for terminal hyperlinks and `LinkEvent`, links to a fragment staying in the page; add `RichTextWidget::setBaseUrl()`
* Support CSS `-webkit-text-fill-color`: the color of the glyphs, the borders and outlines keeping `color`
* Support CSS `forced-color-adjust: none`: an element and what inherits it keep their colors when colors are forced
* Support CSS `content-visibility: hidden` and `contain-intrinsic-size` with its longhands: the box is drawn without its children or its text, which take neither the focus nor the pointer, at the size given; add `Style::withContentHidden()`
* The root of the tree mirrored for CSS, like the one of the Tui, is a `tui-root` element: `:root` matches it, rules like `div { height: 3 }` do not size the screen anymore; add `CssStyleSheet::ROOT_TAG`
* Support CSS `background-clip` (`text` included), `background-origin`, `background-size`, `background-position` and `background-repeat` with their longhands, in the `background` shorthand too: a gradient is sized, placed and tiled in its positioning area, the padding box by default like CSS (a gradient on a bordered box starts inside the border; `background-origin: border-box` lays it over the border again); add `Style\BackgroundLayout`, `Style\BackgroundLength` and `Style::withBackgroundLayout()`
* Support CSS `line-break: anywhere`: lines break between any two characters, in `pre-wrap` text too
* Support CSS `hyphenate-limit-chars`: with `hyphens: auto`, a word breaks at a soft hyphen only when long enough, with enough characters before and after the break
* Support CSS `position-try-order`, in the `position-try` shorthand too: the fallbacks leaving the most width or height next to the anchor are tried first
* Support CSS `animation-composition`: `add` and `accumulate` add the keyframes to the element's value, numbers of the same unit and colors channel by channel
* Fix the right side of an outline on a row shorter than the box, like a block with a margin: it was drawn past the screen
* The focus on a widget a frame does not draw, like one `display: none`, `visibility: hidden` or `content-visibility: hidden` hides, moves to the first one of the focus cycle before the frame is written, the first focus of a view included
* `ScreenBuffer::getStyledScreen()` keeps the last rows that show a style on their spaces, like a background, instead of cutting them as empty
* Support CSS `conic-gradient()`, `repeating-linear-gradient()`, `repeating-radial-gradient()` and `repeating-conic-gradient()`, and gradient stops in lengths or, around a conic one, angles; add `Gradient::resolve()` and the `repeating`, `from` and `rawStops` arguments of `Gradient`
* Support CSS `url()` images in `background-image` and `background`: read from a data: URL or the stylesheet loader, sized (`background-size`, `cover` and `contain` keeping their proportions), placed and tiled like gradients, two pixels per cell, half blocks in the spaces and the average of the two under the characters; add `Style\RasterImage`, `Style::withBackgroundImage()`, `DeclarationMapper::setImageLoader()` and the `fit` of `Style\BackgroundLayout`
* Support CSS `image-set()` and `-webkit-image-set()` in backgrounds: the option for the resolution of the screen, its size divided by its resolution, `type()`s that cannot be decoded left out; add `RasterImage::withResolution()`
* Support CSS `border-image` and `border-image-source`: the lines of the border take the colors of a gradient or an image stretched over the box, cell by cell; the slice, width, outset and repeat are accepted; add `Style::withBorderImage()`
* Support the shape (`circle`, `ellipse`), size (`closest-side`, `farthest-corner`... or radii) and position (`at`) of `radial-gradient()`, and the position of `conic-gradient()`; add the `circle`, `extent` and `center` arguments of `Gradient`
* Support `drop-shadow()` in CSS `filter`, the shadow of the box when `box-shadow` sets none, and accept `blur()`, which a cell cannot show, instead of dropping the whole filter
* Support CSS `font-variant-position: super | sub`, inherited, on blocks too: superscript or subscript characters when Unicode has all of them
* The CSS `font` shorthand sets `small-caps` and the line height after a slash too, resetting them when it does not, the longhands after it winning
* Support CSS `field-sizing: content` on text inputs: as wide as their value, or their placeholder, and the cursor, growing as one types, within their width; `InputWidget` implements `FieldSizingInterface`, add `InputWidget::getContentColumns()`
* The CSS `font-variant` shorthand accepts all its values, using the caps (petite caps drawn as small ones) and the position (`super`, `sub`), instead of dropping the declaration for a ligature or numeric value
* CSS `quotes: auto` takes the quotation marks of the language of the element, its `lang` or its parent's: « » in French, „ “ in German, 「 」 in Japanese...
* Support the CSS viewport units: `vw`, `vh`, `vi`, `vb`, `vmin` and `vmax`, and their small, large and dynamic forms (`svh`, `dvh`...), shares of the screen, in `calc()` too
* Accept more CSS values instead of dropping them: the two-keyword `display` (`inline flex`, `block flow-root`...), `list-item`, `ruby` and the `-webkit-box` of line clamping; `white-space: break-spaces` and its pairs (`preserve nowrap`); `text-align: -webkit-center` and the other prefixed alignments; `vertical-align` lengths; a list of `anchor-name`; and report `-webkit-box-orient`, `font-stretch` and `text-justify` as ignored; add `CssParser::display()`
* Support CSS `width` and `max-width` keywords: `fit-content` and `max-content` make the box as wide as its content within the room, `min-content` as narrow as it can be, `stretch` and `-webkit-fill-available` take the room; add `Style::withContentWidth()`
* Support the logical keywords of CSS `position-area`: `x-start`, `block-end`, `span-inline-start`, `start end`, `self-...`, from the left and the top
* Fix the size of a container narrower than its row, like one with a width: container queries measured the whole row
* Support the CSS container units: `cqw`, `cqh`, `cqi`, `cqb`, `cqmin` and `cqmax`, shares of the nearest container as laid out in the last frame, or of the screen without one
* Support CSS `min()`, `max()` and `clamp()` of a percentage and lengths in `width` and `max-width`, like `min(100%, 60ch)`, the percentage bounded by the lengths; accept `:-webkit-any()` (like `:is()`), the `-webkit-link` color, a thickness in `text-decoration`, and report `-webkit-overflow-scrolling` and `-ms-overflow-style` as ignored
* A `<details>` is named by its `<summary>` and text with links by what it says, like an accessible name from content, instead of being reported without a name
* Support `<input type="hidden">`, not drawn, its value submitted with its form, and `<input type="image">`, a submit button labeled by its `alt` that sends `name.x` and `name.y`
* Draw the obsolete elements like browsers: `<center>`, `<font color>`, `<big>`, `<tt>`, `<acronym>`, `<nobr>`, `<strike>`, `<listing>`, `<xmp>` and `<marquee>` (which does not scroll); inline elements take presentational hints too, and `white-space: nowrap` on one keeps its words on one line
* An element clipped to nothing, by `clip: rect(0, 0, 0, 0)` or `clip-path: inset(50%)` like the visually hidden pattern of `.sr-only`, is not drawn and does not split the text it is in, its text staying in accessible names; a `<button>` draws its text without what is hidden; accept `-webkit-text-decoration` and report `-webkit-backdrop-filter` as ignored; add `CssStyleSheet::clippedToNothing()`
* A vendor-prefixed CSS property that is not known itself, like `-moz-user-select` or `-o-object-fit`, stands for the one it prefixes, supported or ignored, like browsers alias them
* A table with `border-collapse: collapse` and a width, like `width: 100%`, takes it, its columns sharing the room left as they are wide
* A field in its `<label>`, like `<label>Name <input></label>`, is on the line of its text, like the inline control it is in browsers, taking the room left
* Fix CSS values with a string holding a space, like `list-style: "✓ "`, which were split inside it, and the `list-style` shorthand with its position first, like `inside square`
* A text block that scrolls sideways, like `pre { overflow-x: auto }`, scrolls its text instead of wrapping it: it is built as a container of its text
* An absolute or fixed `::before` or `::after` of a block is drawn over the frame like an absolute element, the block its containing block when positioned, like a tooltip in `.tip:hover::after { position: absolute }`, instead of being added to its text; add `Style\PseudoElementProviderInterface`
* An inline element displayed as a block, like `nav a { display: block }`, is laid out apart from the text around it, on lines of its own, instead of staying in it
* A `<meter>` draws its bar and its percentage, like `<progress>`, without the counts of steps that crowded out the bar in a narrow box
* A row of a set height is as tall as it, its items stretched to it, like a single-line flex container, and the `fr` rows of a grid of a set height share its free rows as their fractions say, like an app shell whose sidebar takes the height of the screen; add the `rowFractions` of `Style\GridTemplate`
* An inline element displayed as a block or placed out of the flow in some state only, like `input:invalid + .error { display: block }`, is laid out apart from the text too, a sibling of what is before it
* Fix the rows of a `<textarea>` shorter than its `rows`: it is that many lines tall, like in browsers, instead of as tall as its content
* The text between blocks is no element for selectors, like in browsers: `p:first-child` matches a paragraph after text, `input + .unit` a span after an input, `:nth-child()` counts elements only; add `CssStyleSheet::setAnonymous()`
* A sticky part of a table, like `thead th { position: sticky; top: 0 }`, stays in view while its table is, not only within its row
* CSS `opacity: 0` draws nothing of an element, what is behind it showing, though it keeps its room, the focus and the pointer, like the checkbox of a CSS-only menu hidden behind its label; add `Style::withTransparent()`
* The height a box takes from its `aspect-ratio` is definite, like CSS: the percentage heights of its children are of it, like an image covering a 16:9 thumbnail
* The marker of a list item running right to left, like `<ul dir="rtl">`, is drawn on its right, at the start of its line
* `::before`, `::after` and `li::marker` are drawn in their own colors and text attributes, like `li::marker { color: yellow }`, over those of their element
* Padding and margins take `min()`, `max()` and `clamp()` over percentages and lengths, like `padding: 0 max(1ch, 5%)`
* Grid tracks take percentages in `calc()`, `min()`, `max()` and `clamp()`, like `repeat(auto-fill, minmax(min(20ch, 100%), 1fr))`
* The insets of positioned elements take percentages in `calc()`, `min()`, `max()` and `clamp()`, like `left: calc(50% - 10ch)`, and in the `inset` shorthand
* The arguments of `min()`, `max()` and `clamp()` over percentages can be sums without `calc()`, like `max-width: min(60ch, 100% - 4ch)`
* `width: fit-content(24ch)` makes a box as wide as its content, up to that length
* `font-family` is a list, like browsers: the first registered FIGlet font of it is drawn, the others, like the web fonts of `font-family: Inter, sans-serif`, are passed over, and add `FontRegistry::first()`
* An `<img>` that cannot be drawn and an `<svg>` show their text alternative in the line of the text around them, like browsers, inside links and buttons too; the `<title>` of an `<svg>` names it
* MathML is drawn as linear text in the line of the text around it, like `x^2+(a+b)/2`, `<math display="block">` on a line of its own
* `<canvas>`, `<object>`, `<video>` and `<audio>` draw their fallback content, like browsers that cannot draw them
* Gradients interpolate their colors in the color space they name, like `linear-gradient(to right in oklch, red, blue)` or `in hsl longer hue`
* Add `interactivity: inert`, like the `inert` attribute: what it applies to takes neither the focus nor the pointer, and `Style::withInert()`
* `anchor()` works in the `inset`, `inset-block` and `inset-inline` shorthands, like `inset: anchor(bottom) auto auto anchor(left)`
* `justify-self` and `align-self: anchor-center` center an element placed next to its anchor across or down it
* `page-break-before` and `page-break-after` are read like `break-before` and `break-after`
* Add `caret-animation: manual`, a caret that does not blink, the `caret` shorthand, and the steady shapes of `CursorShape`
* Add `scroll-initial-target: nearest`: a scroll container starts scrolled to the element, like the latest entry of a log
* `line-height` puts a blank row between lines from 1.75 on: the 1.5 of the text of many pages does not double space it
* A collapsed table draws a line where the table, a row group, a row or a cell beside it has a border on that side, like `td { border-bottom: 1px solid }` drawing the lines under the rows only
* The code of a `<pre>`, like `<pre><code>`, is not drawn in reverse video like inline code
* The top and bottom padding and margins are a row from three quarters of one: the `padding: .5rem 1rem` of buttons and fields leaves no blank rows above and below their text
* `outline-offset` is a cell from three quarters of one: the `2px` of a focus ring repaints the border instead of drawing a box two cells around it
* A transparent border, like `border: 1px solid transparent`, keeps its room and is drawn as the background
* The field of a `<select>` is a part of it, `tui-select-field`, that the `button` rules of a page do not style, like in browsers; the `<button>` of a customizable select is still the page's
* A `<button>` that CSS gives a border is drawn without its brackets, the border being its look, like browsers
* A text field its content sizes, like in a flex row, is 20 characters wide without a `size` attribute, like browsers, instead of all the room, and add `InputWidget::setPreferredColumns()`
* The gaps between rows, the insets of positioned elements and `letter-spacing` and `word-spacing` are a cell from three quarters of one, like padding: the `gap: .25rem` of a card or the `letter-spacing: .1em` of a heading leave nothing
* A flex column of a set height, a least height or stretched shares its free rows: the items that grow (`flex: 1`) first, like a `<main>` pushing a footer to the bottom, then the auto margins, like `margin-top: auto` pushing a button to the bottom of a card, then `justify-content: space-*`; add `Style::withAutoMarginRows()`
* `transform` accepts `scale()`, `rotate()`, `skew()`, `matrix()` and `perspective()`, which change nothing, cells being neither scaled nor turned, and the x and y of `translate3d()`
* Buttons, fields, selects, meters and labels are laid out in the line of the text around them, like browsers, side by side and wrapping, like `<label>Search</label> <input> <button>Go</button>`, unless CSS displays them as blocks
* The items of a row that gives way do not shrink below their longest word, like the `min-width: auto` of CSS, when there is room for it: a label beside a slider is not broken in a word
* The first `<legend>` of a `<fieldset>` is drawn in its top border, like browsers, and add `ContainerWidget::setLegend()`
* An image drawn is in the line of the text around it too, and a text that does not fit after an image or a control goes on beside it, wrapping, like in a paragraph; add `ContainerWidget::setInlineFlow()`
* `min-width` takes percentages, the least width of a box sized by its content, like `min-width: 100%` stretching a table to the room of its scroll container, and add `Style::withMinWidthPercent()`
* An `<hr>` given a top or bottom border, like `hr { border: 0; border-top: 1px solid }`, is drawn as that border alone, not with its own line inside it
* The link of a text with the focus matches `:focus` and `:focus-visible`, like `a:focus-visible { background: yellow }`, drawn instead of reversed when it gives a background, and `RichTextWidget::getCurrentLink()` is public
* A `<summary>` draws its `::before` and `::after` content, the latter at the end of the line in a flex row spread by `justify-content`, like the "+" of a FAQ, and add `CollapsibleWidget::setPartContent()`
* A percentage width or max-width is of the containing block, not of the room the layout gives the box, like a bubble of `max-width: 70%` in a flex column
* In a flex column of a set height too tall for it, the scroll containers give way, like the `min-height: 0` of CSS: a list of messages of `flex: 1; overflow-y: auto` scrolls above the field it leaves on the screen
* `all: unset` and `all: initial` take the look of a control away, like the brackets of a `<button>` styled from scratch, and `all: revert` gives it back
* The link of a text under the pointer matches `:hover`, like `a:hover { text-decoration: underline }`: each link has an OSC 8 id, which terminals group its pieces by; add `Tui::getLinkAt()`, `RichTextWidget::setHoveredLink()` and `RichTextWidget::linkId()`
* `HtmlView::cursorAt()` gives a pointer over the links of a text, like browsers
* The compatibility report lists `font-variant-position`, which writes superscript and subscript characters, as partial rather than ignored
* Support `font-variant-numeric` and its values in `font-variant`: `diagonal-fractions` writes 1/2 as ½ and other fractions with superscript and subscript digits, `ordinal` writes 1st as 1ˢᵗ, `slashed-zero` draws 0̸
* The caret of a field takes the `caret-shape` and `caret-animation` of the field, like `input { caret-shape: underscore }`, not only those of its `::cursor`
* Support `caret-color`, and the color of the `caret` shorthand: the terminal cursor takes it while the field has the focus (OSC 12), and its own color back otherwise and on exit (OSC 112)
* The text of a box keeps its background when its color has a zero channel in true colors, like `rgb(0 0 0)` through a `filter`, instead of taking the background of the box around it
* Support `mix-blend-mode`, `background-blend-mode` and `isolation`: the colors of an element and of what it contains blend cell by cell with the background behind it, and a background image or gradient with the background color, in the modes of the Compositing specification (multiply, screen, overlay, difference, luminosity...); add `BlendMode`
* The implicit rows and columns of a grid take the values of `grid-auto-rows` and `grid-auto-columns` in turn, like `grid-auto-columns: 6ch 12ch`, not only the first
* Grid line names inside `repeat()`, like `repeat(auto-fill, [cell] 8ch)`, and after it, are kept, once per repetition, and `grid-column: cell 2 / cell 4` takes the Nth line of a name; the tracks after a repeat that fills the room stay after it
* Draw an inline `<svg>` with half blocks, like an `<img>`, with the GD extension: its rects, circles, ellipses, lines, polylines, polygons and paths, in groups with their transforms, fill and stroke colors (`currentColor` too) and opacities; add `SvgWidget`
* Draw the document of an `<iframe srcdoc>` in a scrolled box of its `width` and `height`, styled by its own stylesheets only, its media queries of the size of the box, its fields in the focus order of the page; add `FrameWidget`, `HtmlView::getFrames()` and `CssStyleSheet::setScope()`
* The text of the paragraphs in a block beside a float flows around it too, like `<div class="card"><p>…</p></div>` next to a floated image, under it once it ends, not only that of a paragraph beside it
* A table cell with `rowspan` is drawn across the rows it spans, each as tall as its other cells and the last taller when the cell needs more, not only in its first row
* An absolute or fixed element of a negative `z-index` is drawn under the flow, the text of the page over its background, and does not take the pointer; relative elements moved over each other are drawn in their `z-index` order, inside their margins
* Support `writing-mode: vertical-rl` and `vertical-lr` on text blocks: their lines are columns, from the right or from the left, one character a row, as wide as their columns, the logical sides and sizes physical ones (the inline start at the top); add `CellBuffer::transpose()`
* Support `font-size` where the terminal draws larger text with the text sizing protocol of kitty (OSC 66), which the application tells with `MediaContext::withTextSizing()`: a text block 2, 3 or 4 times as large, like `h1 { font-size: 2em }`, its lines as many rows tall; no terminal is asked, and elsewhere text keeps its size
* `grid-column` and `grid-row` take `span name` and `span N name`, like `grid-column: col 1 / span last`, reaching the Nth line of that name from the other line
* An inline `<svg>` draws its `<use>` elements, copies of the element or the `<symbol>` they name, moved by their `x` and `y`
* The columns of a text block written vertically are as long as the height of its parent when it is definite, like a `<div style="height: 4">`, not the height of the screen
* An inline `<svg>` fills its shapes with their `<linearGradient>` and `<radialGradient>`, their stops, units and `href`, instead of the fallback color
* A relative element of a positive `z-index` is drawn with the absolute and fixed ones, by `z-index`, like a raised card over an absolute badge of a lower one
* An inline `<svg>` draws its `<text>`, in the bitmap font of GD scaled to its `font-size`, at its `x`, `y` and `text-anchor`
* The blocks of a block written vertically, like the paragraphs of a `<div style="writing-mode: vertical-rl">`, are its columns, from the right, each as wide as its own, the block as wide as them
* The row of a flex container written vertically runs down and its column from the right, like the axes of its text, the container as wide as its columns
* Positioned elements are ordered inside their stacking context, like CSS: a positioned element of a z-index other than `auto`, or one of `opacity` below 1, a `filter`, a `mix-blend-mode`, a `transform` or `isolation: isolate`, orders the positioned elements inside it among themselves, a negative z-index over its background and under its text; a modal dialog and an open popover are over them all
* The columns of a grid written vertically run down and its rows from the right (or from the left for `vertical-lr`), its templates, placements, areas, gaps, flow and alignments turned likewise, the grid as wide as its rows; the text of its items has lines as long as their column when its length is set
* `<ruby>` draws its annotations (`<rt>`) on a row above the line of their bases, or below it with `ruby-position: under`, placed by `ruby-align`, each base as wide as its annotation and kept on one line; `<rb>` and `<rtc>` are bases and annotations of their own, `display: ruby-text` and the other ruby values are kept, and add `InlineFormat::$ruby`, `$rubyUnder` and `$rubyAlign`
* A multi-column container ends its columns where breaks are allowed: between blocks, nested ones too, and between the lines of a text block as CSS `orphans` and `widows` allow, `break-inside: avoid` and the edges of a box keeping what cannot be cut together, which makes the columns taller; with `box-decoration-break: clone` a cut box has its edges around each piece; the blocks of a cut box are placed in the column they are drawn in, and a block cut across columns is under the pointer in each of them (`WidgetRect::$fragments`); add `Style::withCloneDecoration()`, `InlineFormat::$orphans` and `$widows`
* Add `Widget\Text\Hyphenator` and `Widget\Text\HyphenatorRegistry`, and the `$hyphenators` argument of `CssStyleSheet`: CSS `hyphens: auto` breaks the words without soft hyphens where the patterns of their language (`lang`) do, those of English, French, German and Spanish bundled from hyph-utf8, others registered with `HyphenatorRegistry::register()`; a line breaks at a soft hyphen only with room for the hyphen; add `InlineFormat::$hyphenator`
* Lines of text are drawn in visual order by the Unicode Bidirectional Algorithm (UAX #9), with `ext-intl` or `symfony/polyfill-intl-char`: right-to-left text, like Arabic and Hebrew, runs from the right, numbers and mirrored brackets included; CSS `unicode-bidi` (`embed`, `isolate`, `bidi-override`, `isolate-override`, `plaintext`), `<bdi>`, `<bdo>` and `dir` set elements apart like the controls of Unicode, and the terminal is told not to reorder them again (BDSM); add `InlineFormat::$unicodeBidi`
* Inputs and textareas draw right-to-left text in visual order, each line in the direction of its first strong character like `unicode-bidi: plaintext`, the cursor with the character it is on
* Lines drawn in visual order have their Arabic letters in the forms they take in their words, the terminals joining letters in the order they are drawn; the terminals that reorder bidirectional text themselves (Terminal.app, Konsole, mlterm) get it as stored; add `Ansi\Bidi::setEnabled()` to decide otherwise
* A `<form>` submits the controls whose `form` attribute names it, before or after it, a submit button outside it included; add `FormSubmitEvent::getAction()` and `getMethod()` (`action`, `method`, and `formaction` and `formmethod` of the button first) and the `$action` and `$method` arguments of `FormWidget::submit()`; `method="dialog"` closes the dialog the form is in
* `<iframe src>` draws the document of its src, from a data: URL or through the loader of `ImageElement` registered for iframe, like the src of an image; `srcdoc` comes first
* The `<area>` elements of the `<map>` an `<img usemap>` names make its regions links, the cells whose center is in a rect, circle or poly (or the default) drawn with the link of the first area there, followed like the links of a text; add `ImageWidget::setAreas()`
* Add `ImageMapWidget` and `LinksInterface`: an image whose areas are links takes the focus, Tab and Shift+Tab move between its areas, the current one reversed with the cursor on it, Enter follows it with a `LinkEvent`, like `LinkedTextWidget`, which implements `LinksInterface` too
* `srcset`, on `<img>` and on the `<source>` of a `<picture>`, draws the candidate for the density of the terminal (`resolution`), the smallest one at least as dense, chosen again as it changes; width descriptors are densities of the px length of `sizes`, or of the width attribute; the candidates past the first are read when they are first drawn (`ImageWidget` takes a closure that reads its data)
* Add `MarqueeWidget`: the text of a `<marquee>` moves sideways on one line, scrolling, sliding or alternating, by its direction, scrollamount, scrolldelay and loop, and stays at its start with `prefers-reduced-motion`
* `hanging-punctuation: allow-end` (and `force-end`) lets a stop or a comma ending a line go over the right padding when only it would not fit; add `InlineFormat::$hangEnd`
* Make the control that Enter or Space activates match `:active` for a moment
* Add `CommandEvent`: a button with `commandfor` dispatches it on its target before the built-in command, which stopping it cancels, and for custom commands (`--name`)
* `column-fill: auto` fills the columns of a container of a set height one after the other instead of balancing them; add `Columns::$balance`
* The shapes of an `<svg>` take `fill`, `stroke`, `stroke-width`, `fill-opacity`, `stroke-opacity`, `fill-rule`, `opacity`, `display`, `visibility` and `color` from the rules of the page that match them, between their presentation attributes and their style attribute, `var()` taking the custom properties of what holds them
* The shapes of an `<svg>` are styled for the media of the terminal, and drawn again when it changes, like the color scheme; a change of media other than the size clears the render caches
* The stops of the gradients of an `<svg>` take `stop-color` and `stop-opacity`, its text `font-size` and `text-anchor`, from the style sheets of the page too
* Add `HtmlView::show()`, `requestClose()`, `getReturnValue()` and `setReturnValue()`, and `CloseEvent`: `close()` takes a return value, which the button submitting a form of method dialog gives, and dispatches a `CloseEvent`; Escape, a click outside a dialog of `closedby="any"` and `request-close` dispatch a `CancelEvent` first, which stopping keeps the dialog open
* Add `BeforeToggleEvent` and `ToggleEvent`, dispatched on a popover or a dialog before and after it is shown or hidden, stopping the first keeps it hidden, and `ToggleEvent` on a `CollapsibleWidget`, like a `<details>`, once it opens or closes
* `word-break: keep-all` keeps the letters of Chinese, Japanese and Korean words together, lines breaking at spaces and punctuation; add `InlineFormat::$keepAll`
* `text-emphasis`, `text-emphasis-style`, `text-emphasis-color` and `text-emphasis-position` draw their marks on a row over or under the line, each over a character but the spaces and the punctuation; add `InlineFormat::$emphasis`, `$emphasisUnder` and `$emphasisStyle`
* The strokes of an inline `<svg>` are drawn with their dashes, caps and joins: `stroke-dasharray`, `stroke-dashoffset`, `stroke-linecap`, `stroke-linejoin` and `stroke-miterlimit`
* The lines, polylines, polygons and paths of an inline `<svg>` are drawn with their `<marker>` elements (`marker`, `marker-start`, `marker-mid`, `marker-end`), and shapes with their `paint-order`
* Animations and transitions interpolate lists and functions of the same shape part by part (`padding: 0 1ch` to `1ch 3ch`, `translate()`, a shadow and its color, the stops of a gradient), and lengths of different units through `calc()`
* The marker of a list item is isolated from its text in the bidirectional algorithm, like the `unicode-bidi: isolate` of `::marker`
* Add the other predefined counter styles of CSS Counter Styles for `list-style-type`, `counter()` and `@counter-style` `extends`: `arabic-indic`, `persian`, `devanagari`, `bengali`, `thai` and the digits of the other scripts, `armenian`, `georgian`, `hebrew`, `hiragana`, `katakana` and their `-iroha` order, `cjk-earthly-branch`, `cjk-heavenly-stem`, `ethiopic-numeric`, and the Japanese, Korean and Chinese long forms
* The shapes and groups of an inline `<svg>` are clipped by `clip-path: url()` to a `<clipPath>`, with `clip-rule` and `clipPathUnits`
* The fills and strokes of an inline `<svg>` take the tiles of a `<pattern>`, with its units, `viewBox`, `href` and a `patternTransform` that moves or scales it
* Add `HtmlView::highlight()` and `removeHighlight()`: texts found across the inline elements of each text block, drawn in the styles of their `::highlight(name)` rules, like the CSS Custom Highlight API
* `HtmlView::navigate()` and links go to text fragments, like `#:~:text=start,end` or `#id:~:text=prefix-,text,-suffix`: the first match of each text is scrolled into view and drawn as `::target-text`
* The shapes and groups of an inline `<svg>` are faded by a `<mask>` (`mask: url()`), by its luminance or with `mask-type: alpha` its alpha, with its region, `maskUnits` and `maskContentUnits`
* The shapes and groups of an inline `<svg>` take `filter`: CSS filter functions, `blur()` and `drop-shadow()` included, or a `<filter>` of `feColorMatrix`, `feFlood`, `feOffset`, `feGaussianBlur`, `feDropShadow`, `feMerge` and `feComposite`
* `box-shadow` draws its spread, on the right and below, and its blur, as a softer cell of half blocks around the shadow
* Declarative shadow roots (`<template shadowrootmode>`) are drawn in their host, its children in their `<slot>` elements; the style sheets of a shadow tree style it alone, with `:host`, `:host()`, `:host-context()` and `::slotted()`, and the page reaches in through `::part()`
* Custom elements, like `<x-badge>`, are inline like any element, in the line of the text around them, unless they hold blocks, host a shadow tree or CSS displays them otherwise
* `<input type="file">` completes its path with Tab, like a shell: the entries of the directory typed in, the files of the types its `accept` attribute names
* The `@position-try` rules size the element they place by their `width` and `height`, lengths or `anchor-size()`, which it must fit at
* `exportparts` exposes the parts of a nested shadow tree to the tree around it, under their name or another, for `::part()`
* The `font` shorthand sets `font-size` and `font-family` too
* `contain: size`, `strict` and `inline-size` size an element by its `contain-intrinsic-size`, its content drawn in it
* The `size` and `face` attributes of `<font>` are presentational hints for `font-size` and `font-family`
* Draw the first shadow of a `text-shadow` around the text, moved by whole cells
* Take the geometry of the shapes of an `<svg>` (`x`, `y`, `width`, `height`, `cx`, `cy`, `r`, `rx`, `ry` and `d`) from the style sheets
* Add support for `text-justify`: `inter-word`, `inter-character` (also for `auto` on lines of wide characters without spaces) and `none`
* Add `shape-outside` (circle(), ellipse(), inset() with round corners, polygon() and the boxes) and `shape-margin`: the lines beside a float wrap around its shape row by row, with `Style::withFloatShape()` and `withShapeMargin()`
* Add translucent and `transparent` colors to gradients, interpolated premultiplied, blended over the background color of the box or what is behind it, which shows where they are transparent, with `Gradient::paintAt()` and `withBackdrop()`
* Add the `scripting` (none), `overflow-block` and `overflow-inline` (scroll), `device-width`, `device-height` and `device-aspect-ratio` (the terminal), `horizontal-viewport-segments` and `vertical-viewport-segments` (1), `device-posture` (continuous) and `environment-blending` (opaque) media features
* Add `:visited`, which matches the links followed and those given to `HtmlView::setVisitedLinks()` or `markVisited()`, `:link` matching the others
* Add `list-style-image`, and the `url()` of `list-style`: the image is the marker of the items, in half blocks, a row tall and from 1 to 4 columns wide
* Add `image-orientation` (a JPEG turned and flipped as its EXIF orientation says by default) and `image-rendering: pixelated` (images scaled to the nearest pixel), with `RasterImage::withRendering()`
* Add the poster of `<video>`, and the image of `<object data>` and `<embed src>`, drawn like an `<img>` over their fallback content
* Add images in the line of the text: an `<img>` inside a link or another inline element is drawn a row tall, wrapped like a word, with `RichTextWidget::setInlineImages()`
* Add `url()` images to `content`, on `::before`, `::after` and `::marker`, drawn in the line a row tall, with `RichTextWidget::setGeneratedImages()`
* Add SVG icons in the line of the text: an `<svg>` inside a link or another inline element is drawn a row tall, `currentColor` being the color of the text there
* Add the icons of buttons: an `<svg>` or `<img>` in a `<button>` is drawn in its line, in the color of the button, with `ButtonWidget::setInlineImages()`
* Add several background layers: every image and gradient of `background-image` and `background`, the first one over the others, each with its position, size, repeat and boxes, with `Style::withBackgroundLayers()`
* Add a `background-blend-mode` per background layer, each layer blended with the layers under it and the background color
* Add the image of `<input type="image">`, drawn in its button a row tall, its alt text its name
* Add `RasterImage::peekSize()`: the images of style sheets are read by their header, decoded when they are first drawn
* Add `shape-outside: url()` and `shape-image-threshold`: the lines beside a float wrap around the pixels of an image more opaque than the threshold, with `RasterImage::opaqueSpans()`
* Add `:paused`, which matches every `<video>` and `<audio>`, never played, and `:playing`, which matches none
* Add the `::before` and `::after` of flex rows, laid out as items of their own, before and after the children
* Add the `::before` and `::after` of grids, items placed like the others, by their grid-column and grid-row too
* Add the shadows of a `text-shadow` after the first, up to 4, the first one over the others
* Add clicks on the links of text drawn larger with the text sizing protocol, on the cells they take
* Add the `url()` images of the `::before` and `::after` of blocks, flex rows and grids, with `ContainerWidget::setGeneratedImages()`
* Add the rings of `box-shadow`: a shadow spread around the box, not moved nor blurred, like a focus ring, is a line hugging it, with the `ring` and `ring-thick` border patterns
* Add the marks of checkboxes and radios drawn by CSS too small for their border, like the `form-check-input` of Bootstrap: ☐ ☑ ○ ◉ in their colors, with `CheckboxWidget::setCompactMark()`
* Add the flex and grid layout of paragraphs, headings and list items: their text and inline elements are items
* Add the markers of list items drawn only while they are displayed as list items
* Add inline blocks with a border boxed in the line of the text around them, like buttons
* Add the inheritance of a side or a corner from the shorthand of the parent, like `border-top-left-radius: inherit` under `border-radius`
* Add the overlap of the borders of neighbors that a negative margin of less than a cell pulls together, their lines joined
* Add the growth of flex items from their percentage width, like CSS
* Add the look of buttons the page gives a background or no border: theirs, not the native one, like browsers
* Add the horizontal padding of `::before` and `::after`
* Add the static position of absolute and fixed elements without insets: where they would be in the flow
* Add the flow of the text around an inline absolute element, kept in one block
* Add the text under a block pulled over it by a negative margin, shown through its spaces on an inherited background too
* Add the CSS-wide keywords of custom properties: `initial` makes `var()` take its fallback, `unset` inherits
* Add the inset `box-shadow` spread over the whole box, like `inset 0 0 0 9999px`, drawn as a background over the background color
* Add the content width of `<hr>`: none, a box sized by its content not widened by its divider
* Add block elements displayed `inline-block`, `inline-flex` or `inline-grid` in every media and state: in the line of what is around them, as wide as their content
* Add SVG images to `url()` in `background-image`
* Add the join of the lines of boxes stacked one right under another, like `└` over `│` making `├`
* Add percentage widths inside a box sized by its content, auto while it is measured
* Add the transparency of the blank cells of absolute and fixed elements without a background: what is under them shows
* Add `Style::withConcealed()`, its text drawn as spaces, and the transparent color of `::placeholder` hiding it
* Fix the containing block of absolute elements: the padding box, inside the margin of the positioned ancestor
* Add the join of the lines of relative elements raised by their z-index with those under them
* Add a row for the content of a border-box height in CSS units whose border takes its rows while CSS leaves room for a line
* Add `grid-gap`, `grid-row-gap` and `grid-column-gap`, the old names of `gap`, `row-gap` and `column-gap`
* Fix the percentage width of absolute and fixed elements: of their containing block
* Add the transparency of the blank cells of absolute and fixed `::before` and `::after`
* Add `:before`, `:after`, `:first-line` and `:first-letter`, the pseudo-elements of CSS 2 with a single colon
* Fix a collapsed table whose rows are displayed as blocks: laid out like blocks, not drawn empty
* Fix the blank lines between the cells of a row displayed as a block
* Add the last row of children pulled into the bottom border of a box by a negative bottom margin, drawn over it, like an active tab opening into the line under the tabs
* Change the offsets, blur and spread of `box-shadow` outside a box to whole cells, from three quarters of one: a shadow of a pixel or two is none
* Add percentage heights with a length in `calc()`, like `calc(100% - 2rem)`, and the offsets of `Style::withRowsPercent()`
* Add SVG sources to `<img>`, and `VectorImage::fromString()`
* Change the box of an image without a width: as wide as the image is drawn, a height alone narrowing it by its proportions
* Fix a `line-height` length rounded up to whole rows before its blank rows were counted: 24px is a row and a half, none
* Add `NativeAppearanceInterface` to `EditorWidget`: a textarea with a border of CSS, or `appearance: none`, has no rules above and below its text
* Change a `min-height` length to rows from three quarters of one, like padding: `1.5rem` is a row
* Add `Style::withContentBasis()`: a flex item of a basis of auto, like `flex-grow: 1` or `flex: 1 1 auto`, grows from its content width instead of sharing the whole row
* Place an absolute element in an empty positioned ancestor, which draws no rows, where that ancestor would be in the flow
* Draw an absolute element opaque when it or a box in it paints a background, even of the color under it, like a dropdown menu over the text of the page
* Size an absolute element placed past its containing block, like at `left: 100%`, to at least its longest word, overflowing that block
* Draw a block of table rows as their table, like the anonymous table of CSS: `table { display: block; overflow-x: auto }` keeps its grid
* Narrow the columns of a collapsed table that is too wide down to their longest words, like those of a separate one
* Lay out the images of a flex container that cannot be drawn, their text alternatives, as flex items of their own
* Add `Style::withRowMaxColumns()`: a flex item that grows or has a basis, like `width: 0; flex: 1`, takes the share of its row past its width, its `max-width` bounding it
* Draw an inset `box-shadow` spread by a few pixels, not moved nor blurred, like the `ring-inset` of Tailwind, as a ring at the edge of the box
* Leave the items of a flex or grid container where `justify-content` places them under an inherited `text-align`
* Join the lines of the boxes of a row where they touch, like the items of a horizontal list group without a left border of their own
* Box an inline element displayed `inline-block` with a width in the line of the text around it, at that width, its background drawn even when it is empty
* Add `ColorInputWidget::getSwatchColor()`: the swatch of a color field is its prompt, in the box of the field, instead of a `TextWidget` beside it
* Draw a relatively positioned element after an absolute sibling over it, in document order, like the label of a divider over its line
* Draw the mark of a checkbox or a radio, ☑ or ◉, when it is sized narrower than its box, like `[x]`
* Measure a collapsed table as wide as it draws its columns and the lines between them, like for `width: max-content`
* Line up the box and the text of a label wrapping a checkbox or a radio that a page displays otherwise than as a row, like `inline-block`
* Keep the form controls an inline element other than a link holds, like a `<span>` around a field, in the line of the text around it
* Draw the `::before` of a list item starting with a box, and count the `::before` and `::after` items of a flex row in its width
* Keep the marker of a list item beside its `::before`, and draw the marker of an item starting with a box, like `<li><div>`
* Style the elements of a page of a class the default style sheet styles widgets of, like `columns` or `h4`, by the rules of the page only
* Add `GridTemplate::withRepeatLimit()`: `repeat(auto-fit, ...)` drops the tracks no item is in, the others sharing the row
* Put a blank row between the lines of a text from a `line-height` of 2 rows, so that 1.75, the spacing of much prose, is not double spacing
* Join the sides of a box without a bottom border to the top border of the box under it, like the items of an accordion
* Keep in the line a block element hidden by default and displayed `inline-block` in some media, like the items of a pagination
* Give a `::before` or an `::after` without a background its horizontal margins, like the separators of a breadcrumb
* Measure a grid sized by its content as wide as its columns side by side, the implicit `fr` columns of `grid-auto-columns` flexible like explicit ones
* Accept `calc()` in `translate()`, its percentages of the element mixed with lengths
* Keep the rounded corners of a box drawn over another, like a raised or focused button of a group, when their lines meet nothing new
* Accept a `mask` of a box other than an SVG `<mask>`, like the icons of VitePress, as drawing nothing instead of reporting it invalid
* Measure an element of a set width as that width, whatever its content, so that the box sized by its content around it makes room for it
* Resolve the percentage heights of an absolute or fixed element against its containing block, like `min-height: 100%` of a full-screen modal centering its box
* Open the list of a select given a border below that border and flush with it
* Place the items of a right-to-left flex row by the row, a narrower item no longer starting on the right like a block, which pushed the others away
* Draw the right-to-left labels of buttons and placeholders of fields in visual order, their Arabic letters joined, like text
* Open the list of a select whose text is on the right, like on a right-to-left page, on its right, its right-to-left options in visual order
* Stretch the items without content of a flex row of a set height to that height, like the bar of a progress holding only hidden text
* Measure the words a no-break space joins as one word, so that the column of a table keeps them on a line
* Keep a form control clipped to nothing, like `.sr-only`, focusable and toggled from the keyboard, drawn as nothing like `opacity: 0`
* Size a table without a width to its columns, like CSS, its border and caption around them instead of across the page
* Center a table by its `align` attribute, and float a table or an image by `align="left"` or `"right"`, like browsers
* Give the cells of a table its `border`, `cellpadding` and `cellspacing` attributes, a bordered table without spacing drawn as one grid, and size an `<hr>` by its `width` and `align`
* Measure the cells of a table at their widest before sharing the room, so that a table too wide fills its room, and cut its columns past their longest words in proportion to those words
* Float an inline element, like the `<span class="float-end">` of Bootstrap, at the side of its line, the text around it flowing beside it
* Leave blank a side of a border whose color is transparent, like `border-bottom: 2px solid transparent` or `border-right-color: transparent`, keeping its room, not only a border transparent all around
* Convert the absolute lengths (`pt`, `pc`, `in`, `cm`, `mm`, `Q`) to cells as their CSS pixels, 96 an inch
* Grow a table cell to its content past its `height`, a least height like browsers, whatever its `max-height` says
* Center the blocks narrower than a `<center>` or a `<div align="center">` in it, like a table of a width of 85%, like browsers
* Lay out a page without a doctype, or with an old one, in quirks mode, like browsers: its tables do not inherit the alignment and the font of the text around them
* Lay out a block in an inline element as a block, like a `<div>` of a card in a link, the text before and after it on lines of their own, like CSS; the text blocks of a link laid out as a block are that link, the first one taking the focus
* Draw text decorations, like the underline of a link, under the text only, like CSS: not under the border, the padding or the rest of the line of a box; a container passes them on to the text blocks inside
* Ignore the newline before `</pre>` of a block of code scrolled sideways (`overflow: auto`) too, like browsers, and in an element ending it too, like `<pre><code>…\n</code></pre>`; and no line after a `<br>` ending the text of a block
* Lay out a block element displayed `inline` by the rules of every media and state in the line of the text around it, like the `<li>` of a breadcrumb or a menu
* Break no line at the padding or the margin of an inline element, like CSS: the text breaks at its spaces only, a comma kept with the `<code>` before it
* Size a table to the longest words of its captions, like CSS: a caption longer than its table wraps in it, its words whole, and a figure displayed as a table, like a thumbnail of Wikipedia, is not as wide as its caption; accept `display: table-caption`
* Size a float of a percentage width, like `float: left; width: 25%`, to that share of the block it is in, not of its content
* Pull a block by a negative margin of less than a cell, like `margin-bottom: -1px`, over a border only when a border of its own joins it there, not its text over the line
* Draw a ring of `box-shadow` spread inside a box, like `inset 0 0 0 1px`, along its edge cells, not over the text above and below it; around a box of less than three rows, like a field
* Draw the line of a ring over a row of text after that text only, not between its words, like the feedback under a field
* Give an absolute or fixed element with both `left` and `right` its `width`, from its left inset, like CSS, not the room between the insets
* Keep the width of an image that is not drawn, without a text alternative and less than a row tall, like a spacer image `<img src="s.gif" width="40" height="1">` indenting the replies of Hacker News
* Lay out an inline element displayed `inline-flex` in a column as a box in the line, its `::before` and `::after` items of their own, like an arrow over a score
* Align the controls and boxes in a line of text by its `text-align`, like a button alone in a centered block or the buttons of a footer aligned right
* Give no line nor item to a `::before` or `::after` of invisible characters only, like a zero width space
* Accept `width: 0%`, like `width: 0`, and `min-width: max-content` and `fit-content`, like `auto`; `height: fit-content`, `max-content` and `min-content`, like `auto`
* Accept a percentage and a length as the position of a stop of a gradient, like `calc(100% - 2rem)`
* Keep the room of an empty `::before` or `::after` of a width in a flex row, like an icon drawn by a mask, and draw no background of a box masked by an image (`mask-image: url()`), its shape not drawn
* Draw the captions of a table whose borders are separate, or without rows, outside its border and background, as wide as its box, like CSS
* Draw what floats at the right of a `<summary>` at the end of its line, and none of its `::before` or `::after` of no size and clipped; an absolute `::after` at the end of the line; none of `display: none`, an open `<details>` showing its content alone
* Add `HtmlBuilder::setScripting()`: scripts not running by default, the content of `<noscript>` is drawn and its style sheets apply, like in a browser without scripts; left out once they run
* Move the focus off a control hidden once styled to the first control that is no text with links, a skip link shown on focus staying hidden
* Draw an inline block padded on its sides alone in its parent as a box, its lines wrapped inside its padding and its vertical padding taking rows
* Join a line across a box touching its side borders with them, │ and ─ making ├ and ┤
* Leave out an image not drawn without a text alternative, its border drawn around nothing no more; the captions of a table without rows drawn outside its border like those of one with rows
* Set the cells of a separate table as far apart across as `border-spacing` says, 0 making their borders touch, and share the width a length gives a table between its columns
* Draw nothing of an element clipped by `clip-path: rect()` without width or height, like `rect(0 0 0 0)`
* Draw a right float after text on the last line of that text when it leaves room, like a browser, not below it
* Draw the shape of a `mask-image` over a background color in that color, stretched to the box, like an icon masking `currentColor`
* Cut the text of a `<div>` with its `text-overflow`, and clamp it with its `line-clamp` when it holds only text, like that of a `<p>`
* Give an element clipping or scrolling sideways no automatic minimum width in a flex row, like CSS: it shrinks past its content before the items beside it
* Keep a value substituted for `var()` apart from the word or number beside it, like CSS substitutes tokens: `var(--a)var(--b)` of a minified style sheet is two values
* Read a style sheet of escaped quotes and parentheses in its selectors, like the `.before\:content-\['—'\]` of Tailwind, and of quoted spans hundreds of kilobytes long, which made reading it fail
* Keep an inline block whose border has no width, like the `border: 0 solid` of the preflight of Tailwind, in the line of its text, not boxed on its own
* Read a translation whose length holds a function of several arguments, like `translateX(calc(0px - min(300px, 80vw)))`, moving the sidebar of mdBook out of view
* Read the hex escapes of selectors ending at the whitespace after them, like `\2c` for a comma in a class of Tailwind, and `calc()` of a single `min()`, `max()` or `clamp()` in sizes
* Draw nothing of a positioned element with `visibility: hidden` or `opacity: 0` over what is under it, its blank cells no longer covering the text
* Collapse the margins of the first and last children of a block through it, like CSS, when it has no border nor padding there, like a `<p>` in a `<li>`: one line between the items of a list of paragraphs, not two
* Add `HtmlBuilder::setDefaultStyles()` and the default styles of documents after the default style sheets of browsers (`Resources/default.css`): a line between paragraphs, lists, quotes, figures and blocks of code, the headings spaced like in browsers, lists in lists not apart; on by default, left out for an interface laid out by its own rules
* Take the border of an inline block a later rule takes away as none, like a `border-width: 0` after a `border`, and a rule of `@media screen` as one of every media when telling what is in the line of the text
* Lay a block displayed `inline-block` that holds text only, without a border, in the line of the text around it like an inline element displayed so, not as a box of a row, like the items of an inline list
* Support the CSS `ex`, `rex`, `rch` and `rlh` units, a column like `ch` (or a row like `lh`)
* Collapse the margins of blocks one after the other in a block holding floats too, unless a float or a clearance comes between them
* Draw a relative element after an absolute or fixed sibling, and the absolute elements, down a page taller than the screen; only fixed elements are cut to it
* Box an inline block whose border color has spaces in it, like `rgb(0 0 0 / 10%)`, and take `@media not print` as every media when telling what is in the line of the text
* Keep the words of an inline block in the text together, wrapping it as one box like browsers: a line can break before and after it, and one wider than the line wraps at its spaces on lines of its own; words joined by no-break spaces too wide for a line break at those spaces rather than inside a word
* Split a list of selectors at its commas only, an escaped quote or bracket in it being no string nor group, like the arbitrary values of Tailwind
* Leave out of the line of the text an absolute or fixed `::before` or `::after` of an inline element that is transparent or hidden, or moved by a negative left margin wholly before its place, like the anchor marks of headings until hovered
* Collapse the margins of blocks around a float between them, the float beside the text after it, and put a right float after a paragraph under its margin, not on it
* Put a left float after text at the start of the last line of that text when there is room, that line moved beside it, not below it
* Take a rule of a min-width media query met from 80 columns, like `@media (min-width: 601px)`, as one of every media when telling what is in the line of the text, which is decided before the size of the screen is known
* Draw an empty `::before`, `::after` or element whose borders make a triangle, one side of a color and one beside it transparent, as a glyph pointing away from the colored side (▾ ▴ ▸ ◂), like the caret of a dropdown of Bootstrap
* Draw the strokes of an icon masking a color, of two cells or more, in the cells they cross when none covers half a cell, instead of nothing
* Ignore the byte order mark starting a style sheet, which made its first rule match nothing or drop with an `@import` before it
* Measure the least width of text of `word-break: break-all` as a letter, so that it wraps in a scroll container instead of scrolling
* Show the line that a negative `text-indent` pulls out further than its block is wide past the pull, like the hidden text of a link replaced by a logo, instead of a letter a line
* Keep the first word of a line that `text-indent` pushes in as far as its block is wide out of sight, instead of on the next line
* Support `grid-template-columns: subgrid`: the columns of the parent grid it spans, sized by the content of its items, those of subgrids in it too
* Draw a grid item over the one before it in the same area, its blank cells letting it through, instead of hiding it
* Accept a transparent color, like `#0000`, as a value of a property registered with the syntax `<color>`
* Let an absolute box without a background show what is under it when only a positioned child of it has one
* Substitute nothing for a `var()` of an empty fallback, like `var(--x,)`, instead of making the value invalid
* Match the selectors of the page against the classes of an element only, not those Tui gives the widget of a `<p>`, a heading or an `<hr>`, which `[class]` or `.p` matched
* Paint grid items over each other by their `z-index`, then in the order of the document, instead of from left to right
* Ignore the `flex-direction` and `flex-flow` of an element of a document that is not a flex container, instead of laying its blocks out in a row
* Honor the `z-index` of a grid item that is not positioned when painting the items that overlap
* Keep the selectors of the page from matching the classes a `<picture>` chose its source by, which `img:not([class])` missed the image of
* Take the fallback of a `var()` as all that comes after its first comma, as written, like the comma of `var(--a,,)`
* Make each inline element of an element of `display: inherit` in a flex or grid container an item of its own, like in that container
* Draw an empty `::before` or `::after` smaller than a cell, round and of a background color, as a dot of that color, like the bullets of VitePress, and an absolute one as its triangle or dot too
* Keep the text and inline elements of an element that is a flex container in some media only in one line in the others, instead of a line for each inline element
* Let a `grid-row`, `grid-column` or `grid-area` set the lines declared before it, like `grid-row: auto` after a `grid-row-start: 2`, instead of the longhands always winning over it
* Line up a block that is `inline-block` in some media only with the content around it, on a line of its own where it is a block, instead of stacking it on every screen
* Draw an empty `::before` or `::after` smaller than a cell as a bar or a small square when it is not round, like the separators of a footer of GitHub, and an absolute one of a size and a background as its box
* Let the `font` shorthand reset the `font-style`, `font-weight`, `font-variant` and `line-height` the children of an element inherit, instead of keeping a bold declared before it on their text
* Leave the glyph of an empty `inline-block` `::before` or `::after`, like a caret drawn by its borders, out of the underline of the text it is in
* Leave a column between the controls and inline boxes lined up in a line of text only where the source has a space between them, dropped where the line wraps, instead of one between all of them
* Keep the width of a block that is `inline-block` in some media only where it is a block, on a line of its own, instead of stretching it over the line
* Wrap the controls and inline boxes written together in a line of text together, instead of breaking the line between them
* Keep the classes of an element in the order of its `class` attribute for the selectors that read it, like `[class^="col-"]`, when Tui keeps some of them on the element
* Line up a block that some media display `inline` with the content around it, like the `inline-block` ones, instead of stacking it
* Drop the invalid colors and keywords of a style sheet or a style attribute when parsing them, the declaration before them applying, instead of letting an invalid value override a valid one
* Line up an inline element that some media display as a block with the content around it where it is inline, instead of a block on every screen
* Draw a relative element moved past the lines of its container over what comes after them, instead of cutting it
* Lay the cells of an element displayed as a table without rows out side by side in an anonymous row, like browsers, and keep a column of a set width at it in a table wider than its content
* Leave the focus of a page to its first Tab or an `autofocus` element, like browsers, instead of focusing its first control from the start
* Draw inline `code`, `kbd` and `samp` in the colors the page gives them, instead of in reverse video over them
* Let the content of a page go on past the height of its `<html>` and `<body>`, like a `height: 100%`, instead of cutting the page at the height of the screen
* Place a float too wide for the room beside the other floats below them, instead of squeezing it
* Put the lines of a text below the floats where the room beside them is narrower than its words, instead of a letter on each row
* Let the floats of a block holding nothing else out of it, beside the floats that follow, unless the block keeps them, like a clearfix or a flow root
* Leave a column between checkboxes or radios one after the other, written together too
* Lay a subgrid of rows (`grid-template-rows: subgrid`) out in the rows of its parent grid it spans, like cards side by side whose titles, texts and links line up
* Keep a growing flex item as wide as its min-content, its siblings narrower down to theirs, where a browser overflows the row, not its words broken
* Leave out a triangle or dot drawn in the top padding of its element when that padding rounds to no row, not over its text
* Place a float of a percentage width against the one before it, and below the others when it misses the room by more than its rounding
* Place an absolute element in a positioned box narrower than its row from that box, not from the row
* Draw the triangle of an absolute `::before` or `::after` of a box holding blocks once, not in its line too
* Draw the content of an absolute box taller than its insets make it past them, unless it clips or scrolls
* Let the content of a page go on past the max-height of its `<html>` and `<body>`, not cut to the screen
* Lay a child of a width set wider than a box that clips sideways (`overflow-x: hidden` or `clip`) out at that width, cut at the side of the box
* Place each line of a wrapping row in the room it leaves for `justify-content: center` and `end`, not all of them by the longest
* Take a closed `<dialog>` out of the flow, one a page displays anyway, hidden, taking no room
* Keep a flex item of `flex: none` at its width, its basis, not at the width of its content
* Draw the text of a button that a rule of the page hides and another displays again in every media, not leave it out
* Ellipsize the controls and scale down the images of a row too narrow for the min-content of its items before breaking a word
* Align each line of text and inline boxes centered or at the right in the room it leaves once drawn, not all of them by the longest
* Keep the `filter: invert(1)` a page writes on inline code of a color of its own, and take `invert(1.0)` as a full invert
* Put the padding and the border of a subgrid of rows above its first row and below its last, in the rows of its parent
* Draw the text of a button as the cascade displays its parts in the media and the state of the button, following each resize
* Leave out the auto margins of the items of a line of inline content, not pushed to the side
* Let a float go on past the plain block it is in, like a paragraph starting with a floated image, the blocks after it flowing beside it, instead of making the block as tall as the float
* Start an element whose `::before` is a block that clears below the floats
* Let a float go on past a block of a padding or a border too, from its content edge
* Draw the text of a button as the states of its ancestors display its parts too, like `.card:hover .label`
* Add `HtmlView::unmount()`, `Tui::removeListener()`, `Tui::removeStyleProvider()` and `StyleSheet::removeProvider()`: a page unmounted, or replaced by the next page of the whole screen mounted, nothing of it is kept
* Clear the `::before` of a block among floats, not the block: its border and background beside the floats, its content below them
* Clear the `::before` of a text among floats too, its lines below them in its box beside them
* Lay a block that fits out at the width of the box that clips or scrolls a wider sibling sideways, centered in the box
* Draw the content of an absolute box taller than the height it sets as a share of its containing block past it
* Draw a rule beside floats under them, like the border of a block, and the marker of a list item after the float it starts with, not in its box
* Draw the text of an inline `<svg>` half a row to two and a half rows high as characters over its cells, like the labels of a diagram, and honor its `dominant-baseline: central`
* Draw the lines of an inline `<svg>` of 16 columns by 4 rows or larger a pixel of its cells wide at least, like browsers draw lines a device pixel wide at least
* Add `HtmlView::navigate()` and `getTarget()`, `Tui::reveal()` and `Tui::getFragmentLinkAt()`: a click on a link to a fragment of the page (`<a href="#id">`) goes there, the element matching `:target`, scrolled into view and focused when it takes the focus
* Add `Style\AutoMargin` and `Style::withAutoMargin()`: auto margins take the free columns of a row, or center a block narrowed by `maxColumns` or a percentage width
* Add `Style::withMinColumns()` and `Style::withOrder()`, from CSS `min-width` and `order`
* Add `VerticalAlign::Stretch` to stretch the children of a row to its height, the default of CSS flex rows
* Add `Style::withAlignSelf()`, `Style::withWrap()` (with the gap between wrapped lines) and `Style::withReverseOrder()`, from CSS `align-self`, `flex-wrap` and the reverse flex directions
* Add `Style::withFlexBasis()` and `Style::withInvisible()`, from CSS `flex-basis` and `visibility: hidden`
* Widgets hidden by their style, or inside a hidden widget, leave the focus cycle
* Support CSS counters (`counter-reset`, `counter-increment`, `counter-set`, `counter()`, `counters()`) in `Css\CssStyleSheet`
* Support cascade layers (`@layer`, `@import … layer()`) in `Css\CssStyleSheet`, with `CssStyleSheet::addUserAgentStyles()` so that the user agent styles come before every author style
* Add `Style::withFlexBasisPercent()` and `Style::withRowsPercent()` (heights as percentages of a parent of definite height), and collapse vertical margins in containers without a direction, between siblings and between a parent and its first or last child, like CSS block layout
* Add the `$indent` argument to `Text\InlineFormat`, from CSS `text-indent`
* Add `Style\LayoutAwareStyleProviderInterface`: after a frame is laid out, a provider can restyle from the widget sizes, and the frame is rendered once more; `Css\CssStyleSheet` uses it for `@container` queries
* Add `MediaContext::withPointer()` and `MediaContext::withColors()`, for the `hover`, `pointer`, `color` and `color-index` media features; the palette defaults from `COLORTERM` and `TERM`
* Add the `group` item key to `SelectListWidget`: the first item of a group carries its heading, styled by the `group` sub-element
* Add `Style\TableRole`, `Style::withTableRole()`, `Style::withColumnSpan()` and `Style::withRowSpan()`: the columns of a table are sized across its rows, from CSS `display: table`, `table-row-group`, `table-row` and `table-cell`
* Fix the tracked rects of a subtree a layout rendered without tracking positions, which kept the ones of an earlier frame
* Add `Style\UnderlineStyle` and `Style::withUnderlineStyle()` for styled and colored underlines, from CSS `text-decoration-style` and `text-decoration-color`
* Add `HtmlView::cursorAt()` and `CssStyleSheet::getComputedValue()`; support CSS `pointer-events`, `cursor` and `accent-color`
* Map `<dialog>` (shown when open) and exclusive `<details name>` accordions; the `inert` attribute keeps a subtree out of the focus cycle and the hit tests
* Add the `$lineGap`, `$letterSpacing` and `$script` arguments to `Text\InlineFormat`, from CSS `line-height`, `letter-spacing` and `vertical-align: super|sub`
* Add `FormWidget` and `FormSubmitEvent`: a `<form>` is submitted by its submit buttons or Enter in a field, after constraint validation, with the values of its named controls
* Add the `$dispatch` argument to `InputWidget::setValue()`, and `SelectListWidget::getItems()` and `SelectListWidget::getSelectedIndex()`
* Support CSS `color-scheme`, which forces the scheme of `light-dark()` for a subtree
* Add `InputWidget::setMasked()`, for password fields, and `SliderWidget`, a value picked on a track like `<input type="range">`
* Add `InputWidget::setMaxLength()` and `InputWidget::setSuggestions()` (completed with Tab or Right), and `EditorWidget::setPlaceholder()`, `EditorWidget::setReadOnly()` and `EditorWidget::setMaxLength()`
* Add `Style\GridTemplate` and `Style::withGridTemplate()`: a grid lays its children out in rows of its column tracks (lengths, percentages, `fr`, `auto`, `minmax()`, `repeat()` and `auto-fill`), from CSS `display: grid`
* Add `Style\GridPlacement` and `Style::withGridPlacement()`: grid items go to a named area of `grid-template-areas` or to explicit lines (`grid-column`, `grid-row`, `grid-area`, from 1 or from the end), span rows, and fill the free cells otherwise; `grid-template-rows` sets the least height of each row; lines can be named (`[content-start]` in the templates, `GridTemplate::line()`), the edges of an area being `area-start` and `area-end`
* Add `Style\Scroll`, `Style::withScroll()` and `ContainerWidget::scrollTo()`, `scrollBy()`, `getScrollTop()`, `getScrollHeight()` and `getClientHeight()`: a container of bounded height with CSS `overflow: auto` or `scroll` shows a window of its content with a scrollbar (`scrollbar-width`, `scrollbar-color`), scrolls a widget into view when it gets the focus, and `HtmlView::scrollAt()` scrolls the container under the pointer like a mouse wheel; Page Up and Page Down scroll the one around the focus, unless the focused widget uses them
* Add `Style::withStickyTop()`, from CSS `position: sticky` with `top`: in a scroll container, the widget stays that many rows below the top while its parent is in view
* Add `BorderPattern::HEAVY`, `DASHED` and `DOTTED`, from CSS `thick`, `dashed` and `dotted` borders, and `Border::withSideColors()` and `withWidths()`: `border-color` takes up to four colors and `border-*-color`, `border-*-width` and `border-*-style` set one side
* Add `TextAlign::Justify`; text blocks align each of their lines, like CSS `text-align`, and justify all but the last line of a paragraph
* Add `ImageWidget`, which draws PNG, JPEG, GIF and WebP images with half blocks in true colors (with the GD extension); HTML `<img>` uses it for `data:` URLs and, given a loader to `ImageElement`, for other sources, with `width` and `height` in CSS pixels, and `<picture>` draws its `<img>`
* Add `ScreenWriter::setColors()`: on a terminal with 256 or 16 colors (`MediaContext::$colors`, from `COLORTERM` and `TERM` by default), true colors become the closest palette or basic color when written, so CSS colors and images keep their look
* Add `TableRole::Column`, from CSS `display: table-column` and `table-column-group`: HTML `<col>` and `<colgroup>` give the columns they span a least width
* Lines wrap at zero-width spaces, like HTML `<wbr>`; `<bdo dir="rtl">` draws its text right to left, and `<ruby>` puts its annotation in parentheses after its base
* CSS colors with an alpha channel (`rgba()`, `hsl(... / 50%)`, `#rgba`, `#rrggbbaa`) are blended over the closest background around the element, or the terminal background of the color scheme; `transparent` text takes the color behind it. `DeclarationMapper::map()` takes that backdrop
* Add `Style\Position`, `PositionMode` and `Style::withPosition()`, from CSS `position: relative`, `absolute` and `fixed` with `top`, `right`, `bottom`, `left`, `inset` and `z-index`: absolute and fixed widgets leave the flow and are drawn over the frame at their insets from their containing block, centered by auto margins between two insets
* Add `HtmlView::showModal()` and `close()`: a modal `<dialog>` is centered over the page (`:modal`), which is inert until Escape or `close()` closes it
* Lines wrap at soft hyphens, showing a hyphen there; text blocks take CSS `line-clamp` (and `-webkit-line-clamp`), `word-break: break-all`, `hyphens`, `text-wrap` (`nowrap`, `balance`) and `tab-size`
* Add `Style\Columns`, `Style::withColumns()` and `withAvoidBreak()`, from CSS `columns`, `column-count`, `column-width`, `column-gap`, `column-rule` and `break-inside: avoid`: the content of a container flows into balanced columns
* Add `Style\Shadow`, `Style::withShadow()` and `Color::isThemed()`: CSS `box-shadow` draws a drop shadow inside the margin; `opacity` blends the colors of the element over what is behind; a modal dialog lays its `::backdrop` background over what is under it, blending every color toward it (`Style::withTranslucentBackground()` keeps a translucent background as declared), or dims it with `opacity` alone
* `position: relative` moves a widget by its offsets within its parent, its room kept; add `Style::withSpanAll()`, from CSS `column-span: all`: a child spans the columns of its multi-column parent
* Add CSS animations to `CssStyleSheet`: `@keyframes` and `animation` (name, duration, timing, delay, iterations, direction, fill mode, play state); colors and numbers are interpolated, `HtmlView` draws frames while animations run, `CssStyleSheet::isAnimating()` and `setAnimationHandlers()` let other hosts do it
* Add CSS transitions: `transition` and its longhands make a change of color or number go from the old value to the new one, like on `:hover` or `:focus`; `@starting-style` gives the values they start from when an element is shown, first or again after `display: none`, so a popover fades in
* Add `DropdownWidget`: HTML `<select>` without `size` or `multiple` shows its choice on one line and opens its list over what follows, like in a browser
* Add `Style::getWidthOffset()` and `resolveWidthPercent()`, and an offset to `withWidthPercent()`: CSS `width` and `max-width` take `calc()` mixing percentages and lengths, like `calc(100% - 20ch)`
* Add `ComboboxWidget`: HTML `<input list>` lists the `<datalist>` options containing what is typed below the input, Up and Down to move, Enter to take one, Down or Alt+Down to open it, all of the options when none contains the value; a number field keeps stepping with Up and Down, Alt+Down opens its list; a length `width` is the flex basis of a flex item, like CSS
* Support CSS `direction` and HTML `dir` (`rtl`, `ltr`, `auto`): right to left, logical sides are the right and left ones, rows run from the right, and text and narrower blocks start on the right; add the logical `margin-*`, `padding-*` and `inset-*` longhands
* Add `Style::withCollapse()` and `withCaptionBottom()`, from CSS `border-collapse: collapse` and `caption-side`: a table and its cells are drawn as one grid of lines with junctions, as wide as its columns; cells span rows and columns, the lines and junctions following
* Right to left, grids run from the right; Down opens a `DropdownWidget`, whose field shows the choice until another one is taken
* Add `Style::withOverline()`, from CSS `text-decoration: overline` (SGR 53); text blocks take `font-variant: small-caps` and `text-transform: full-width`, and CSS `transform: translate()` and `translate` move an element like relative offsets
* Inline elements draw their left and right padding (in their colors), margins and borders, like CSS: tags and keys look like pills and boxes in text
* Add `Style::withJustifySelf()`, `withJustifyItems()` and `withContents()`, and `GridTemplate::$autoRows` and `$dense`: CSS `justify-self`, `justify-items`, `place-self`, `grid-auto-rows`, `grid-auto-flow: dense`, `display: contents` and the logical sizes (`inline-size`, `block-size`...); a width or height stays within its minimum and maximum whatever their order
* Add `ReplacedElementInterface`, `Style::withAspectRatio()`, `withObjectFit()` and `withObjectPosition()`: CSS `aspect-ratio` gives a box its height from its width, an image takes the width and height its style sets, filling them like `object-fit` says, placed like `object-position` says
* Add `Style::withFloat()` and `withClear()`, from CSS `float` and `clear`: in a block container, a floated child is at the left or right, the blocks starting beside it laid out in the room left
* Add `ColorInputWidget` and `CompositeControlInterface`: HTML `<input type="color">` is a #rrggbb field with a swatch of its color, `<input type="file">` a path to type in
* Add CSS `@scope`, with a root and a limit, or scoped to the element of the `<style>` it is in: the styles of a component stay in it; `CssStyleSheet::add()` takes that root
* Add `HtmlView::showPopover()`, `hidePopover()`, `togglePopover()` and `invoke()`: the HTML `popover` attribute, `popovertarget` and `commandfor` buttons open and close popovers and dialogs, an auto popover closing on Escape or a click outside
* Add `Style::withAnchorName()` and the anchor of `Style\Position`, from CSS anchor positioning: `anchor-name`, `position-anchor`, `position-area`, `anchor()` in the insets and `position-try-fallbacks: flip-block | flip-inline` place an absolute or fixed element next to another, a popover under its button; add `Tui::setImplicitAnchor()`: the button that opens a popover is its anchor when its style names none
* Add the `$firstLetter` argument to `Text\InlineFormat`, from CSS `::first-letter`; support CSS `quotes`, `:defined`, and `outline` on bordered elements
 * Restore the widget style after a sub-element styled with `AbstractWidget::applyElement()`, instead of leaving the terminal defaults for the rest of the line
 * Support the CSS named colors, `currentcolor`, `calc()`, `min()`, `max()` and `clamp()`, nesting, `@supports`, `:lang()`, `:dir()`, `:scope` and `:any-link` in `Css\CssStyleSheet`
 * Add `Compatibility\CompatibilityReport` to list the HTML and CSS features the registries support
 * Add `Tui::getWidgetRect()` to know where a widget was drawn, so pointer events can be placed
 * Add `CollapsibleWidget` for collapsible detail/summary panels
 * Add `AbstractWidget::off()` to remove listeners registered with `AbstractWidget::on()`
 * Add `AnsiUtils::walkCells()` to iterate the cells and escape sequences of a rendered line
 * Add `AnsiUtils::sliceToWidth()` to extract a fixed-width range of columns from a line
 * Add `AbstractWidget::postRender()` to post-process a widget's finished lines, chrome included
 * Make `LoopClock` part of the public API
 * Dispatch paste lifecycle events through the terminal event dispatcher
 * Add `AbstractWidget::attachChild()` and `AbstractWidget::detachChild()` to wire child widgets
 * Add multi-select support to `SelectListWidget`
 * Make `SelectListWidget` wrap option labels and descriptions in aligned columns, and vertically expandable
 * Add `TabsWidget`
 * Add `$multiselect` as the third argument of `SelectListWidget::__construct()`, moving `$keybindings` to fourth position
 * Add `KeyBindingWidget` to display the keybindings of the focused widget
 * Add the `super`, `hyper` and `meta` modifiers to key ids, and `Key::super()`
 * Make a key id naming an unknown modifier match nothing instead of the bare key
 * Make `Keybindings` throw an `InvalidArgumentException` for a key id that has no key, names an unknown modifier or an unknown key

8.1
---

 * Introduce the component as experimental
