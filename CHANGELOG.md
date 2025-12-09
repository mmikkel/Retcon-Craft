# Retcon Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](http://keepachangelog.com/) and this project adheres to [Semantic Versioning](http://semver.org/).

## 3.2.2 - 2025-12-09
### Fixed 
- Fixed a bug where Retcon would remove UTF-8 encoded `&nbsp;` entities in CKEditor field values. [#73](https://github.com/mmikkel/Retcon-Craft/issues/73) 

## 3.2.1 - 2024-10-21
### Fixed 
- Fixed an issue where the `retconAutoAlt` filter didn't work as intended [#71](https://github.com/mmikkel/Retcon-Craft/issues/71)
- Fixed an issue where Retcon would convert UTF-8 non-breaking spaces to HTML entities, preventing filters like `retconRemoveEmpty` from working correctly in all cases [#70](https://github.com/mmikkel/Retcon-Craft/issues/70)

## 3.2.0 - 2024-06-28
### Improved  
- Improves support for Imager X named transforms for the `retconSrcset` filter. [#69](https://github.com/mmikkel/Retcon-Craft/issues/69)

## 3.1.1 - 2024-06-14
### Fixed
- Fixed a compatibility issue with CKEditor 4.1, where applying a Retcon filter to a CKEditor field's output would cause nested entries to not render 

## 3.1.0 - 2024-06-10
### Added  
- Adds support for the body element selector (i.e. `'body'`), which can be used in conjunction with the child combinator to only select top-level nodes (e.g. `'body > p'`). [#68](https://github.com/mmikkel/Retcon-Craft/issues/68) 

## 3.0.0 - 2024-03-28
### Added
- Added Craft 5.0 compatibility
### Changed
- Deprecated the `craft.retcon` variable
