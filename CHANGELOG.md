# Retcon Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](http://keepachangelog.com/) and this project adheres to [Semantic Versioning](http://semver.org/).

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
