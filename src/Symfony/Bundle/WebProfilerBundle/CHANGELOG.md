CHANGELOG
=========

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
