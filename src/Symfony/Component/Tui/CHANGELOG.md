CHANGELOG
=========

8.2
---

 * Reuse unchanged rendered line segments during differential updates
 * Add `AbstractWidget::attachChild()` and `AbstractWidget::detachChild()` to wire child widgets
 * Add multi-select support to `SelectListWidget`
 * [BC BREAK] Add `$multiselect` as the third argument of `SelectListWidget::__construct()`, moving `$keybindings` to fourth position
 * Add `LinearGradient`, `RadialGradient`, `ColorStop` and `Angle`, with `Style::withLinearGradient()` and
   `Style::withRadialGradient()`, to paint gradient backgrounds on a truecolor terminal

8.1
---

 * Introduce the component as experimental
