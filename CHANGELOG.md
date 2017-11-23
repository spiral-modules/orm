CHANGELOG
======================

1.0.8 (23.11.2017)
-----
- Update composer dependencies
- bugfix of HAS_ONE NULLABLE relation

1.0.5 (29.09.2017)
-----
- bugfix of MANY_TO_MANY exception when used several databases

1.0.3 (20.09.2017)
-----
- bugfix of association of HasOne to already saved record with nullable outer key
- bugfix of forcing join type of pre-loading
- readme update

1.0.1 (25.04.2017)
-----
- BelongsTo and BelongsToMorphed relations do not save parent relations when parent is already loaded (to prevent recursion loops)

0.9.7 (15.03.2017) = 1.0.0 public release
-----
- fetchAll
- better phpDoc

0.9.4 (11.02.2017)
-----
- table and model role are now tabelized instead of camelized

0.9.4 (09.02.2017)
-----
- improved database alias resolution

0.9.2 (06.02.2017)
-----
* Data tree deduplication now casts primary key as string to address ObjectIDs
* AbstractNode now allows to parse data without column mapping

0.9.0 (03.02.2017)
-----
* Split from Components package
