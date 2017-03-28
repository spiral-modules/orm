CHANGELOG for 0.9.0 RC
======================

0.9.9 (28.03.2017)
-----
- now possible to use 'limit' and 'orderBy' options in HasMany and ManyToMany loaders
- new Record constant 'ORDER_BY', defines default order for related data (exclude: with-relations)

0.9.7 (15.03.2017)
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
