
=== Advanced Custom Fields: Font Awesome Field ===
Contributors: justinkruit, mattkeys
Tags: Advanced Custom Fields, ACF, Font Awesome, FontAwesome
Requires at least: 3.5
Tested up to: 6.9
Stable tag: 5.0.2
Requires PHP: 5.6
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

Adds a new 'Font Awesome Icon' field to the popular Advanced Custom Fields plugin.

== Description ==

Add a [Font Awesome](http://fontawesome.com/) icon field type to Advanced Custom Fields.

* Specify which FontAwesome icon sets to use (Solid, Regular, Light, Thin, Duotone, Duotone Sharp, Brands, and Custom Upload Icons) (Some features only available with FontAwesome 5.x or 6.x icons)
* Create your own custom filtered list of FontAwesome icons to use with your fields. Use different custom icon sets with different fields
* Returns Icon Element, Icon Class, Icon Unicode, or an Object including the element, class, unicode value, and SVG data (if applicable)
* Optionally enqueues Font Awesome in footer where needed (when a FontAwesome field is being used on the page))
* Integrates with the [FontAwesome GraphQL](https://docs.fontawesome.com/apis/graphql) for loading your [FontAwesome Kits](https://docs.fontawesome.com/web/setup/use-kit), searching for icons, and loading the latest version of FontAwesome

Note: It is recommended to let this plugin enqueue the latest version of Font Awesome on your front-end; or include the latest version yourself using your [FontAwesome Kit](https://docs.fontawesome.com/web/setup/use-kit); so that available icons in the admin area will be displayed properly on your sites front-end.

= Compatibility =

This ACF field type is compatible with:
* ACF 5.7+
* FontAwesome 4.x, 5.x, 6.x and 7.x fonts

== Installation ==

Typical Installation:

1. Copy the `advanced-custom-fields-font-awesome` folder into your `wp-content/plugins` folder
2. Activate the Font Awesome plugin via the plugins admin page
3. Create a new field via ACF and select the Font Awesome type

Theme Installation:

If you are a theme author who wants to bundle this plugin with your theme, starting in version 4.0.1 this plugin has been designed to work from within a theme directory.

It is still more ideal to use something like [TGM Plugin Activation](http://tgmpluginactivation.com/) to include required/recommended plugins with your theme. However if you choose to bundle this plugin please follow these instructions:

1. Copy the 'advanced-custom-fields-font-awesome' folder into your theme folder. Preferably into a path like 'includes/advanced-custom-fields-font-awesome'
2. Include the plugin from your theme functions.php. Example: `include_once( get_stylesheet_directory() . '/includes/advanced-custom-fields-font-awesome/acf-font-awesome.php' );
`

***Plugins included this way are not able to receive regular plugin updates. It is up to theme developer to instead release these plugin updates to their users.***

However this plugin does attempt to let the user know if they are on an out of date version of the plugin. Out of date theme installations show an admin notification message at the top of the WordPress admin plugins page. The message is as follows:

> There is a new version of Advanced Custom Fields: Font Awesome available. Installed Version: {current_version}, Latest Version: {latest_version}
> 
> It looks like this plugin is bundled with your theme: ({theme_name}) and is not able to receive updates. It is recommended that you contact  your theme author for updates. Alternatively you can install this plugin through the WordPress Plugin Repository to get the latest version.

If the user installs this plugin from the plugin repository, that version of the plugin will override the one installed in their theme directory.

This notification message is filterable so you can customize it to better represent how you handle plugin updates for your theme users. See example below:

    function my_acffa_update_message( $out_of_date_message, $current_version, $latest_version ) {
    	// Customize the message here
    	return $out_of_date_message;
    }
    add_filter( 'ACFFA_theme_install_update_message', 'my_acffa_update_message', 10, 3 );


== Optional Configuration ==

=== Filters ===

* **ACFFA_always_enqueue_fa**: Return true to always enqueue FontAwesome on the frontend, even if no ACF FontAwesome fields are in use on the page. This will enqueue FontAwesome in the header instead of the footer.
* **ACFFA_admin_enqueue_fa**: Return false to stop enqueueing FontAwesome in the admin area. Useful if you already have FontAwesome enqueued by some other means.
* **ACFFA_get_icons**: (Applies to FontAwesome 4.x and 5.x icons only) Filter the array of icons and icon details loaded from the database
* **ACFFA_get_fa_url**: Filter the URL used for enqueuing FontAwesome in the frontend and admin areas of the site.
* **ACFFA_override_major_version**: Filter to manually set the 'major' version of FontAwesome to load (accepts either 4, 5, or 6). NOTE: This filter must be registered before any calls to ACF get_field() function are made.
* **ACFFA_fa_api_key**: Filter to programmatically set the FontAwesome API key.
* **ACFFA_fa_kit_token**: Filter to programmatically set the FontAwesome kit token.


== Screenshots ==

1. Set a default icon, and choose how you want icon data to be returned.
2. Searchable list of all icons, including large live preview

== Changelog ==

= 5.0.2 =
**Note: The next bigger update will have changes to the way icons are being selected when using FontAwesome 7.x to utilize new and future features from FontAwesome. Unfortunately this means we will be deprecating the “Icon Set Builder” feature to better align this plugin with the FontAwesome ecosystem. This change will only effect users using FontAwesome 7.x icons. The Icon Set Builder will continue to work as it has with previous versions of FontAwesome. We welcome your feedback on this change.**

* Bugfix: Added custom kit icons to Font Awesome 7.x icon set loading.
* Security: Sanitizing and escaping more outputs.

= 5.0.1 =
**Note: The next update will have changes to the way icons are being selected when using FontAwesome 7.x to utilize new and future features from FontAwesome. Unfortunately this means we will be deprecating the “Icon Set Builder” feature to better align this plugin with the FontAwesome ecosystem. This change will only effect users using FontAwesome 7.x icons. The Icon Set Builder will continue to work as it has with previous versions of FontAwesome. We welcome your feedback on this change.**

* Bugfix: Latest FA version will be properly checked and updated when saving settings.
* Bugfix: Field was not loading correctly when used within ACF block which used blockVersion 3.

= 5.0.0 =
* Added support for FontAwesome 7.x icons and new Icon Styles
* Updated FontAwesome GraphQL API integration to support FontAwesome 7.x icons and kits
* Because of the long list of Icon Sets when editing the field, the checkboxes have been replaced with a select2 multi-select field.

= 4.1.2 =
* Bugfix plugin textdomain can load too early if ACF fields are used before WordPress 'init'

= 4.1.1 =
* Bugfix icon sets not saving properly when editing FontAwesome icon field

= 4.1.0 =
* Added support for Duotone Sharp icons

= 4.0.9 =
* Bugfix certain SVG icons that were previously before 4.0.8 update not outputting properly.
* Bugfix certain custom icons that were previously selected before 4.0.8 update not outputting properly.
* Bugfix resolve potential PHP warning in admin post edit screens when downgrading from FontAwesome 6 to 5 icon sets.

= 4.0.8 =
* Bugfix php warning when returning some custom icons as an object
* Added support for returning the FontAwesome formatted SVG on custom icons and duotone icons instead of generating it in the plugin. Previously selected icons must be reselected to use this enhanced SVG.

= 4.0.7 =
* Added support for FontAwesome "Sharp" family icons
* Added support for user uploaded duotone icons in kits
* Updated icon initialization for better compatibility with REST API, and a variety of third party site builder plugins that integrate with ACF fields.
* Added filter (ACFFA_fa_api_key) to allow for the ability to programmatically set the FontAwesome API key.
* Added filter (ACFFA_fa_kit_token) to allow for the ability to programmatically set the FontAwesome kit token.
* Limited role of Select2 'dropdownCssClass' and 'containerCssClass' arguments to avoid conflicts with other plugins using older/non full feature versions of Select2

= 4.0.6 =
* Fixed php 8.2 related warning

= 4.0.5 =
* Fixed compatibility issue with ACF 6.1+ causing this plugins settings page to break
* Fixed bug with logic for theme 'bundling' of this plugin that caused issues loading assets in some environments

= 4.0.4 =
* Fixed bug where queries to the FontAwesome API were timing out for some users, causing issues with searching for icons in the backend

= 4.0.3 =
* Automatically refresh 4.x and 5.x icon caches when saving the settings
* Additional strings for translation

= 4.0.2 =
* Fixed bug where FontAwesome 5.x icon data was improperly loading FontAwesome 6.x data
* Removed some dependancies on jsDelivr for FontAwesome 5.x icon data

= 4.0.1 =
* Fixed bug where FontAwesome settings page would not properly load CSS/JS assets on internationalized WordPress installations.
* Added support for theme authors to bundle this plugin with a theme ( see install instructions )
* Increased 'fallback' FontAwesome 6 version number to match the 6.0.0 release

= 4.0.0 =
* Added support for FontAwesome 6 icons
* Added support for FontAwesome Kits including Custom Uploaded Icons
* Now utilizes the FontAwesome GraphQL API for quicker searching, fuzzy matching
* Added new compatibility mode to make updating from v4 and v5 icons easier

= 3.1.2 =
* Fixed compatibility issue with ACF 5.10+
* Fixed deprecated warnings in PHP 8, thanks to Levi Cole for the pull request

= 3.1.1 =
* Fixed bug where Duotone icons were not available to existing users of this plugin due to cached versions of the icons from before this plugin could properly parse the duotone icons.

= 3.1.0 =
* Added support for new FontAwesome Duotone icons (FontAwesome Pro subscription required)
* Added support for ACF 'acf/settings/capability' filter on the settings page

= 3.0.2 =
* Fixed bug causing PHP warning of undefined constant

= 3.0.1 =
* Fixed bug where the FontAwesome field would not successfully register on sites which force ACF to initialize itself early (usually by calling get_field function in the theme functions.php file)

= 3.0.0 =
* NOTE: When upgrading from a previous version of this plugin, the FontAwesome 'major version' will remain at v4 to stay compatible with existing integrations. *HOWEVER* if you are upgrading from a very old version of this plugin, it may not be possible to detect the former installation, and you will need to manually configure this plugin to use FontAwesome v4 in the new settings admin area.
* Added support for new FontAwesome 5.x free and pro icon sets
* Added new 'custom icon set' builder which allows FontAwesome ACF fields to be created with a limited set of icons individually selected from the full list of FontAwesome icons. Example: Create a custom icon set limited to just the social media brand icons
* Added new field options to to limit which of the icon sets ( Brands, Regular, Lite, Solid ) you want to allow in the field (applies to FontAwesome v5 only)
* Adding new FontAwesome Settings admin menu under the ACF primary menu area for global configuration options.
* Page load performance improvements (don't load icons in field constructor)
* Removing support for ACF v4 now that v5 is out with a free version

= 2.1.2 =
* Fixed bug where ACFFA_get_icons filter was not used in wp-admin area when retrieving icons.

= 2.1.1 =
* Fixed bug effecting any users who did not update to 2.1.0 before jsDelivr added FontAwesome 5.x to their CDN, where the 5.x icons would get loaded and break functionality

= 2.1.0 =
* Preventing any automatic updates to version 5.x of FontAwesome that could break plugin functionality and result in broken icons on sites currently using 4.x FontAwesome icons.

= 2.0.9 =
* Fixed bug effecting null value fields created in 1.x of this plugin would return a string of 'null' instead of boolean false when used in 2.x versions of this plugin.

= 2.0.8 =
* Fixed bug where fields marked to 'allow null' in acf v5 did not show the (x) to remove the selected option on the field

= 2.0.7 =
* Fixed bug with FA fields assigned to menu items + further refactoring of JS for ACF v5

= 2.0.6 =
* Refactored JS to simplify codebase and fix bugs where Chosen or Select2 fields would not initialize in a variety of field/sub-field configurations and display options.

= 2.0.5 =
* Fixed bug with ACF tabs + FontAwesome fields where Chosen/Select2 would not initialize beyond the first tab shown

= 2.0.4 =
* Added filter (ACFFA_always_enqueue_fa) to allow FontAwesome to always be enqueued on your sites frontend, even if no ACF FontAwesome fields are in use on the page.

= 2.0.3 =
* Fixed bug where a field set to return 'Icon Object' would instead return an array

= 2.0.2 =
* Fixed bug effecting Select fields when used along with a Font Awesome field in a repeater (ACF v5)

= 2.0.1 =
* Fixed bug causing incompatibilities with ACF Clone fields (ACF v5)
* Fixed bug where default icons could not be unselected when creating FontAwesome fields (ACF v5)

= 2.0.0 =
* Total rewrite of plugin to simplify codebase and better adhere to WordPress and Advanced Custom Fields coding standards and best practices
* Added option to disable the larger icon preview displayed with the FontAwesome select fields

= 1.7.4 =
* Fixed incompatibilities with latest ACF 5 + Select2 v4.x

= 1.7.3 =
* Updated Better Font Awesome library to latest version
* Updated 'fallback' ACF font to 4.6.3
* Bugfix overly broad CSS rules effecting non font awesome field groups
* Bugfix select2 not loading on new fontawesome icons until after saving fields

= 1.7.2 =
* Bugfix PHP Notice when trying to access property of 'null' value

= 1.7.1 =
* Updated Better Font Awesome Library for better compatibility with Better Font Awesome plugin
* Changed ACF 4/5 detection method for better integration with Better Font Awesome plugin
* Bugfix wrong preview icon appearing in ACF custom field creator area

= 1.7 =
* Added ability to select no icon by default
* Better handling of 'null' or 'no selection' items
* Fixed bug where default icon would not display in admin area if 'unicode' return type was selected 

= 1.6.4 =
* Misc JS performance improvements
* Fixed bug where select2 would not initialize on repeater items added mid-rows (using the plus icon at the end of a repeater row)

= 1.6.3 =
* Fixed asset path errors when including this add-on from a theme instead of the plugins directory

= 1.6.2 =
* Rolling back changes from 1.6.1 after a number of bugs were reported. Incompatibility issues with Better Font Awesome have been corrected in that plugins code.

= 1.6.1 =
* Addressing incompatibility issues between this plugin and the Better Font Awesome plugin

= 1.6 =
* Misc fixes to JS to properly target ACF fields in the DOM (based on changes to the ACF structure). This should resolve issues with duplicate, or missing select2 fields when picking font awesome icons.

= 1.5 =
* New Feature: Integrated with the [Better Font Awesome Library](https://github.com/MickeyKay/better-font-awesome-library) to automatically use the latest version of the Font Awesome icons

= 1.4 =
* Updated included FontAwesome to version 4.2

= 1.3 =
* Added support for ACF version 5.x

= 1.2 =
* Added support for new icons in FontAwesome 4.1
* Updated included FontAwesome to version 4.1

= 1.1.2 =
* Fixed overly specific JS selector which was causing font preview icons to not load when used on taxonomy term pages

= 1.1.1 =
* Fixed JS error which was breaking conditional field select boxes

= 1.1.0 =
* Added support for use in repeater fields
* Added support for use in flexible content fields
* Added live icon preview to field creation screen
* Fixed various bugs with Select2 initialization on dynamically added fields

= 1.0.0 =
* Initial Release.

== Upgrade Notice ==

= 4.1.2 =
* Bugfix plugin textdomain can load too early if ACF fields are used before WordPress 'init'

= 4.1.1 =
* Bugfix icon sets not saving properly when editing FontAwesome icon field

= 4.1.0 =
* Added support for Duotone Sharp icons

= 4.0.9 =
* Bugfix certain SVG icons that were previously before 4.0.8 update not outputting properly.
* Bugfix certain custom icons that were previously selected before 4.0.7 update not outputting properly.
* Bugfix resolve potential PHP warning in admin post edit screens when downgrading from FontAwesome 6 to 5 icon sets.

= 4.0.8 =
* Bugfix php warning when returning some custom icons as an object
* Added support for returning the FontAwesome formatted SVG on custom icons and duotone icons instead of generating it in the plugin. Previously selected icons must be reselected to use this enhanced SVG.

= 4.0.7 =
* Added support for FontAwesome "Sharp" family icons
* Added support for user uploaded duotone icons in kits
* Updated icon initialization for better compatibility with REST API, and a variety of third party site builder plugins that integrate with ACF fields.
* Added filter (ACFFA_fa_api_key) to allow for the ability to programmatically set the FontAwesome API key.
* Added filter (ACFFA_fa_kit_token) to allow for the ability to programmatically set the FontAwesome kit token.
* Limited role of Select2 'dropdownCssClass' and 'containerCssClass' arguments to avoid conflicts with other plugins using older/non full feature versions of Select2

= 4.0.6 =
* Fixed php 8.2 related warning

= 4.0.5 =
* Fixed compatibility issue with ACF 6.1+ causing this plugins settings page to break
* Fixed bug with logic for theme 'bundling' of this plugin that caused issues loading assets in some environments

= 4.0.4 =
* Fixed bug where queries to the FontAwesome API were timing out for some users, causing issues with searching for icons in the backend

= 4.0.3 =
* Automatically refresh 4.x and 5.x icon caches when saving the settings
* Additional strings for translation

= 4.0.2 =
* Fixed bug where FontAwesome 5.x icon data was improperly loading FontAwesome 6.x data
* Removed some dependancies on jsDelivr for FontAwesome 5.x icon data

= 4.0.1 =
* Fixed bug where FontAwesome settings page would not properly load CSS/JS assets on internationalized WordPress installations.
* Added support for theme authors to bundle this plugin with a theme ( see install instructions )
* Increased 'fallback' FontAwesome 6 version number to match the 6.0.0 release

= 4.0.0 =
* Added support for FontAwesome 6 icons
* Added support for FontAwesome Kits including Custom Uploaded Icons
* Now utilizes the FontAwesome GraphQL API for quicker searching, fuzzy matching
* Added new compatibility mode to make updating from v4 and v5 icons easier

