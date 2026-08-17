CHANGELOG
=========

8.2
---

 * Add `AnsiUtils::walkCells()` to iterate the cells and escape sequences of a rendered line
 * Add `AnsiUtils::sliceToWidth()` to extract a fixed-width range of columns from a line
 * Add `AbstractWidget::postRender()` to post-process a widget's finished lines, chrome included
 * Make `LoopClock` part of the public API
 * Dispatch paste lifecycle events through an optional terminal event dispatcher
 * Reuse unchanged rendered line segments during differential updates
 * Add `AbstractWidget::attachChild()` and `AbstractWidget::detachChild()` to wire child widgets
 * Add multi-select support to `SelectListWidget`
 * [BC BREAK] Add `$multiselect` as the third argument of `SelectListWidget::__construct()`, moving `$keybindings` to fourth position

8.1
---

 * Introduce the component as experimental
