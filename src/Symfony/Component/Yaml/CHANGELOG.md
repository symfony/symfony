CHANGELOG
=========

2.1.0
-----

 * Yaml::parse() does not evaluate loaded files as PHP files by default
   anymore (call Yaml::enablePhpParsing() to get back the old behavior)

2.8
---

 * Added a $timestampAsDateTime argument to the Yaml::parse() and Yaml::dump() methods.
 * The ability to pass $timestampAsDateTime = false to the Yaml::parse method is
   deprecated since version 2.8. The argument will be removed in 3.0. Pass true instead.
 * The ability to pass $timestampAsDateTime = false to the Yaml::dump method is deprecated
   since version 2.8. The argument will be removed in 3.0. Pass true instead.
