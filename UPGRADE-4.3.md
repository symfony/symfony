UPGRADE FROM 4.2 to 4.3
=======================

Yaml
----

 * Parsing YAML strings that contain any of the unicode sequences forbidden by the specification is deprecated and
   will throw a `ParseException` in 5.0.
