=== National Bank of Greece Payment Gateway for WooCommerce ===
Contributors: mpbm23, emspacegr, princeofabyss
Tags: ecommerce, woocommerce, payment gateway, nbg, national bank of greece
Requires at least: 4.0
Tested up to: 5.0.3
Stable tag: 1.0.2
Requires PHP: 5.2.4
License: GPLv3 or later
License URI: https://www.gnu.org/licenses/gpl-3.0.html

Adds National Bank of Greece as a Payment Gateway for WooCommerce

== Description ==

Adds National Bank of Greece as a Payment Gateway for WooCommerce

= Features =

* Uses the HPS method to authorize payments on behalf of merchants
* Uses the 3D-Secure verification as an additional layer of protection during transactions
* Immediate or pre-authorized capture of funds
* Configurable purchase description for the 3D-Secure verification
* Fully configurable installments option
* Accreditation or production mode for test or live operation
* Configurable return page after successful transactions
* WPML-compatible for multilingual websites

== Installation ==

There are three different ways to install `National Bank of Greece Payment Gateway for WooCommerce`, as with any other wordpress.org plugin:

= Using the WordPress Dashboard =

1. Log in to your WordPress `Dashboard`
1. Navigate to the `Plugins` menu and click `Add New`
1. In the `Search plugins...` field, type *National Bank of Greece Payment Gateway for WooCommerce* and press `Enter`
1. Once you have found the plugin, you can install it by simply clicking `Install Now`
1. Activate the plugin in the `Plugins` menu

= Uploading in WordPress Dashboard =

1. Download the latest version of the [plugin](https://wordpress.org/plugins/woo-payment-gateway-national-bank-greece)
1. Navigate to the `Plugins` menu and click `Add New`
1. Navigate to the `Upload` area
1. Select the .zip file downloaded in step 1 from your computer
1. Click `Install Now`
1. Activate the plugin in the `Plugins` menu

= Using FTP =

1. Download the latest version of the [plugin](https://wordpress.org/plugins/woo-payment-gateway-national-bank-greece)
1. Unzip the .zip file, which will extract the plugin directory to your computer
1. Upload the plugin directory to the `/wp-content/plugins` directory in your web server
1. Activate the plugin in the `Plugins` menu

Once the plugin is installed and activated:

1. Navigate to `WooCommerce > Settings` screen and then to `Payments` tab
1. Click the `Set up` button of the `National Bank of Greece Payment Gateway` added by the plugin
1. Add the `vTID (Client)` and `Processing Password` credentials provided by the bank
1. Set up a few more settings according to your needs and the plugin is ready for use

== Frequently Asked Questions ==

= Does it work? =

Yes

= How much does it cost? =

It's free, as it should be

== Screenshots ==

1. A view of the Payment Gateway added by the plugin in WooCommerce > Settings > Payments
2. The Settings screen of the plugin
3. Translation of public strings using any WPML-compatible plugin (Polylang used here)

== Changelog ==

= 1.0.2 =
Up-to-date and works fine with WordPress 5.0.3+ and WooCommerce 3.5.2+. Updated by Petros Kalpitzis.

= 1.0.1 =
Renamed SimpleXML element to avoid re-declaring errors

= 1.0.0 =
Initial release