HTML and CSS compatibility
==========================

TuiHtml draws HTML and CSS in a terminal: a grid of character cells, each with one character, one foreground and one
background color, and a few attributes (bold, dim, italic, underline...). It follows the HTML and CSS specifications
where a cell grid can, and documents where it cannot. It is not a browser and does not aim to draw pages like one.

Support levels
--------------

`CompatibilityReport::createDefault()` lists each HTML element and attribute, CSS property, value type, selector and
at-rule TuiHtml knows, keyed like [MDN browser-compat-data](https://github.com/mdn/browser-compat-data), with one of
four levels and a note. `Resources/compatibility.json` is that report for a default build.

| Level | Meaning |
|---|---|
| `supported` | Behaves as specified, within the limits of a cell grid (see below) |
| `partial` | Some values or behaviors only: the note says which, and what differs |
| `ignored` | Accepted and without effect, having no meaning in a terminal, like `user-select` or `@font-face` |
| `unsupported` | Everything the report does not list; a document using it is reported to the PSR-3 logger |

Give `HtmlBuilder` and `CssStyleSheet` a logger to hear about the unsupported features a document uses, once for each
style sheet, or make `CssStyleSheet` strict to throw instead. Applications registering their own elements report their
support with `ElementRegistry::register()`.

What a cell grid changes
------------------------

These apply to every feature, including the ones reported as `supported`:

 * **Lengths are in cells.** A column is a character wide; a row is about twice as tall as a column is wide. `px`
   and the other absolute units are converted with the cell size of the terminal (8 by 16 pixels by default, see
   `MediaContext::withCellSize()`) and rounded to whole cells: a `1px` border is a line of box-drawing characters, a margin of
   `0.3em` may be no cell at all. `ch` is a column, a row is a `lh`.
 * **Content past a height** set in rows (a number or `lh`) is drawn past it, like `overflow: visible`. A height in
   another unit, like pixels, ems or `vh`, rounded to rows, clips its content instead: rounded down, it would
   overflow where a browser fits it.
 * **The terminal font draws all text.** Fonts cannot be loaded (`@font-face` is ignored) and text has one size:
   headings are bold, not larger, unless the terminal supports the text sizing protocol of kitty and the application
   says so with `MediaContext::withTextSizing()`. `font-family` only selects FIGlet fonts for large text.
 * **A cell has one color.** Translucent colors, `opacity` and blend modes are blended into the cell; gradients and
   shadows are painted cell by cell. Colors are reduced to 256 or 16 when the terminal supports no more (`COLORTERM`
   and `TERM`, or `MediaContext::withColors()`), and turned off by `NO_COLOR`.
 * **Images are drawn with half blocks** (`▀`), two pixels a cell, in true colors, with the GD extension. Terminal
   image protocols (kitty, sixel, iTerm2) are not used. Without GD, images and SVG show their text alternative.
 * **Character widths come from Unicode**, not from the terminal font: East Asian wide characters take two columns,
   combining marks none. Terminals disagree on some characters (emoji with variation selectors, flags, ambiguous
   width characters), which may shift what follows them by a column there.
 * **Bidirectional text** (Arabic, Hebrew) is drawn in visual order with the intl extension; without it, in logical
   order. Terminals that reorder text themselves are detected. Arabic letters are shaped by the terminal, if at all.
 * **Pointers and keys.** Hover, clicks and the wheel need a terminal that reports the mouse and an application that
   tracks it (`MediaContext::withPointer()`); controls, links and dialogs are used with the keyboard too.

Layout
------

Block, inline, flex, grid, table, multi-column and positioned layouts are implemented, and nest in each other: a grid
item can be a flex container holding a table. Their interactions are covered by the fixtures of `Tests/Fixtures/Compat`
and the layout tests, rendered at several widths. Known differences from CSS:

 * inline boxes are centered on the line, not aligned on a baseline: `vertical-align: baseline` is like `top` on
   table cells, and inline elements stay on the line;
 * floats, margin collapsing and percentages of the containing block are laid out like CSS, rounded to cells; the
   notes of `float`, `margin` and `width` say where they differ;
 * `transform` moves elements by `translate()` only; `scale()`, `rotate()`, `skew()` and `perspective()` are accepted
   and change nothing;
 * a box holding only text, an item a flex column grows, and the content CSS draws above a box (like
   `justify-content: end` in a too short column) are clipped rather than drawn past it.

Not supported
-------------

 * Scripts and `on*` attributes never run; `<canvas>`, `<video>` and `<audio>` show their fallback content or poster.
 * Nothing is loaded from the network or the filesystem by default: style sheets, `@import`, images and `url()` read
   `data:` URLs only, unless a loader is given (see the README).
 * Web fonts, font sizes (but with the text sizing protocol of kitty), positions and sizes finer than a cell.

Hyphenation
-----------

`hyphens: auto` needs a `HyphenatorInterface` given to `CssStyleSheet` and a dictionary for the language of the text
(its `lang` attribute). TuiHtml bundles no dictionary: without them, words break at their soft hyphens (`&shy;`) only,
like `hyphens: manual`. See the README.

Summary
-------

The number of features of each kind by level, from `CompatibilityReport::createDefault()`. The notes of each one are in
`Resources/compatibility.json`.

<!-- Generated by the tests with TUI_UPDATE_COMPAT=1 from here -->

| | supported | partial | ignored |
|---|---|---|---|
| HTML elements | 98 | 44 | 4 |
| HTML global attributes | 16 | 7 | 11 |
| CSS properties | 167 | 260 | 105 |
| CSS values and functions | 48 | 46 | 2 |
| CSS selectors | 67 | 20 | 20 |
| CSS at-rules | 42 | 8 | 7 |
| MathML elements | 0 | 1 | 0 |
