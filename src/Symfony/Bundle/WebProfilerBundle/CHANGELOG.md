CHANGELOG
=========

6.4
---

 * Add console commands to the profiler

6.3
---

 * Add a "role=img" and an explicit title in the .svg file used by the web debug toolbar
   to improve accessibility with screen readers for blind users
 * Add a clickable link to the entry view twig file in the toolbar

6.1
---

 * Add a download link in mailer profiler for email attachments

5.4
---

 * Add a "preview" tab in mailer profiler for HTML email

5.2.0
-----

 * added session usage

5.0.0
-----

 * removed the `ExceptionController`, use `ExceptionPanelController` instead
 * removed the `TemplateManager::templateExists()` method

4.4.0
-----

 * added support for the Mailer component
 * added support for the HttpClient component
 * added button to clear the ajax request tab
 * deprecated the `ExceptionController::templateExists()` method
 * deprecated the `TemplateManager::templateExists()` method
 * deprecated the `ExceptionController` in favor of `ExceptionPanelController`
 * marked all classes of the WebProfilerBundle as internal
 * added a section with the stamps of a message after it is dispatched in the Messenger panel

4.3.0
-----

 * Replaced the canvas performance graph renderer with an SVG renderer

4.1.0
-----

 * added information about orphaned events
 * made the toolbar auto-update with info from ajax reponses when they set the
   `Symfony-Debug-Toolbar-Replace header` to `1`

4.0.0
-----

 * removed the `WebProfilerExtension::dumpValue()` method
 * removed the `getTemplates()` method of the `TemplateManager` class in favor of the ``getNames()`` method
 * removed the `web_profiler.position` config option and the
   `web_profiler.debug_toolbar.position` container parameter

3.4.0
-----

 * Deprecated the `web_profiler.position` config option (in 4.0 version the toolbar
   will always be displayed at the bottom) and the `web_profiler.debug_toolbar.position`
   container parameter.

3.1.0
-----

 * added information about redirected and forwarded requests to the profiler

3.0.0
-----

 * removed profiler:import and profiler:export commands

2.8.0
-----

 * deprecated profiler:import and profiler:export commands

2.7.0
-----

 * [BC BREAK] if you are using a DB to store profiles, the table must be dropped
 * added the HTTP status code to profiles

2.3.0
-----

 * draw retina canvas if devicePixelRatio is bigger than 1

2.1.0
-----

 * deprecated the verbose setting (not relevant anymore)
 * [BC BREAK] You must clear old profiles after upgrading to 2.1 (don't forget
   to remove the table if you are using a DB)
 * added support for the request method
 * added a routing panel
 * added a timeline panel
 * The toolbar position can now be configured via the `position` option (can
   be `top` or `bottom`)
