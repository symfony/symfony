<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Tui\Html;

use Symfony\Component\Tui\Compatibility\Support;
use Symfony\Component\Tui\Compatibility\SupportProviderInterface;
use Symfony\Component\Tui\Html\Element\ButtonElement;
use Symfony\Component\Tui\Html\Element\ContainerElement;
use Symfony\Component\Tui\Html\Element\DetailsElement;
use Symfony\Component\Tui\Html\Element\FormElement;
use Symfony\Component\Tui\Html\Element\ImageElement;
use Symfony\Component\Tui\Html\Element\InputElement;
use Symfony\Component\Tui\Html\Element\LoaderElement;
use Symfony\Component\Tui\Html\Element\MarkdownElement;
use Symfony\Component\Tui\Html\Element\MarqueeElement;
use Symfony\Component\Tui\Html\Element\MeterElement;
use Symfony\Component\Tui\Html\Element\PictureElement;
use Symfony\Component\Tui\Html\Element\ProgressElement;
use Symfony\Component\Tui\Html\Element\SelectElement;
use Symfony\Component\Tui\Html\Element\TableElement;
use Symfony\Component\Tui\Html\Element\TabsElement;
use Symfony\Component\Tui\Html\Element\TextareaElement;
use Symfony\Component\Tui\Html\Element\TextElement;
use Symfony\Component\Tui\Widget\AbstractWidget;
use Symfony\Component\Tui\Widget\ContainerWidget;

/**
 * Maps element names to widget factories.
 *
 * Applications register their own widgets as custom elements, whose names
 * contain a hyphen as HTML requires (<app-file-tree>).
 *
 * @experimental
 */
final class ElementRegistry implements SupportProviderInterface
{
    /** @var array<string, ElementFactoryInterface> */
    private array $factories = [];

    /** @var array<string, Support> */
    private array $support = [];

    public function __construct(bool $registerDefaults = true)
    {
        if ($registerDefaults) {
            $this->registerDefaults();
        }
    }

    /**
     * Registers an element, or replaces the factory of a known one, like new ImageElement($loader->load(...)) for img.
     *
     * @param ElementFactoryInterface|\Closure(\Dom\Element, HtmlBuilder): AbstractWidget $factory
     * @param Support|null                                                                $support null for supported, or what was known of a replaced element
     *
     * @return $this
     */
    public function register(string $tagName, ElementFactoryInterface|\Closure $factory, ?Support $support = null): static
    {
        $tagName = strtolower($tagName);
        $this->factories[$tagName] = $factory instanceof \Closure ? new class($factory) implements ElementFactoryInterface {
            public function __construct(private readonly \Closure $factory)
            {
            }

            public function create(\Dom\Element $element, HtmlBuilder $builder): AbstractWidget
            {
                return ($this->factory)($element, $builder);
            }
        } : $factory;
        // Replacing the factory of a known element keeps what is known of its support
        $this->support['html.elements.'.$tagName] = $support ?? $this->support['html.elements.'.$tagName] ?? Support::supported();

        return $this;
    }

    public function get(string $tagName): ?ElementFactoryInterface
    {
        return $this->factories[strtolower($tagName)] ?? null;
    }

    public function getSupport(): array
    {
        return $this->support + [
            // Built by <select>, not on their own
            'html.elements.option' => Support::supported('Inside <select>: see select. In a dropdown, an option holding elements and its elements are styled by CSS, like option:default or option .flag: their colors and text attributes, in the list and in the field.'),
            'html.elements.datalist' => Support::partial('For <input list>: the options containing what is typed are listed below the input while it has the focus (ComboboxWidget), Down (or Alt+Down) opens, all of them when none contains the value, Up and Down move, Enter takes, Escape hides them; the first one extending the value is shown after the cursor, Tab or Right takes it. A number field keeps stepping with Up and Down: Alt+Down opens its list.'),
            'html.elements.optgroup' => Support::supported('Inside <select>: a heading over its options; disabled hides them.'),
            'html.elements.selectedcontent' => Support::supported('Inside the <button> of a customizable <select>: the field shows the content of the selected option, its elements styled like in the list, the ▾ after it.'),
            'html.global_attributes.id' => Support::supported(),
            'html.global_attributes.class' => Support::supported(),
            'html.global_attributes.style' => Support::supported('Declarations take part in the cascade, var() included.'),
            'html.global_attributes.hidden' => Support::supported(),
            'html.global_attributes.title' => Support::supported('Labels the widget, after aria-label and <label>; HtmlView::titleAt() returns it for tooltips.'),
            'html.global_attributes.autofocus' => Support::supported(),
            'html.global_attributes.popover' => Support::partial('auto, manual or hint: hidden until shown (:popover-open), then over the page, centered by the user agent stylesheet; an auto one closes on Escape, a click outside it and another auto one opening. Its invoker is its implicit anchor, which position-area and anchor() use. A hint one closes the other hints, not the auto ones, which close it. A BeforeToggleEvent is dispatched on it before it is shown or hidden, stopping it keeps it hidden, and a ToggleEvent once it is.'),
            'html.elements.input.size' => Support::supported('A presentational hint: the width of a text field, in characters, the cursor\'s column added; without it, a field its content sizes, like in a flex row, is 20 characters wide, like browsers, not the room it has.'),
            'html.elements.textarea.cols' => Support::supported('A presentational hint: width in columns, under any CSS rule.'),
            'html.elements.table.width' => Support::supported('A presentational hint: CSS pixels or a percentage, under any CSS rule.'),
            'html.elements.td.width' => Support::supported('A presentational hint: CSS pixels or a percentage, under any CSS rule.'),
            'html.elements.table.border' => Support::supported('A presentational hint: a border around the table, none for 0.'),
            'html.elements.table.bgcolor' => Support::supported('A presentational hint: the background color, of tables, rows and cells.'),
            'html.elements.td.bgcolor' => Support::supported('A presentational hint: the background color, of tables, rows and cells.'),
            'html.elements.td.nowrap' => Support::supported('A presentational hint: white-space: nowrap.'),
            'html.elements.td.align' => Support::supported('A presentational hint: text-align, on cells, rows, captions, paragraphs, divisions and headings.'),
            'html.elements.p.align' => Support::supported('A presentational hint: text-align, on cells, rows, captions, paragraphs, divisions and headings.'),
            'html.elements.summary' => Support::supported('In a <details>: its text, styled like an element (summary { ... }); its marker follows list-style: none, ::marker content and ::-webkit-details-marker; ::before and ::after content around its text, the latter at the end of the line in a flex row spread by justify-content, like the + of a FAQ.'),
            'html.global_attributes.interestfor' => Support::partial('Pointing at the element or focusing it shows the popover it names, anchored to it (position-area places it), and leaving it hides it, unless the popover is pointed at; no delays.'),
            'html.elements.dialog.closedby' => Support::supported('any: Escape or a click outside closes the dialog; closerequest: Escape does, the default of a modal one; none: neither, the default of the others.'),
            'html.elements.button.popovertarget' => Support::supported('With popovertargetaction (toggle, show, hide).'),
            'html.elements.button.commandfor' => Support::partial('With command: show-modal, close, request-close, show-popover, hide-popover and toggle-popover, each after a CommandEvent on the element, stopping it cancels the command; custom commands (--name) only dispatch the event.'),
            'html.global_attributes.inert' => Support::supported('The subtree takes neither the focus nor the pointer.'),
            'html.global_attributes.tabindex' => Support::supported('Orders the F6 focus cycle; a negative value leaves the cycle.'),
            'html.global_attributes.accesskey' => Support::supported('Alt + the key focuses the element.'),
            'html.global_attributes.lang' => Support::partial('Kept for attribute selectors and :lang(), and the quotation marks of quotes: auto; the words that hyphens: auto breaks, see the CSS property; text is not otherwise localized.'),
            'html.global_attributes.dir' => Support::partial('Sets direction, see the CSS property, and isolates the element (unicode-bidi: isolate): rtl mirrors rows and logical sides, starts text on the right and draws it right to left; auto takes the direction of the first strong character.'),
            'html.global_attributes.data_attributes' => Support::supported(),
            'html.global_attributes.data-density' => Support::supported('Tui extension: "condensed" or "regular", inherited; see :condensed.'),
            'html.global_attributes.aria-label' => Support::supported(),
            'html.global_attributes.aria-labelledby' => Support::supported(),
            'html.global_attributes.aria-describedby' => Support::partial('Used as the description of <select> options and <details>.'),
            'html.global_attributes.aria-live' => Support::supported('Changes are dispatched as LiveRegionChangedEvent.'),
            'html.global_attributes.role' => Support::partial('Kept for attribute selectors; status and alert are live regions.'),
            'html.elements.style' => Support::supported(),
            'html.elements.link' => Support::partial('rel="stylesheet" only, loaded with the stylesheet loader; the media attribute is honored.'),
            'html.elements.label' => Support::supported('Labels the control named by "for" or the one it contains, which is on the line of its text, like an inline control, a field taking the room left.'),
            'html.elements.br' => Support::supported(),
            'html.elements.strong' => Support::supported('Inline: styled by CSS, see the user agent stylesheet.'),
            'html.elements.b' => Support::supported('Inline: styled by CSS, see the user agent stylesheet.'),
            'html.elements.em' => Support::supported('Inline: styled by CSS, see the user agent stylesheet.'),
            'html.elements.i' => Support::supported('Inline: styled by CSS, see the user agent stylesheet.'),
            'html.elements.u' => Support::supported('Inline: styled by CSS, see the user agent stylesheet.'),
            'html.elements.s' => Support::supported('Inline: styled by CSS, see the user agent stylesheet.'),
            'html.elements.del' => Support::supported('Inline: styled by CSS, see the user agent stylesheet.'),
            'html.elements.ins' => Support::supported('Inline: styled by CSS, see the user agent stylesheet.'),
            'html.elements.code' => Support::supported('Inline: styled by CSS, see the user agent stylesheet.'),
            'html.elements.kbd' => Support::supported('Inline: styled by CSS, see the user agent stylesheet.'),
            'html.elements.a' => Support::supported('A terminal hyperlink (OSC 8) for http(s), mailto, file, ftp, sftp and ssh URLs, on each line it wraps over; a link to a fragment (#id) goes there when clicked, see :target. Text with links takes the focus (LinkedTextWidget, in the F6 cycle but never focused first): Tab and Shift+Tab, or Right and Left, move between its links, the current one reversed with the cursor on it, Enter follows it with a LinkEvent, which goes to a fragment and leaves the others to the application.'),
            'html.elements.mark' => Support::supported('Inline: styled by CSS, see the user agent stylesheet.'),
            'html.elements.small' => Support::supported('Inline: styled by CSS.'),
            'html.elements.span' => Support::supported('Inline when inside text: styled by CSS.'),
            'html.elements.abbr' => Support::supported('Inline: styled by CSS.'),
            'html.elements.q' => Support::supported('Inline, quoted with ::before and ::after.'),
            'html.elements.cite' => Support::supported('Inline: styled by CSS.'),
            'html.elements.sub' => Support::partial('Subscript characters (H₂O) when Unicode has all of them, see vertical-align; otherwise on the line.'),
            'html.elements.sup' => Support::partial('Superscript characters (x²) when Unicode has all of them, see vertical-align; otherwise on the line.'),
            'html.elements.time' => Support::supported('Inline.'),
            'html.elements.dfn' => Support::supported('Inline: styled by CSS, see the user agent stylesheet.'),
            'html.elements.var' => Support::supported('Inline: styled by CSS, see the user agent stylesheet.'),
            'html.elements.samp' => Support::supported('Inline: styled by CSS, see the user agent stylesheet.'),
            'html.elements.data' => Support::supported('Inline; its value attribute is kept for attribute selectors.'),
            'html.elements.bdi' => Support::partial('Inline, isolated from the text around it (unicode-bidi: isolate), in the direction of its first strong character without dir; with ext-intl or symfony/polyfill-intl-char, otherwise drawn as stored.'),
            'html.elements.bdo' => Support::supported('Inline, its characters in the order of its dir (unicode-bidi: isolate-override): dir="rtl" draws them right to left.'),
            'html.elements.wbr' => Support::supported('A line break opportunity: a long word can wrap there.'),
            'html.elements.ruby' => Support::partial('Its annotations (<rt>) on a row above the line of their bases, or below it with ruby-position: under, each base as wide as its annotation, kept on one line; bases (<rb>, or the text before the annotations) and annotations pair one to one, the annotations left over going with the last base; <rtc> is an annotation of its own; on a line that cannot have rows above it, like a button, the annotation follows its base in parentheses.'),
            'html.elements.rt' => Support::partial('Over its base, see <ruby>; displayed inline, it is drawn after it, like in browsers.'),
            'html.elements.rp' => Support::supported('Hidden, like in browsers that draw ruby.'),
            'css.selectors.before' => Support::partial('On text blocks, inline elements and blocks holding blocks (a line of its own), and as items of flex rows and grids, with content, in its colors and text attributes over those of its element, its horizontal padding in its background; absolute or fixed, of a block, drawn over the frame like an absolute element, the block its containing block when positioned, like a tooltip in .tip:hover::after, written :before too, like CSS 2.'),
            'css.selectors.after' => Support::partial('On text blocks, inline elements and blocks holding blocks (a line of its own), and as items of flex rows and grids, with content, in its colors and text attributes over those of its element, its horizontal padding in its background; absolute or fixed, of a block, drawn over the frame like an absolute element, the block its containing block when positioned, like a tooltip in .tip:hover::after, written :after too, like CSS 2.'),
            'css.selectors.marker' => Support::partial('On <li>: its content, colors and text attributes, over those of the item; not its box.'),
            'html.elements.html' => Support::supported('The root: :root, its rules and its attributes, like lang and dir, apply; as tall as its content, its percentage heights and those of <body> are of the screen. HtmlView::getRoot() returns it, getBody() the <body>.'),
            'html.elements.meta' => Support::partial('name="color-scheme": the color-scheme of <html> under any CSS rule, from the first valid one; the other names and charset are ignored.'),
            'html.elements.base' => Support::partial('href: the first absolute one is the URL the relative links (<a href>) are resolved against, for terminal hyperlinks and LinkEvent; links to a fragment stay in the page; not stylesheets, which the loader finds; target is ignored.'),
            'html.elements.head' => Support::ignored('Not drawn: its <title>, <style> and <link> elements are read.'),
            'html.elements.title' => Support::partial('The title of the terminal window or tab, set when the view is mounted at the root of the Tui (HtmlView::getDocumentTitle() returns it); the terminal keeps it after the program stops.'),
            'html.elements.area' => Support::partial('The regions of the images whose usemap names its <map> are links: rect, circle, poly and default shapes, in CSS pixels of the image as drawn, the cells whose center is in them, the first area there winning; followed like the links of a text, to a fragment of the page or by the terminal; the image takes the focus (ImageMapWidget, in the F6 cycle but never focused first): Tab and Shift+Tab, or Right and Left, move between its areas, the current one reversed with the cursor on it, Enter follows it with a LinkEvent.'),
            'html.elements.track' => Support::ignored('Videos and their captions are not played.'),
            'html.elements.param' => Support::ignored('The parameters of an <object>: an image is drawn without them, anything else is not drawn.'),
            'html.global_attributes.autocapitalize' => Support::ignored('Text is typed as it is: there is no virtual keyboard.'),
            'html.global_attributes.autocorrect' => Support::ignored('Text is typed as it is: there is no virtual keyboard.'),
            'html.global_attributes.enterkeyhint' => Support::ignored('Text is typed as it is: there is no virtual keyboard.'),
            'html.global_attributes.inputmode' => Support::ignored('Text is typed as it is: there is no virtual keyboard.'),
            'html.global_attributes.writingsuggestions' => Support::ignored('Text is typed as it is: there are no suggestions.'),
            'html.global_attributes.spellcheck' => Support::ignored('Text is not checked for spelling.'),
            'html.global_attributes.translate' => Support::ignored('Text is not translated.'),
            'html.global_attributes.contenteditable' => Support::ignored('Elements cannot be edited in place: see <textarea> and <input>.'),
            'html.global_attributes.draggable' => Support::ignored('Elements cannot be dragged.'),
            'html.global_attributes.nonce' => Support::ignored('Scripts do not run, and style sheets are not filtered by a content security policy.'),
            'html.global_attributes.is' => Support::ignored('There are no custom elements: the element is drawn like its tag.'),
            'html.global_attributes.part' => Support::partial('The names of an element of a shadow tree that ::part() matches from outside it; kept for attribute selectors.'),
            'html.global_attributes.exportparts' => Support::supported('On the host of a shadow tree in another: the parts of its tree the outer tree sees, like "icon, label: title", under their name or another, on out through the hosts that export them again.'),
            'html.global_attributes.slot' => Support::supported('The <slot> of the shadow tree of its parent it is drawn in, by name; without it, the slot without a name.'),
            'html.elements.script' => Support::ignored(),
            'html.elements.template' => Support::partial('Not drawn, but with shadowrootmode (open or closed), as the first of its kind in an element: a declarative shadow root, its content drawn in place of the children of that element, which go in its <slot> elements; its <style> and <link> elements style it alone, and those of the page do not reach in but by inheritance, ::part() and the host; the child and sibling combinators of the page see a slotted element in its slot, not in its host. Scripts not running, nothing else uses templates.'),
        ];
    }

    private function registerDefaults(): void
    {
        $container = new ContainerElement();
        foreach (['div', 'section', 'main', 'aside', 'header', 'footer', 'nav', 'article', 'fieldset', 'body', 'ul', 'ol', 'dl', 'figure', 'search', 'menu', 'tui-columns', 'tui-column'] as $tag) {
            $this->register($tag, $container);
        }
        $this->register('form', new FormElement(), Support::partial('A submit button or Enter in a single-line field submits it, after constraint validation (novalidate, formnovalidate): the FormWidget dispatches a FormSubmitEvent with the values of its named, enabled controls, a name ending with [] collecting a list, and its action and method (formaction and formmethod of the button first), the application sending them; method="dialog" closes the dialog the form is in; its controls are those it holds and those whose form attribute names it, before or after it; reset buttons restore the initial values.'));
        $this->register('center', $container, Support::supported('A block whose text is centered.'));
        $this->register('marquee', new MarqueeElement(), Support::partial('Its text moves sideways on one line (MarqueeWidget): direction (left or right, up and down like them), behavior (scroll, slide, alternate), scrollamount (8 CSS pixels a column, a column at least), scrolldelay (60ms at least unless truespeed) and loop; still at its start with prefers-reduced-motion; a marquee holding blocks is a block, its text not moving.'));
        $this->register('noscript', $container, Support::supported('Drawn, its style sheets applying, like in a browser without scripts: scripts do not run; left out, its style sheets too, once they do, see HtmlBuilder::setScripting().'));
        $this->register('map', $container, Support::partial('Its content is drawn, like in a browser; its <area> elements make regions of the images that use it (usemap) links, see <area>.'));
        $this->register('slot', $container, Support::supported('In a declarative shadow root, see <template>: the children of the host whose slot attribute names it (or without one, for the slot without a name), else its own content; display: contents, by the user agent stylesheet.'));
        $this->register('fieldset', $container, Support::supported('disabled disables the form controls inside, except those of its first <legend>.'));
        $this->register('tui-columns', $container, Support::supported('Tui extension: children laid out in columns, see .columns.'));
        $this->register('tui-column', $container, Support::supported('Tui extension: a column of <tui-columns>.'));

        $text = new TextElement();
        foreach (['p', 'span', 'label', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'pre', 'li', 'dt', 'dd', 'figcaption', 'legend', 'blockquote', 'output', 'tui-text'] as $tag) {
            $this->register($tag, $text);
        }
        // Obsolete elements, drawn like browsers do
        foreach (['font' => 'Inline; its color, size (1 to 7, or relative to 3 like +2, see font-size) and face (see font-family) attributes are presentational hints.', 'big' => 'Inline, bold: the font size cannot grow.', 'tt' => 'Inline, in the terminal font.', 'acronym' => 'Inline, like <abbr>.', 'nobr' => 'Inline, kept on one line.', 'strike' => 'Inline, like <s>.', 'listing' => 'Preformatted, like <pre>.', 'xmp' => 'Preformatted, like <pre>.'] as $tag => $note) {
            $this->register($tag, $text, Support::supported($note));
        }
        $this->register('label', $text, Support::supported('Names its control, by for or by wrapping it; a click on the label goes to the control.'));
        $this->register('legend', $text, Support::supported('The first one of a <fieldset> is drawn in its top border, after its left corner, like browsers; without a top border, on a line of its own.'));
        $this->register('li', $text, Support::partial('The marker follows list-style-type, list-style-position and li::marker, while it is displayed as a list item: displayed otherwise, like a block or a flex row, it has none; an item with nested blocks is a container, its first block carrying the marker; displayed as a flex or grid container, its text and its elements are items.'));
        $this->register('pre', $text, Support::supported('Whitespace is preserved.'));
        $this->register('hr', $text, Support::supported());
        $this->register('tui-text', $text, Support::supported('Tui extension: a TextWidget; truncate="" truncates instead of wrapping.'));

        $this->register('textarea', new TextareaElement(), Support::partial('Content, rows (that many lines, scrolling past them, unless field-sizing: content; without it, it grows), placeholder, readonly and maxlength, and cols as a width in columns; wrap="off" keeps each line on one row, scrolled sideways to the cursor (like white-space: pre).'));
        $this->register('select', new SelectElement(), Support::partial('Without size or multiple, a dropdown (DropdownWidget): the choice on one line, Enter or Space opens the list over what follows, Enter takes an item, Escape or leaving it closes it as it was; with them, a list. <option> value, label, title (description), selected. <optgroup label> is a heading over its options, styled by select::group; a disabled one hides them.'));
        $this->register('progress', new ProgressElement(), Support::partial('value and max: the bar and its percentage, which says it without colors; without a value, indeterminate, the bar moving, still with prefers-reduced-motion; no step count, like a browser.'));
        $this->register('details', new DetailsElement(), Support::partial('<summary>, open, and name for exclusive accordions; the content is the remaining children. The summary is matched like an element: its style, list-style: none, ::marker content and ::-webkit-details-marker { display: none } apply to its marker. Opening or closing it dispatches an ExpandEvent or a CollapseEvent, then a ToggleEvent.'));
        $this->register('dialog', static fn (\Dom\Element $element, HtmlBuilder $builder) => (new ContainerElement())->create($element, $builder)->setState('open', $element->hasAttribute('open')), Support::partial('Shown when open, as a box in the flow: the "open" state (:open) is the attribute; HtmlView::show() opens it there and showModal() over the page, under its ::backdrop, both focusing its first control; close() closes it, with a return value (getReturnValue(), the value of the button that submits a form of method dialog in it), and dispatches a CloseEvent; Escape, a click outside one of closedby="any", request-close and requestClose() dispatch a CancelEvent first, which stopping keeps it open; a BeforeToggleEvent before it opens or closes, stopping it keeps it closed, and a ToggleEvent once it has.'));
        $this->register('tui-markdown', new MarkdownElement(), Support::supported('Tui extension: a MarkdownWidget rendering its text content.'));
        $this->register('tui-tabs', new TabsElement(), Support::supported('Tui extension: a TabsWidget; each child is a tab labeled by its title.'));
        $this->register('tui-loader', new LoaderElement(), Support::supported('Tui extension: a LoaderWidget; the text content is the message.'));

        // Buttons, checkboxes, radios
        $this->register('button', new ButtonElement(), Support::partial('In the line of the text around it, like browsers, unless CSS displays it as a block; Enter or Space dispatches a PressEvent; the text content is the label, in brackets, unless CSS gives the button a border, which is then its look, or no border or a background, in rules of every state, like browsers, which then drop the native look; an <svg> or <img> icon in it is drawn in the line, a row tall, in the color of the button, its text alternative when it is too thin to show in so few cells; a submit button submits its form, see <form>.'));
        $this->register('input', new InputElement(), Support::partial('Text-like types (text, search, email, url, tel, number with arrows stepping it, password masked), date, time, datetime-local, month and week as text validated against their format, range as a slider, checkbox and radio, submit, button and reset; value, placeholder, readonly, maxlength (typing and pasting stop at it), checked and name (radio groups). color as #rrggbb with a swatch of it (ColorInputWidget), file as a path to type in, completed like in a shell with Tab (the entries of its directory, the files of the types accept names, by extension or MIME type); hidden is not drawn, its value submitted; image is a submit button drawing its image a row tall, like the icon of a button (its alt its name, shown when the image cannot be drawn), sending name.x and name.y.'));
        $this->support['html.elements.input.type_checkbox'] = Support::supported('Space toggles it; the "checked" state follows the value; only the box is drawn, like in a browser.');
        $this->support['html.elements.input.type_radio'] = Support::supported('Space checks it and unchecks the radios of the same name in the document.');

        // Embedded content: the text alternative is shown
        $image = new ImageElement();
        $this->register('img', $image, Support::partial('Raster images (PNG, JPEG, GIF, WebP... with the GD extension), and SVG ones drawn at their size, are drawn with half blocks in true colors, from a data: URL or through the loader given to ImageElement; width and height are CSS pixels (8 per column, 16 per row), CSS sizes bound them, a height alone narrowing it by its proportions; without a width its box is as wide as it is drawn, like the inline images of browsers. Inside an inline element, like a link, it is drawn in the line of the text, a row tall and as wide as its proportions say (up to 16 columns), wrapped like a word. Otherwise the alt text is shown in brackets in the line of the text around it, like browsers; an empty alt or aria-hidden shows nothing; srcset picks the candidate for the density of the terminal, like a <picture>, chosen again as it changes.'));
        $this->register('picture', new PictureElement(), Support::partial('The image of the first <source> whose media query matches, else its <img>, chosen again as the media change (the color scheme, the width...); each source is read like an <img> with the candidate of its srcset for the density of the terminal (the smallest density at least its resolution, the densest past them; width descriptors over the px length of sizes or the width attribute, else the first candidate), through the <img> factory and its loader.'));
        $this->register('source', static fn () => new ContainerWidget(), Support::partial('In a <picture>: media, srcset (the candidate for the density of the terminal, see <picture>), sizes as a px length, width and height; a source whose first candidate cannot be read or decoded is skipped; its other candidates are read when they are first drawn, their alt text shown when they cannot be, like browsers.'));
        $this->register('svg', $image, Support::partial('With the GD extension, its shapes are drawn with half blocks in true colors, like an <img>: rect (with rx and ry), circle, ellipse, line, polyline, polygon and path (every command, curves and arcs too), in groups, nested <svg> elements and copies of <use> (a <symbol> too), with their transforms, the fill and stroke colors (currentColor is the color of the element), linear and radial gradients for fills (their stops, units and href; not gradientTransform, spreadMethod nor the focal point; a stroke takes their average color) and opacities, stroke-width, the dashes, caps and joins of strokes (stroke-dasharray, stroke-dashoffset, stroke-linecap, stroke-linejoin and stroke-miterlimit), the markers of lines, polylines, polygons and paths (marker-start, marker-mid and marker-end, at the ends of their commands, with their refX, refY, orient, markerUnits, markerWidth, markerHeight and viewBox; not clipped to them), paint-order, fill-rule, display and visibility, the stop-color and stop-opacity of the stops, the font-size and text-anchor of text, from their presentation attributes, their geometry (x, y, width, height, cx, cy, r, rx, ry and d: path()) from style sheets over their attributes, the rules of the page that match them (not those of a state, like :hover; those of the media, again as they change) and their style attributes; the viewBox fills its width and height, CSS pixels (8 per column, 16 per row), like preserveAspectRatio, else its view box is its size, else 300 by 150; text as characters at its x and y, text-anchor and dominant-baseline (central and middle) where its font-size is half a row to two and a half rows high once drawn, like the labels of a diagram, over the color of the cells under it, else in the bitmap font of GD scaled to its font-size (ASCII, accents dropped, the other characters as question marks; not the font, the weight nor the spacing); the lines of a drawing 16 columns by 4 rows or larger a pixel of its cells wide at least, as browsers draw a line a device pixel wide at least; clip-path to a <clipPath> (its shapes, clip-rule, clipPathUnits; not its text), fills and strokes of a <pattern> (tiles of its content with x, y, width, height, patternUnits, patternContentUnits, viewBox and href, moved and scaled by patternTransform; turned, its fallback color), masks (a <mask> fading what it masks by its luminance or alpha, maskUnits, maskContentUnits), filters (CSS filter functions, or a <filter> of feColorMatrix, feFlood, feOffset, feGaussianBlur, feDropShadow, feMerge and feComposite over, in, out, atop or xor, with its region; one of another primitive is not applied). Inside an inline element, like an icon in a link, it is drawn in the line of the text, a row tall and as wide as its view box says (up to 16 columns), currentColor the color of the text there. Without a shape, the aria-label, the title or the <title> child is shown in brackets in the line of the text around it.'));
        $this->register('iframe', $image, Support::partial('srcdoc: a nested document in a box of its width and height (300 by 150 CSS pixels by default) with a border, scrolled when taller, styled by its own stylesheets only, which do not reach the page around it, its media queries and viewport units of that box inside its border (of its attributes, not of a CSS size); its fields join the focus order of the page; nested three deep at most. Without srcdoc, the document of its src, read like the src of an <img> (a data: URL, or the loader given to ImageElement for iframe), as UTF-8; one that cannot be read: the aria-label, the title or the <title> child is shown in brackets in the line of the text around it, like an <img> that cannot be drawn.'));
        $this->register('embed', $image, Support::partial('An image (src, of no type or an image/ one) is drawn like an <img>, width and height too; anything else is not: the aria-label or the title is shown in brackets in the line of the text around it, like an <img> that cannot be drawn.'));
        $this->register('video', $image, Support::partial('Not played: its poster is drawn like an <img>, width and height too, else its fallback content is, like in browsers that cannot play it, else the aria-label or the title in brackets, in the line of the text around it.'));
        $this->register('object', $image, Support::partial('An image (data, of no type or an image/ one) is drawn like an <img>, width and height too; anything else is not: its fallback content is, like in browsers that cannot draw it, else the aria-label or the title in brackets, in the line of the text around it.'));
        foreach (['canvas', 'audio'] as $tag) {
            $this->register($tag, $image, Support::partial('Not drawn: its fallback content is, like in browsers that cannot draw it, else the aria-label or the title in brackets, in the line of the text around it.'));
        }

        // Tables
        $table = new TableElement();
        foreach (['table', 'thead', 'tbody', 'tfoot', 'tr', 'td', 'th'] as $tag) {
            $this->register($tag, $table, Support::partial('Columns are sized across the rows by their widest cell, shrunk down to their longest words when the table is too wide, and evenly past them, stretched by a percentage width; colspan spans columns; rowspan draws the cell across the rows it spans, the last of them taller when it needs more (in its first row when border-spacing sets rows apart).'));
        }
        foreach (['colgroup', 'col'] as $tag) {
            $this->register($tag, $table, Support::partial('Not drawn: the width attribute (CSS pixels or a percentage) or the CSS width is the least width of each column it spans, span says how many; its background is under the cells of its columns without one; its other styles do not apply to them.'));
        }
        $this->register('caption', $text, Support::supported());

        $this->register('meter', new MeterElement(), Support::partial('value, min and max, drawn as a bar and its percentage, which says it without colors, like <progress>; low, high and optimum set :optimum, :suboptimum or :even-less-good, green, yellow and red by default.'));
        // MathML: linear text, like a formula typed in plain text (see HtmlBuilder::MATHML)
        $this->register('math', $text);
        $this->support['mathml.elements.math'] = Support::partial('Drawn as linear text in the line of the text around it, display="block" on a line of its own, centered: x^2 for <msup>, x_i for <msub>, a/b for <mfrac>, √(x) for <msqrt>, the groups of several parts in parentheses; <annotation> is not drawn.');
        unset($this->support['html.elements.math']);
        foreach (['address', 'hgroup'] as $tag) {
            $this->register($tag, $container);
        }
    }
}
