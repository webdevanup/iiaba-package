# Wikit Core

wdgdc/wikit-core should ideally be used as a dependency of wdgdc/wikit-app. If wdgdc/wikit-app is not being used, it should be required by wdgdc/wikit-theme.

If installing it directly as part of a composer project use the packagist.wdg.co repository:

```sh
composer require wdgdc/wikit-core --repository=https://packagist.wdg.co
```

## Features

Nothing is automatically initialized excerpt enqueuing the wdg.components library in the block editor. See the src directory for classes that can be initialized.

### Big Features

* Breadcrumbs structure and functions
* FocalPoints on images in the media library
* Protected media files
* PostTypeTerms for creating post types that double as taxonomies
* SVG uploads and sprite functions
* Custom taxonomy control (checkbox, radio) via the block_control_editor taxonomy property
* wdg.components gutenberg library of components that should have been in wp components
* Pinned Posts

### Less Big Features

* AdminDashboard cleanup
* Custom AdminMenu
* Clean WP Head of un-necessary bloat
* PHP functions to output to the browser console
* Disabling Emoji
* Custom login customizer panel
* Menu Item Fields
* Classic Menus Modeling without a walker
* Pagination modeling without generating HTML
* date and year shortcodes
* related reading functions
