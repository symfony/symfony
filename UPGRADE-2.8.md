UPGRADE FROM 2.7 to 2.8
=======================

Yaml
-----

 * The ability to pass $timestampAsDateTime = false to the Yaml::parse method is
   deprecated since version 2.8. The argument will be removed in 3.0. Pass true instead.
 * The ability to pass $dateTimeSupport = false to the Yaml::dump method is deprecated
   since version 2.8. The argument will be removed in 3.0. Pass true instead.
