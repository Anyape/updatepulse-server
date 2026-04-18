# UpdatePulse Server - Run your own update server

* [UpdatePulse Server - Run your own update server](#updatepulse-server---run-your-own-update-server)
	* [Introduction](#introduction)
		* [Overview](#overview)
		* [Special Thanks](#special-thanks)
		* [Compatibility](#compatibility)
		* [Screenshots](#screenshots)
			* [Packages Overview](#packages-overview)
			* [Version Control Systems](#version-control-systems)
			* [Licenses](#licenses)
			* [API \& Webhooks](#api--webhooks)
			* [Client - plugin screens](#client---plugin-screens)
			* [Client - theme screens](#client---theme-screens)
			* [Client - updates screen](#client---updates-screen)
	* [User Interface](#user-interface)
		* [Packages Overview](#packages-overview-1)
		* [Version Control Systems](#version-control-systems-1)
		* [Licenses](#licenses-1)
		* [API \& Webhooks](#api--webhooks-1)
	* [Performances](#performances)
		* [Benchmark](#benchmark)
		* [Update API](#update-api)
		* [Public License API](#public-license-api)
	* [Help](#help)
		* [Registering packages with a Version Control System](#registering-packages-with-a-version-control-system)
		* [Provide updates with UpdatePulse Server - packages requirements](#provide-updates-with-updatepulse-server---packages-requirements)
		* [Scheduled tasks optimisation](#scheduled-tasks-optimisation)
		* [Requests optimisation](#requests-optimisation)
		* [More help...](#more-help)


Developer documentation:
- [Packages](https://github.com/anyape/updatepulse-server/blob/main/docs/packages.md)
- [Licenses](https://github.com/anyape/updatepulse-server/blob/main/docs/licenses.md)
- [Miscellaneous](https://github.com/anyape/updatepulse-server/blob/main/docs/misc.md)
- [Generic Updates Integration](https://github.com/anyape/updatepulse-server/blob/main/docs/generic.md)
- [UpdatePulse Server Integration](https://github.com/Anyape/updatepulse-server-integration) repository

## Introduction

UpdatePulse Server allows developers to provide updates for their own plugins & themes not hosted on `wordpress.org`, or for generic packages unrelated to WordPress altogether. It also allows to control the updates with license.
Package updates may be either uploaded directly, or hosted in a Version Control System, public or private, with the latest version of packages stored either locally or in the Cloud. It supports Bitbucket, Github, Gitlab, and self-hosted installations of Gitlab for package updates; S3 compatible service providers are supported for package storage.

**The `main` branch contains a beta version of UpdatePulse Server.**
**The `dev` branch contains an alpha version of UpdatePulse Server.**
**For stable versions, please use releases, or the [WordPress Repository](https://wordpress.org/plugins/updatepulse-server/).**  

### Overview

This plugin adds the following major features to WordPress:

* **Packages Overview:** manage package updates with a table showing Package Name, Version, Type, File Name, Size, File Modified and License Status; includes bulk operations to delete, download and change the license status, and the ability to delete all the packages. Upload updates from your local machine to UpdatePulse Server, or let the system to automatically download them to UpdatePulse Server from a Version Control System. Store packages either locally, or in the Cloud with an S3 compatible service. Packages can also be managed through their own API.
* **Version Control Systems:** configure the Version Control Systems of your choice (Bitbucket, Github, Gitlab, or a self-hosted installation of Gitlab) with secure credentials and a branch name where the updates are hosted; choose to check for updates recurringly, or when receiving a webhook notification. UpdatePulse Server acts as a middleman between your reposiroty, your udpates storage (local or Cloud), and your clients.
* **Licenses:** manage licenses with a table showing ID, License Key, Registered Email, Status, Package Type, Package Slug, Creation Date, and Expiration Date; add and edit them with a form, or use the API for more control. Licenses prevent packages installed on client machines from being updated without a valid license. Licenses are generated automatically by default and the values are unguessable (it is recommended to keep the default). When checking the validity of licenses an extra license signature is also checked to prevent the use of a license on more than the configured allowed domains.
* **Not limited to WordPress:** with a platform-agnostic API, updates can be served for any type of package, not just WordPress plugins & themes. Basic examples of integration with Node.js, PHP, bash, and Python are provided in the [documentation](https://github.com/anyape/updatepulse-server/blob/main/docs/generic.md).
* **API & Webhooks:** Use the Package API to administer packages (browse, read, edit, add, delete), and request for expirable signed URLs of packages to allow secure downloads. Use the License API to administer licenses (browse, read, edit, add, delete) and check, activate or deactivate licenses. Fire Webhooks to notify any URL of your choice of key events affecting packages and licenses. 

To connect their packages and UpdatePulse Server, developers can find integration examples [here](https://github.com/Anyape/updatepulse-server-integration):
* **Dummy Plugin:** a folder `dummy-plugin` with a simple, empty plugin that includes the necessary code in the `dummy-plugin.php` main plugin file and the necessary libraries in a `lib` folder.
* **Dummy Theme:** a folder `dummy-theme` with a simple, empty child theme of Twenty Seventeen that includes the necessary code in the `functions.php` file and the necessary libraries in a `lib` folder.
* **Dummy Generic:** a folder `dummy-generic` with a simple command line program written bash, Node.js, PHP, bash, and Python. Execute by calling `./dummy-generic.[js|php|sh|py]` from the command line. See `updatepulse-api.[js|php|sh|py]` for simple examples of the API calls.

In addition, requests to the various APIs are optimised with a customisable [Must Use Plugin](https://codex.wordpress.org/Must_Use_Plugins) automatically added upon install of UpdatePulse Server. The original file can be found in `updatepulse-server/optimisation/upserv-endpoint-optimizer.php`.  

### Special Thanks

A warm thank you to [Yahnis Elsts](https://github.com/YahnisElsts), the author of the [Plugin Update Checker](https://github.com/YahnisElsts/plugin-update-checker) library, without whom the creation of this plugin would not have been possible.  
Authorisation to use and modify these libraries freely (provided relevant licenses are included) has been graciously granted [here](https://github.com/YahnisElsts/wp-update-server/issues/37#issuecomment-386814776).

### Compatibility

Tested with PHP 8.x - may work with PHP 7.x versions for the most part, but it is not guaranteed

**Pull requests to solve any bugs, improve performance, and keep libraries up to date are welcome and highly encouraged.**  
**Requests to debug or troubleshoot specific setups will not be addressed.**

### Screenshots

Note: the screenshots are updated regularly, but the actual interface may vary slightly.

#### Packages Overview

<img src="https://anyape.com/resources/upserv/screenshots/upserv-page-202501302128.png" alt="Packages Overview" width="100%">

#### Version Control Systems

<img src="https://anyape.com/resources/upserv/screenshots/upserv-page-remote-sources-202501302128.png" alt="Version Control Systems" width="100%">

#### Licenses

<img src="https://anyape.com/resources/upserv/screenshots/upserv-page-licenses-202501302128.png" alt="Licenses" width="100%">

#### API & Webhooks

<img src="https://anyape.com/resources/upserv/screenshots/upserv-page-api-202501302128.png" alt="API & Webhooks" width="100%">

#### Client - plugin screens

<img src="https://anyape.com/resources/upserv/screenshots/admin_plugins.png" alt="Plugins" width="100%">
<img src="https://anyape.com/resources/upserv/screenshots/admin_plugins-2.png" alt="Plugin Details" width="100%">

#### Client - theme screens

<img src="https://anyape.com/resources/upserv/screenshots/admin_themes.png" alt="Themes" width="100%">
<img src="https://anyape.com/resources/upserv/screenshots/admin_themes-2.png" alt="Theme Details" width="100%">
<img src="https://anyape.com/resources/upserv/screenshots/admin_themes-3.png" alt="Theme License" width="100%">

#### Client - updates screen

<img src="https://anyape.com/resources/upserv/screenshots/admin_update-core.png" alt="Updates" width="100%">

## User Interface

UpdatePulse Server provides a user interface to manage packages, manage licenses, manage Version Control System connections, and to configure API & Webhooks.

### Packages Overview

This tab allows administrators to:
- View the list of packages currently available in UpdatePulse Server, with Package Name, Version, Type (Plugin or Theme), File Name, Size, File Modified (when the package file was changed on the file system) and License Status (if enabled)
- Download a package
- Delete a package
- Apply bulk actions on the list of packages (download, delete)
- Add a package (either by uploading it directly, or by registering it by pulling it from a configured Version Control System)
- Configure and test a Cloud Storage service
- Configure other packages-related settings - file upload, cache and logs max sizes.

The following settings are available:

Name                                | Type     | Description
----------------------------------- |:--------:| ------------------------------------------------------------------------------------------------------------------------------
Use Cloud Storage                   | checkbox | Check to use a Cloud Storage Service - S3 Compatible.<br>If it does not exist, a virtual folder `updatepulse-packages` will be created in the Storage Unit chosen for package storage.
Cloud Storage Access Key            | text     | The Access Key provided by the Cloud Storage service provider.
Cloud Storage Secret Key            | text     | The Secret Key provided by the Cloud Storage service provider.
Cloud Storage Endpoint              | text     | The domain (without `http://` or `https://`) of the endpoint for the Cloud Storage Service.
Cloud Storage Unit                  | text     | Usually known as a "bucket" or a "container" depending on the Cloud Storage service provider.
Cloud Storage Region                | text     | The region of the Cloud Storage Unit, as indicated by the Cloud Storage service provider.
Archive max size (in MB)            | number   | Maximum file size when uploading or downloading packages.
Cache max size (in MB)              | number   | Maximum size in MB for the `wp-content/updatepulse-server/cache` directory. If the size of the directory grows larger, its content will be deleted at next cron run (checked hourly). The size indicated in the "Force Clean" button is the real current size.
Logs max size (in MB)               | number   | Maximum size in MB for the `wp-content/updatepulse-server/logs` directory. If the size of the directory grows larger, its content will be deleted at next cron run (checked hourly). The size indicated in the "Force Clean" button is the real current size.

A button is available to send a test request to the Cloud Storage Service. The request checks whether the provider is reachable and if the Storage Unit exists and is writable.  
If it does not exist during the test, a virtual folder `updatepulse-packages` will be created in the Storage Unit chosen for package storage.  

### Version Control Systems

This tab allows administrators to configure how Remote Sources are handled with the following settings:

Name                          | Type      | Description
----------------------------- |:---------:| --------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------
Enable VCS                    | checkbox  | Enables this server to download packages from a Version Control System before delivering updates.<br/>Supports Bitbucket, Github and Gitlab.<br/>If left unchecked, zip packages need to be manually uploaded to `wp-content/plugins/updatepulse-server/packages`.
VCS URL                       | text      | The URL of the Version Control System where packages are hosted.<br/>Must follow the following pattern: `https://version-control-system.tld/username` where `https://version-control-system.tld` may be a self-hosted instance of Gitlab.<br/>Each package repository URL must follow the following pattern: `https://version-control-system.tld/username/package-slug/`; the package files must be located at the root of the repository, and in the case of WordPress plugins the main plugin file must follow the pattern `package-slug.php`.
Self-hosted VCS               | checkbox  | Check this only if the Version Control System is a self-hosted instance of Gitlab.
Packages branch name          | text      | The branch to download when getting remote packages from the Version Control System.<br/>If the VCS supports releases or tags, they will be prioritised over the branch name (release first, then tag, then branch).<br/>To bypass this behaviour, set the `PUC_FORCE_BRANCH` constant to `true` in `wp-config.php`.
VCS credentials               | text      | Credentials for non-publicly accessible repositories.<br/>In the case of Github and Gitlab, a Personal Access Token (PAT); in the case of Bitckucket, the Bitbucket account email and an API token with scopes formatted like `email@domain.com:API_TOKEN_WITH_SCOPE`.<br/>**WARNING: Keep these credentials secret, do not share them, and take care of renewing them before they expire!**
Use Webhooks                  | checkbox  | Check so that each repository of the Version Control System calls a Webhook when updates are pushed.<br>When checked, UpdatePulse Server will not regularly poll repositories for package version changes, but relies on events sent by the repositories to schedule a package download.<br>Webhook URL: `https://domain.tld/updatepulse-server-webhook/package-type/package-slug` - where `package-type` is the package type (`plugin`, `theme`, or `generic`) and `package-slug` is the slug of the package that needs updates.<br>Note that UpdatePulse Server does not rely on the content of the payload to schedule a package download, so any type of event can be used to trigger the Webhook.
Remote Download Delay         | number    | Delay in minutes after which UpdatePulse Server will poll the Version Control System for package updates when the Webhook has been called.<br>Leave at `0` to schedule a package update during the cron run happening immediately after the Webhook notification was received.
VCS Webhook Secret            | text      | Ideally a random string, the secret string included in the request by the repository service when calling the Webhook.<br>**WARNING: Changing this value will invalidate all the existing Webhooks set up on all package repositories.**<br>After changing this setting, make sure to update the Webhooks secrets in the repository service.
Remote update check frequency | select    | Only available in case Webhooks are not used - How often UpdatePulse Server will poll each Version Control System for package updates - checking too often may slow down the server (recommended "Once Daily").

A button is available to send a test request to the Version Control System. The request checks whether the service is reachable and if the request can be authenticated.  
Tests via this button are not supported for Bitbucket; if Bitbucket is used, testing should be done after saving the settings and trying to register a package in the Packages Overview tab.  

In case Webhooks are not used, the following actions are available to forcefully alter the packages schedules (maintenance, tests, etc):
- Clear all the scheduled remote updates
- Reschedule all the remote updates

### Licenses

This tab allows administrators to:
- Entirely enable/disable package licenses. **It affects all the packages with a "Requires License" license status delivered by UpdatePulse Server.**
- View the list of licenses currently stored by UpdatePulse Server, with License Key, Registered Email, Status, Package Type (Plugin or Theme), Package Slug, Creation Date, Expiration Date, ID
- Add a license
- Edit a license
- Delete a license
- Apply bulk actions on the list of licenses (delete, change license status)

### API & Webhooks

This tab allows administrators to configure:
- the Package API to administer packages (browse, read, edit, add, delete), request for expirable signed URLs of packages to allow secure downloads, and requests for tokens & true nonces.
- the License API to administer licenses (browse, read, edit, add, delete) and check, activate or deactivate licenses.
- the list of URLs notified via Webhooks, with the following available events:
	-  Package events `(package)`
		- Package added or updated `(package_update)`
		- Package deleted `(package_delete)`
		- Package downloaded via a signed URL `(package_download)`
	- License events `(license)`
		- License activated `(license_activate)`
		- License deactivated `(license_deactivate)`
		- License added `(license_add)`
		- License edited `(license_edit)`
		- License deleted `(license_delete)`

Available settings:

Name                                     | Description
---------------------------------------- | --------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------
Private API Keys (Package API)           | Multiple values; creating a key required a "Package Key ID" used to identify the package key.<br>Used to sign requests to obtain tokens for package administration operations (browse, read, edit, add, delete) and obtaining signed URLs of package.<br>The Package Key ID must contain only numbers, letters, `-` and `_`.<br>**WARNING: Keep these keys secret, do not share any of them with customers!**
IP Whitelist (Package API)               | Multiple values.<br>List of IP addresses and/or CIDRs of remote sites authorized to use the Package Private API (one IP address or CIDR per line).<br>Leave blank to accept any IP address (not recommended).
Private API Keys (License API)	         | Multiple values; creating a key required a "License Key ID" used to identify the package key.<br>Used to sign requests to obtain tokens for license administration operations (browse, read, edit, add, delete).<br>The License Key ID must contain only numbers, letters, `-` and `_`.<br>**WARNING: Keep these keys secret, do not share any of them with customers!**
IP Whitelist (License API)               | Multiple values.<br>List of IP addresses and/or CIDRs of remote sites authorized to use the License Private API (one IP address or CIDR per line).<br>Leave blank to accept any IP address (not recommended).
Webhook                                  | Multiple values; creating a Webhook requires a "Payload URL", a `secret-key`, and a list of events.<br>Webhooks are event notifications sent to arbitrary URLs at next cronjob (within 1 minute after the event occurs with a server cron configuration schedule to execute every minute). The event is sent along with a payload of data for third party services integration.<br>To allow the recipients to authenticate the notifications, the payload is signed with a `secret-key` secret key using the `sha256` algorithm; the resulting hash is made available in the `X-UpdatePulse-Signature-256` header.<br>**The `secret-key` must be at least 16 characters long, ideally a random string.**<br>The payload is sent in JSON format via a `POST` request.<br>**WARNING: Only add URLs you trust!**

## Performances

The performance insights below have been gathered on a cheap shared hosting server (less than $10 per month) with 256 MB of RAM, without any function hooked to UpdatePulse Server actions or filters, no Webhook, and with the MU Plugin endpoint optimizer active. Your Mileage May Vary depending on your server configuration and various optimisations you may add to your WordPress installation.  

The general conclusion is that most calls to the APIs are lighter and faster than loading the vaste majority of WordPress homepages (which is the page likely to be visited the most on any website) and lighter than a WordPress ajax call (extra optimisations and aggressive caching not considered).

### Benchmark

Performances loading the frontpage of a fresh WordPress installation with `dummy-theme`, an empty static frontpage and no active plugin:  

```
--- Start load tests ---
Time elapsed: 0.031
Total server memory used: 3.5 M / 4096M
Total number of queries: 11
Total number of scripts: 455
--- End load tests ---
```

### Update API
Performance can evaluated with the script `tests.php` located at the plugin's root. It is included only if the `wp-config.php` constant `UPSERV_ENABLE_TEST` is truthy.  
Below are the results of the tests performed on the Update API with the option "Use Cloud Storage" unchecked.  

Performances when a client is checking for updates (no license):

```
--- Start load tests ---
Time elapsed: 0.017
Total server memory used: 2.17 M / 4096M
Total number of queries: 5
Total number of scripts: 416
Server memory used to run the plugin: 118.57 K / 4096M
Number of queries executed by the plugin: 4
Number of included/required scripts by the plugin: 52
--- End load tests ---
```

Performances when a client is downloading an update (YMMV: downloading `dummy-plugin` - no license):

```
--- Start load tests ---
Time elapsed: 0.018
Total server memory used: 2.17 M / 4096M
Total number of queries: 6
Total number of scripts: 415
Server memory used to run the plugin: 118.57 K / 4096M
Number of queries executed by the plugin: 5
Number of included/required scripts by the plugin: 51
--- End load tests ---
```

Performances when a client is checking for updates (with license):

```
--- Start load tests ---
Time elapsed: 0.017
Total server memory used: 2.18 M / 4096M
Total number of queries: 7
Total number of scripts: 417
Server memory used to run the plugin: 118.57 K / 4096M
Number of queries executed by the plugin: 6
Number of included/required scripts by the plugin: 53
--- End load tests ---
```

Performances when a client is downloading an update (YMMV: downloading `dummy-plugin` - with license):

```
--- Start load tests ---
Time elapsed: 0.029
Total server memory used: 2.18 M / 4096M
Total number of queries: 7
Total number of scripts: 417
Server memory used to run the plugin: 118.57 K / 4096M
Number of queries executed by the plugin: 6
Number of included/required scripts by the plugin: 53
--- End load tests ---
```

### Public License API
Performance can evaluated with the script `tests.php` located at the plugin's root. It is included only if the `wp-config.php` constant `UPSERV_ENABLE_TEST` is truthy.  

Performances when a client is activating/deactivating a bogus license key:

```
--- Start load tests ---
Time elapsed: 0.019
Total server memory used: 2.44 M / 4096M
Total number of queries: 4
Total number of scripts: 412
Server memory used to run the plugin: 395.28 K / 4096M
Number of queries executed by the plugin: 3
Number of included/required scripts by the plugin: 39
--- End load tests ---
```

Performances when a client is activating a license key:
```
--- Start load tests ---
Time elapsed: 0.025
Total server memory used: 2.54 M / 4096M
Total number of queries: 9
Total number of scripts: 423
Server memory used to run the plugin: 460.91 K / 4096M
Number of queries executed by the plugin: 8
Number of included/required scripts by the plugin: 50
--- End load tests ---
```

Performances when a client is deactivating a license key:
```
--- Start load tests ---
Time elapsed: 0.029
Total server memory used: 2.54 M / 4096M
Total number of queries: 9
Total number of scripts: 423
Server memory used to run the plugin: 461.04 K / 4096M
Number of queries executed by the plugin: 8
Number of included/required scripts by the plugin: 50
--- End load tests ---
```

## Help

The following can also be found under the "Help" tab of the UpdatePulse Server admin page.  

### Registering packages with a Version Control System

Registering a package is possible with the following methods:
- \[simple\] using the "Register a package using a VCS" feature in the "Packages Overview" tab of UpdatePulse Server
- \[simple\] triggering a webhook from a VCS already added to UpdatePulse Server  
Webhook URL: `https://domain.tld/updatepulse-server-webhook/package-type/package-slug` - where `package-type` is the package type (`plugin`, `theme`, or `generic`) and `package-slug` is the slug of the package to register.
- \[advanced\] calling `wp updatepulse download_remote_package <package-slug> <plugin|theme|generic>` in the [command line](https://github.com/anyape/updatepulse-server/blob/main/docs/misc.md#wp-cli), with the VCS-related parameters corresponding to a VCS configuration saved in UpdatePulse Server
- \[expert\]calling the [upserv_download_remote_package](https://github.com/anyape/updatepulse-server/blob/main/docs/packages.md#upserv_download_remote_package) method in your own code, with the VCS-related parameters corresponding to a VCS configuration saved in UpdatePulse Server
- \[expert\]calling the `add` method of the [package API](https://github.com/anyape/updatepulse-server/blob/main/docs/packages.md), with the VCS-related parameters corresponding to a VCS configuration saved in UpdatePulse Server present in the request payload


### Provide updates with UpdatePulse Server - packages requirements

To link your packages to UpdatePulse Server, and optionally to prevent users from getting updates of your ppackages without a license, your packages need to include some extra code.  

For plugins, and themes, it is fairly straightforward:
- Add a `lib` directory with the `plugin-update-checker` and `updatepulse-updater` libraries to the root of the package as provided in `dummy-[plugin|theme]`; `updatepulse-updater` can be customized as you see fit, but `plugin-update-checker` should be left untouched.
- Add the following code to the main plugin file (for plugins) or in the `functions.php` file (for themes) :
```php
/** Enable updates
 * Replace `$prefix_` in `$prefix_updater` variable to a unique string for your package.
 * Replace vX_X with the version of the UpdatePulse Updater you are using.
 **/
require_once __DIR__ . '/lib/updatepulse-updater/class-updatepulse-updater.php';

$prefix_updater = new UpdatePulse_Updater(
	wp_normalize_path( __FILE__ ),
	0 === strpos( __DIR__, WP_PLUGIN_DIR ) ? wp_normalize_path( __DIR__ ) : get_stylesheet_directory()
);
```
- Optionally add headers to the main plugin file or to the theme's `style.css` file to enable license checks:
```text
Require License: yes
Licensed With: another-plugin-or-theme-slug
```
The "Require License" header can be `yes`, `true`, or `1`: all other values are considered as `false`; it is used to enable license checks for your package.  
The "Licensed With" header is used to link packages together (for example, in the case of an extension to a main plugin the user already has a license for, if this header is present in the extension, the license check will be made against the main plugin). It must be the slug of another plugin or theme that is already present in your UpdatePulse Server.  
- Add a `updatepulse.json` file at the root of the package with the following content - change the value of `"server"` to your own (required):
```json
{
   "server": "https://server.domain.tld/"
}
```
- Connect UpdatePulse Server with your repository and register your package, or manually upload your package to UpdatePulse Server.

For generic packages, the steps involved entirely depend on the language used to write the package and the update process of the target platform.  
You may refer to the documentation found [here](https://github.com/anyape/updatepulse-server/blob/main/docs/generic.md).

Dummy packages are available in the [UpdatePulse Server Integration](https://github.com/Anyape/updatepulse-server-integration) repository.

Unless "Enable VCS" is checked in "Version Control Systems", you need to manually upload the packages zip archives (and subsequent updates) in `wp-content/updatepulse-server/packages` or `CloudStorageUnit://updatepulse-packages/`.  A package needs to be a valid generic package, or a valid WordPress plugin or theme package, and in the case of a plugin the main plugin file must have the same name as the zip archive. For example, the main plugin file in `package-slug.zip` would be `package-slug.php`.  

### Scheduled tasks optimisation

By default, UpdatePulse Server uses the WordPress cron system to schedule tasks. This means that the tasks are executed when a visitor accesses the site, which can lead to delays in the execution of the tasks.  
This is not optimal: the website where UpdatePulse Server is installed is very likely not meant to be visited by users, and the tasks should be executed as close to the scheduled time as possible..

To make sure that the tasks are executed on time, it is recommended to set up a true cron job by [hooking WP-Cron Into the System Task Scheduler](https://developer.wordpress.org/plugins/cron/hooking-wp-cron-into-the-system-task-scheduler/).

For more advanced scheduling, it is recommended to use the [Action Scheduler](https://wordpress.org/plugins/action-scheduler/) plugin.  
Simply install and activate the plugin, and UpdatePulse Server will automatically use it to schedule tasks instead of the default core scheduler.

### Requests optimisation

When the remote clients where plugins, themes, or generic packages are installed send a request to check for updates, download a package or check or change license status, WordPress where UpdatePulse Server is installed is also loaded, with its own plugins and themes.  
This is suboptimal: the request should be handled as quickly as possible, and the WordPress core should be loaded as little as possible.

To solve this, the Must-Use Plugin file `upserv-default-optimizer.php` is automatically copied to `/wp-content/mu-plugins/upserv-default-optimizer.php` upon activating UpdatePulse Server.  
It runs before everything else, and offers mechanisms to prevent WordPress core from executing beyond what is strictly necessary.  
This file has no effect on other plugins activation status, has no effect when UpdatePulse is deactivated, and is automatically deleted when UpdatePulse Server is uninstalled.

Aside from the default optimizer, the [UpdatePulse Server Integration](https://github.com/Anyape/updatepulse-server-integration) repository contains more production-ready Must-Use Plugins developers can download and add to their UpdatePulse Server installation.  
Contributions via pull requests are welcome.

To alter the behaviour of the optimiser, see the `upserv_mu_optimizer_*` filters in [Miscellaneous](https://github.com/anyape/updatepulse-server/blob/main/docs/misc.md).

### More help...

For more help on how to use UpdatePulse Server, please open an issue - bugfixes are welcome via pull requests, detailed bug reports with accurate pointers as to where and how they occur in the code will be addressed in a timely manner, and a fee will apply for any other request (if they are addressed).  
If and only if you found a security issue, please contact `updatepulse@anyape.com` with full details for responsible disclosure.
