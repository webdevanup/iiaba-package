# Changelog
All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](http://keepachangelog.com/)
and this project adheres to [Semantic Versioning](http://semver.org/).

<!--
### Added for new features.
### Changed for changes in existing functionality.
### Deprecated for once-stable features removed in upcoming releases.
### Removed for deprecated features removed in this release.
### Fixed for any bug fixes.
### Security to invite users to upgrade in case of vulnerabilities.
-->

## [Unreleased]

### Added
- add WordPress custom post type Source
- add status report print out
- add contributor role to newly created migrate user
- Extend vip-cli if exists

### Changed
- use underscores for original meta

### Fixed
- clean up count query

## 0.9.2 - 2023-09-20

### Fixed
- php 8.1 / 8.2 deprecations / notices

## 0.9.1 - 2023-07-17

### Changed
- Add post id to skip message

## 0.9.0 - 2023-07-12

### Changed
- wordpress xml source improvements

## 0.8.0 - 2023-07-10

### Changed
- Update flag accepts date for overwrite protection

## 0.7.2 - 2023-04-18

### Fixed
- remove var dump

## 0.7.1 - 2023-04-11

### Added
- add count only queries for faster status
- add filter to file import to locate file

## 0.7.0 - 2022-07-26

### Changed
- select only _wp_attached_file in attachment url lookup
- add distinct option for terms


## 0.6.1 - 2022-01-11

### Fixed
- fixed php 8.x warnings/notices

## 0.6.0 - 2021-12-23

### Added
- Add WordpressXML Source to base
- New custom database table to hold original source data
- Add Action hooks for start/end/process_row 
- Add VIP CLI wrapper for calling stop_the_insanity()
- Add WP CLI Progress bar for simple output
- Add D7 Entity Reference field schema
- Add source_key_prefix to avoid id colisions with multiple sources

### Fixed
- Attachement conditional for importing files

## 0.5.0 - 2021-10-12

### Added
- added D7 custom_table field type

## 0.4.0 - 2021-06-24

### Added
- Add link_field and field_collection field types

### Changed
- Shorten post titles on progress output

### Fixed
- Quote string keys for mapping lookup

## 0.3.0 - 2021-03-30

### Added
- Added CLI Status view with imported counts.
- Added Drupal 8 Sources.
- Added Migrate User class to create a user as default migration user.
- Moved Redirect Mapping to base.

### Changed
- Don't import draft or trash - XML imports

## 0.2.0 - 2020-07-16

### Added
- Added initialized function on MapInterface to prevent unnecessary cache clearing if the Map was already initialized.

### Changed
- Changed MigrationBase field mapping signature to accept a MapInterface instead of a MigrationInterface.

## 0.1.1

## 0.1.0

__Initial Commit__