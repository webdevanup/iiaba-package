# WDG Facets

WDG Facets is a package to provide facets to WP_Query in a consistently structured manner from a variety of sources by abstracting them through a common API.

**Note: there is not currently a UI implemntation. UI needs tend to differ depending on the design but a reference implementation can be found in wikit-theme advanced-query block.**

## Supported Providers

* [Native](./docs/native.md)
* [Pantheon SolrPower](./docs/solr-power.md)
* [WPVIP Enterprise Search](./docs/enterprise-search.md)


### Provider Interface

See the [ProviderInterface](src/Provider/ProviderInterface.php) for how to implement the methods for a new facet provider

## Supported Sources

* Native (WP_Query)
* SearchWP

###

## Installation

### Composer

To use this within an composer enabled project (e.g. wikit-app):

```sh
composer require wdgdc/wikit-facets --repository=https://packagist.wdg.co
```

### Initialize composer package

Initialize it within wikit-app:

```php
$app = App::instance();

$app->add_feature( 'facets', new \WDG\Facets\Facets() );
```

Initialize it standalone:

```php
\WDG\Facets\Facets::instance();
```

### WordPress Plugin

Use it as a WordPress plugin:

Download the most recent release and upload it to your plugins directory and activate. It will attempt to auto-configure your provider.

## Usage

The way to mark a query as facetable is to provide the 'facets' query arg.

```php
$query = new WP_Query(
	[
		'post_type' => [
			'post',
			'news',
		],
		's' => '60% of the time, it works, every time',
		'facets' => [
			'category',
			'post_tag',
			'topic',
		]
	]
);
```

After executing the query, there is a facets property of the query itself that contains the results keyed by the facet slug.

```php
print_r( $query->facets );
```

```php
Array
(
    [post_type] => WDG\Facets\Facet Object
        (
            [position:WDG\Facets\Facet:private] => 0
            [label] => Type
            [name] => post_type
            [filters] => Array
                (
                    [0] => WDG\Facets\FacetFilter Object
                        (
                            [id] => facet-post_type-news
                            [type] => post_type
                            [facet] => post_type
                            [value] => news
                            [label] => News
                            [name] => filter[post_type][]
                            [count] => 216
                            [active] =>
                        )
					...
                )

            [active] =>
        )

    [news_type] => WDG\Facets\Facet Object
        (
            [position:WDG\Facets\Facet:private] => 0
            [label] => News Type
            [name] => news_type
            [filters] => Array
                (
                    [0] => WDG\Facets\FacetFilter Object
                        (
                            [id] => facet-news_type-news
                            [type] => taxonomy
                            [facet] => news_type
                            [value] => news
                            [label] => News
                            [name] => filter[news_type][]
                            [count] => 139
                            [active] =>
                        )

                    [1] => WDG\Facets\FacetFilter Object
                        (
                            [id] => facet-news_type-press-release
                            [type] => taxonomy
                            [facet] => news_type
                            [value] => press-release
                            [label] => Press Release
                            [name] => filter[news_type][]
                            [count] => 68
                            [active] =>
                        )
					...
                )

            [active] =>
        )
```

### WDG\Facets\Facet

The WDG\Facets\Facet object is a data transfer object that implements the iterator interface to iterate over the filters.

Usage in a view:

```php
<?php if ! empty( $wp_query->facets ) : ?>
	<div class="facets">
		<?php foreach( $wp_query->facets as $facet_name => $facet ) : ?>
			<div class="facet facet--<?= esc_attr( $facet_name ); ?> facet--<?= esc_attr( $attributes['type'] ); ?>">
				<ol class="facets__facet-filters">
					<?php foreach ( $facet as $filter ) : ?>
						<li class="facets__facet-filter">
							<input
								type="<?= 'single' === $type ? 'radio' : 'checkbox'; ?>"
								id="<?= esc_attr( $filter->id . '-' . $filter->value ); ?>"
								name="<?= esc_attr( $filter->name ); ?>"
								value="<?= esc_attr( $filter->value ); ?>"
								autocomplete="off"
								<?php checked( $filter->active ); ?>
							>
							<label for="<?= esc_attr( $filter->id . '-' . $filter->value ); ?>">
								<?= esc_html( $filter->label ); ?>
							</label>
						</li>
					<?php endforeach; ?>
				</ol>
			</div>
		<?php endforeach; ?>
	</div>
<?php endif; ?>
```

### WDG\Facets\FacetFilter

This object contains the result of an individual filter within a facet in a provider independent format. See [FacetFilter.php](./src/FacetFilter.php) for the values available.

## Filters

**wdg/facets/query_var**

Allows customization of the query string key and

```php
add_filter( 'wdg/facets/query_var', fn() => 'custom_query_string' );
```

**wdg/facets/facets**

Specify custom facets for a particular query

```php
add_filter(
	'wdg/facets/facets',
	function( $facets, $query ) {
		if ( $query->is_main_query() && $query->is_search() ) {
			$facets[] = 'category';
		}

		return $faces;
	},
	10,
	2
);

```

**wdg/facets/auto**

Should all configured facets be automatically applied to the query

```php
add_filter(
	'wdg/facets/auto',
	function( $can_facet, $query ) {
		if ( $query->is_home() ) {
			$can_facet = false;
		}

		return $can_facet;
	}
);
```

**wdg/facets/sub_query**

Modifies the sub query before the native facets index

**wdg/facets/post_type**

Override configuration settings for enabling post type as a facet

```php
add_filter( 'wdg/facets/post_type', '__return_false' );
```

**wdg/facets/taxonomies**

Override configuration settings for enabling taxonomies as a facet

```php
// ensure category is never a facet
add_filter( 'wdg/facets/taxonomies', fn( $taxonomies ) => array_diff( $taxonomies, [ 'category' ] ) );
```

**wdg/facets/meta_keys**

Override configuration settings for enabling meta keys as a facet

```php
// add a private meta key as a facet
add_filter( 'wdg/facets/meta_keys', fn( $meta_keys ) => array_merge( $meta_keys, [ '_some_private_meta_key' ] ) );
```

## Examples

### Default initialization with native provider

```php
new \WDG\Facets\Facets();
```

### Default configuration with a custom query_var

```php
new \WDG\Facets\Facets(
	new \WDG\Facets\Provider\Native(
		[
			'query_var' => 'custom_query_var'
		]
	)
);
```

### Use with WPVIP Enterprise Search

```php
new WDG\Facets\Facets( '\WDG\Facets\Provider\EnterpriseSearch' );
```

### Use with Pantheon SolrPower

```php
new WDG\Facets\Facets( '\WDG\Facets\Provider\SolrPower' );
```

### Use with SearchWP source

```php
new WDG\Facets\Facets(
	'\WDG\Facets\Provider\Native',
	'\WDG\Facets\Source\SearchWP',
);
```

## WP CLI

There are a few WP CLI commands to manage the native index. This command will only appear if `\WDG\Facets\Provider\Native` is configured as the provider.

**wp wdg facets create**

Creates the index table

**wp wdg facets delete**

Drops the index table

**wp wdg facets index**

Index individual posts or the entire site in batch

**wp wdg facets stats**

Display the stats about the current state of the index

**wp wdg facets truncate**

Truncate the facets index table


## Development

### Integration Testing

#### Environment

A test suite environment can be installed by invoking `./bin/install-tests.sh` from the root of the repo. This will setup a clean testing environment from which to run the tests. The script does the following:

* Download WordPress
* Symlink this repo as an mu-plugin and install an mu-plugin loader file
* Install the WordPress database with content from an [export](./tests/wptest.xml) from [wptest.io](https://wptest.io)

The script relies on either environment variables or a .env file in the root of the repo. Here's a complete list of variables:

```sh
DB_USER=maria
DB_PASS=maria
DB_NAME=wordpress_tests
DB_HOST=localhost
WP_PATH="/var/www/wordpress-tests"
WP_TITLE="Wikit Facets Tests"
WP_USER="wikit_facets_tests"
WP_EMAIL="user@example.com"
```

#### Invocation

An phpunit integration script exists by invoking `composer phpunit`. This tests the database creation, drop, indexing process, and query results of the Native provider.

### Code Style

PHPCS is installed through composer and can be invoked with `composer phpcs .`. You can pass a directory or file instead of `.` to limit the scope of the linter.

`composer phpcbf .` can also be used to auto-fix fixable style errors.
