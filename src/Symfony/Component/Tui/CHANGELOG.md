CHANGELOG
=========

8.3
---

 * Add `RendererInterface`, `FrameWriterInterface` and `FocusPolicyInterface` so that another rendering backend can draw the widget tree
 * Add `Tui::setRenderer()` and `Tui::getRenderer()` to select the rendering backend, and accept a `RendererInterface` as the `$renderer` argument of `Tui::__construct()`
 * Add `WidgetContext::getRenderer()` and `WidgetContext::resolveStyle()`
 * Add `RendererChangeEvent`, dispatched when `Tui::setRenderer()` selects another renderer
 * Add `Tui::removeListener()` to remove listeners registered with `Tui::addListener()`
 * Add `AbstractWidget::setState()` and `AbstractWidget::hasState()` for custom state flags matched by state selectors
 * Add `TerminalInterface::pushTitle()` and `TerminalInterface::popTitle()` to save and restore the terminal window title
 * Add `FocusManager::setTabIndex()`, `FocusManager::getTabIndex()` and `FocusManager::getCycle()` to order the focus cycle
 * Add the `$fragments` and `$clip` arguments to `WidgetRect::__construct()`, and `WidgetRect::withClip()` and `WidgetRect::withOffset()`, for widgets drawn in several pieces or clipped
 * Make `LineBufferInterface` and `WidgetRect` part of the public API, as the types renderers return
 * Add `AbstractWidget::getExtensionState()` to keep the state a renderer or another extension has of each widget
 * Make `AbstractWidget::dispatch()` public, to dispatch events on a widget from a renderer
 * Make the render cache of `AbstractWidget` part of the public API: `getRenderCache()`, `setRenderCache()` and `clearRenderCache()`, with a `$key` for what else a render depends on besides its size
 * Add `FocusManager::setTabStart()` and `FocusManager::isTabStart()` to leave the focus to the first Tab
 * Make `WidgetContext::getFocusManager()` part of the public API
 * Add `Tui::setReducedMotion()`, `Tui::prefersReducedMotion()` and `WidgetContext::prefersReducedMotion()`: `LoaderWidget` and an indeterminate `ProgressBarWidget` stay still while it is set; an indeterminate `ProgressBarWidget` has the `indeterminate` state
 * Skip the widgets hidden by their style and those with the disabled state (`AbstractWidget::STATE_DISABLED`) in the focus cycle; a focused widget that gets that state loses the focus and receives no input, and one the renderer no longer allows loses it after the next frame (`FocusManager::revalidateFocus()`)
 * Draw bidirectional text in visual order when the intl extension is available, the terminal set to its explicit BiDi mode while the TUI runs, and add ANSI text measurement and wrapping helpers

8.2
---

 * Add `TerminalInterface::setProgramStatus()` and `TerminalInterface::clearProgramStatus()` to report the status of the program and its tasks with the Program Status Protocol (OSC 7501)
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
