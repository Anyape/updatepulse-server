# UpdatePulse Server - Miscellaneous - Developer documentation
(Looking for the main documentation page instead? [See here](https://github.com/anyape/updatepulse-server/blob/main/README.md))

UpdatePulse Server provides an API and offers a series of functions, actions and filters for developers to use in their own plugins and themes to modify the behavior of the plugin. Below is the documentation to interface with miscellaneous aspects of UpdatePulse Server. 

* [UpdatePulse Server - Miscellaneous - Developer documentation](#updatepulse-server---miscellaneous---developer-documentation)
    * [Nonce API](#nonce-api)
        * [Acquiring a reusable token or a true nonce - payload](#acquiring-a-reusable-token-or-a-true-nonce---payload)
        * [Responses](#responses)
        * [Building API credentials and API signature](#building-api-credentials-and-api-signature)
    * [Update API](#update-api)
        * [The `get_metadata` action](#the-get_metadata-action)
        * [The `download` action](#the-download-action)
    * [WP CLI](#wp-cli)
    * [Consuming Webhooks](#consuming-webhooks)
    * [Functions](#functions)
        * [upserv\_get\_brand\_icon](#upserv_get_brand_icon)
        * [upserv\_is\_doing\_api\_request](#upserv_is_doing_api_request)
        * [upserv\_is\_doing\_webhook\_api\_request](#upserv_is_doing_webhook_api_request)
        * [upserv\_init\_nonce\_auth](#upserv_init_nonce_auth)
        * [upserv\_create\_nonce](#upserv_create_nonce)
        * [upserv\_get\_nonce\_expiry](#upserv_get_nonce_expiry)
        * [upserv\_get\_nonce\_data](#upserv_get_nonce_data)
        * [upserv\_validate\_nonce](#upserv_validate_nonce)
        * [upserv\_delete\_nonce](#upserv_delete_nonce)
        * [upserv\_clear\_nonce](#upserv_clear_nonce)
        * [upserv\_build\_nonce\_api\_signature](#upserv_build_nonce_api_signature)
        * [upserv\_schedule\_webhook](#upserv_schedule_webhook)
        * [upserv\_fire\_webhook](#upserv_fire_webhook)
    * [Actions](#actions)
        * [upserv\_mu\_optimizer\_default\_pre\_apply](#upserv_mu_optimizer_default_pre_apply)
        * [upserv\_mu\_optimizer\_default\_applied](#upserv_mu_optimizer_default_applied)
        * [upserv\_mu\_optimizer\_ready](#upserv_mu_optimizer_ready)
        * [upserv\_mu\_ready](#upserv_mu_ready)
        * [upserv\_ready](#upserv_ready)
        * [upserv\_no\_api\_includes](#upserv_no_api_includes)
        * [upserv\_no\_priority\_api\_includes](#upserv_no_priority_api_includes)
        * [upserv\_api\_options\_updated](#upserv_api_options_updated)
    * [Filters](#filters)
        * [upserv\_mu\_optimizer\_remove\_all\_hooks](#upserv_mu_optimizer_remove_all_hooks)
        * [upserv\_mu\_optimizer\_doing\_api\_request](#upserv_mu_optimizer_doing_api_request)
        * [upserv\_mu\_optimizer\_info](#upserv_mu_optimizer_info)
        * [upserv\_mu\_require](#upserv_mu_require)
        * [upserv\_mu\_plugin\_registration\_classes](#upserv_mu_plugin_registration_classes)
        * [upserv\_is\_api\_request](#upserv_is_api_request)
        * [upserv\_scripts\_l10n](#upserv_scripts_l10n)
        * [upserv\_nonce\_api\_payload](#upserv_nonce_api_payload)
        * [upserv\_nonce\_api\_code](#upserv_nonce_api_code)
        * [upserv\_nonce\_api\_response](#upserv_nonce_api_response)
        * [upserv\_created\_nonce](#upserv_created_nonce)
        * [upserv\_clear\_nonces\_query](#upserv_clear_nonces_query)
        * [upserv\_clear\_nonces\_query\_args](#upserv_clear_nonces_query_args)
        * [upserv\_expire\_nonce](#upserv_expire_nonce)
        * [upserv\_delete\_nonce](#upserv_delete_nonce-1)
        * [upserv\_fetch\_nonce](#upserv_fetch_nonce)
        * [upserv\_nonce\_authorize](#upserv_nonce_authorize)
        * [upserv\_api\_option\_update](#upserv_api_option_update)
        * [upserv\_api\_option\_save\_value](#upserv_api_option_save_value)
        * [upserv\_api\_webhook\_events](#upserv_api_webhook_events)
        * [upserv\_webhook\_fire](#upserv_webhook_fire)
        * [upserv\_schedule\_webhook\_is\_instant](#upserv_schedule_webhook_is_instant)

___
## Nonce API

The nonce API is accessible via `POST` and `GET` requests on the `/updatepulse-server-token/` endpoint to acquire a reusable token, and `/updatepulse-server-nonce/` to acquire a true nonce.  
It accepts form-data payloads (arrays, basically). This documentation page uses `wp_remote_post`, but `wp_remote_get` would work as well.  

Authorization is granted with either the `HTTP_X_UPDATEPULSE_API_CREDENTIALS` and `HTTP_X_UPDATEPULSE_API_SIGNATURE` headers or with the `api_credentials` and `api_signature` parameters.  
If requesting a token for an existing API, the `api` parameter value must be provided with one of `package` or `license` to specify the target API.  
The credentials and the signature are valid for 1 minute; building them is the responsibility of the third-party client making use of the API - an implementation in PHP is provided below.  
**Using `GET` requests directly in the browser, whether through the URL bar or JavaScript, is strongly discouraged due to security concerns**; it should be avoided at all cost to prevent the inadvertent exposure of the credentials and signature.  

In case the Private API Key is invalid, the API will return the following response (message's language depending on available translations), with HTTP response code set to `403`:

Response `$data` - forbidden access:
```json
{
    "message": "Unauthorized access"
}
```

The description of the API below is using the following code as reference, where `$payload` is the body sent to the API, `$headers` are the headers sent to the API, and `$response` is the response received from the API:

```php
$url      = 'https://domain.tld/updatepulse-server-nonce/'; // Receive a true nonce. Replace domain.tld with the domain where UpdatePulse Server is installed.
$url      = 'https://domain.tld/updatepulse-server-token/'; // Receive a resuable token. Replace domain.tld with the domain where UpdatePulse Server is installed.
$headers  = array(
    'X-UpdatePulse-API-Signature' => $signature,     // The signature built using the Private API Key (optional - must be provided in case `api_signature` is absent from the payload)
    'X-UpdatePulse-API-Credentials' => $credentials, // The credentials acting as public key `timestamp|key_id` (optional - must be provided in case `api_credentials` is absent from the payload)
);
$response = wp_remote_post(
    $url,
    array(
        'headers' => $headers,
        'body'    => $payload,
        // other parameters...
    );
);

if ( is_wp_error( $response ) ) {
    printf( esc_html__( 'Something went wrong: %s', 'text-domain' ), esc_html( $response->get_error_message() ) );
} else {
    $data         = wp_remote_retrieve_body( $response );
    $decoded_data = json_decode( $data );

    if ( 200 === intval( $response['response']['code'] ) ) {
        // Handle success with $decoded_data
    } else {
        // Handle failure with $decoded_data
    }
}
```
### Acquiring a reusable token or a true nonce - payload

```php
$payload = array(
    'expiry_length' => 999,               // The expiry length in seconds (optional - default value to UPServ_Nonce::DEFAULT_EXPIRY_LENGTH - 30 seconds)
    'data' => array(                      // Data to store along the token or true nonce (optional)
        'permanent' => 1,                 // set to a truthy value to create a nonce that never expires
        'key1'      => 'value1',          // custom data
        'key2'      => array(             // custom data can be as nested as needed
            'subkey1'   => 'subval1',
            'subkey2'   => 'subval2'
            'bool_key1' => 'true',
            'bool_key2' => 'false',
            'bool_key3' => 1,
            'bool_key4' => 0,
        ),
    ),
    'api_credentials' => '9999999999|private_key_id', // The credentials acting as public key `timestamp|key_id`, where `timestamp` is a past timestamp no older than 1 minutes, and `key_id` is the ID corresponding to the Private API Key (optional - must be provided in case X-UpdatePulse-API-Credentials header is absent)
    'api_signature'   => 'complex_signature',         // The signature built using the Private API Key (optional - must be provided in case X-UpdatePulse-API-Signature header is absent)
    'api'             => 'api_name',                  // The target API (required if requesting a nonce for the existing APIs; one of `'package'` or `'license'`)
);
```

Please note that **boolean values are NOT supported in the `data` array**: if the payload needs to include such value type, developers must use values from the following table:

| Value | Type | Boolean value |
| --- | --- | --- |
| `1` | integer | `true` |
| `0` | integer | `false` |
| `'1'` | string | `true` |
| `'0'` | string | `false` |
| `'true'` | string | `true` |
| `'false'` | string | `false` |
| `'on'` | string | `true` |
| `'off'` | string | `false` |
| `'yes'` | string | `true` |
| `'no'` | string | `false` |
| `''` | string | `false` |

### Responses

Code `200` - **success**:
```json
{
    "nonce": "nonce_value",
    "true_nonce": true|false,
    "expiry": 9999999999,
    "data": {
        "permanent": 1,
        "key1": "value1",
        "key2": {
            "subkey1": "subval1",
            "subkey2": "subval2",
            "bool_key1": "true",
            "bool_key2": "false",
            "bool_key3": 1,
            "bool_key4": 0
        }
    }
}
```

Code `400` - **failure** - invalid action:
```json
{
    "code": "action_not_found",
    "message": "Malformed request"
}
```

Code `403` - **failure** - forbidden:
```json
{
    "code": "unauthorized",
    "message": "Unauthorized access."
}
```

Code `500` - **failure** - nonce insert error:
```json
{
    "code": "internal_error",
    "message": "Internal Error - nonce insert error"
}
```


### Building API credentials and API signature

Production-ready PHP example:

```php
if ( ! function_exists( 'upserv_build_nonce_api_signature' ) ) {
    /**
    * Build credentials and signature for UpdatePulse Server Nonce API
    *
    * @param string $api_key_id The ID of the Private API Key
    * @param string $api_key The Private API Key - will not be sent over the Internet
    * @param int    $timestamp The timestamp used to limit the validity of the signature (validity is MINUTE_IN_SECONDS)
    * @param int    $payload The payload to acquire a reusable token or a true nonce 
    * @return array An array with keys `credentials` and `signature`
    */
    function upserv_build_nonce_api_signature( $api_key_id, $api_key, $timestamp, $payload ) {
        unset( $payload['api_signature'] );
        unset( $payload['api_credentials'] );

        ( function ( &$arr ) {
            $recur_ksort = function ( &$arr ) use ( &$recur_ksort ) {

                foreach ( $arr as &$value ) {

                    if ( is_array( $value ) ) {
                        $recur_ksort( $value );
                    }
                }

                ksort( $arr );
            };

            $recur_ksort( $arr );
        } )( $payload );

        $str         = base64_encode( $api_key_id . json_encode( $payload, JSON_NUMERIC_CHECK ) ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode, WordPress.WP.AlternativeFunctions.json_encode_json_encode
        $credentials = $timestamp . '/' . $api_key_id;
        $time_key    = hash_hmac( 'sha256', $timestamp, $api_key, true );
        $signature   = hash_hmac( 'sha256', $str, $time_key );

        return array(
            'credentials' => $credentials,
            'signature'   => $signature,
        );
    }
}

// Usage
$values = upserv_build_nonce_api_signature( getenv( 'UPSERV_API_KEY_ID' ), getenv( 'UPSERV_API_KEY' ), time(), $payload );

echo '<div>The credentials are: ' . esc_html( $values['credentials'] ) . '</div>';
echo '<div>The signature is: ' . esc_html( $values['signature'] ) . '</div>';
```

## Update API

The Update API is accessible via `GET` requests on the `/updatepulse-server-update-api/` endpoint.  
It has two actions: `get_metadata` and `download`.

### The `get_metadata` action

The `get_metadata` action is used to check for updates. It accepts the following parameters:

| Parameter | Description | Required |
| --- | --- | --- |
| `action` | The action to perform. Must be `get_metadata`. | Yes |
| `package_id` | The ID of the package to check for updates. | Yes |
| `installed_version` | The version of the package currently installed. | No |
| `php` | The PHP version of the client. | No |
| `locale` | The locale of the client. | No |
| `checking_for_updates` | A flag indicating whether the client is checking for updates. | No |
| `license_key` | The license key of the package | Yes (if the package requires a license) |
| `license_signature` | The license signature of the package | Yes (if the package requires a license) |
| `update_type` | The type of update. Must be one of `Plugin`, `Theme`, or `Generic`. | Yes |

Example of a request to the Update API with:
- `get_metadata` action
- `package_id` set to `dummy-plugin`
- `installed_version` set to `1.0`
- `php` set to `8.3`
- `locale` set to `en_US`
- `checking_for_updates` set to `1`
- `license_key` set to `abcdef1234567890`
- `license_signature` set to `signabcdef1234567890`
- `update_type` set to `Plugin`

```bash
curl -X GET "https://server.domain.tld/updatepulse-server-update-api/?action=get_metadata&package_id=dummy-plugin&installed_version=1.0&php=8.3&locale=en_US&checking_for_updates=1&license_key=abcdef1234567890&license_signature=signabcdef1234567890&update_type=Plugin"
```

Example of a response (success):
```json
{
    "name": "Dummy Plugin",
    "version": "1.5.0",
    "homepage": "https:\/\/domain.tld\/",
    "author": "A Developer",
    "author_homepage": "https:\/\/domain.tld\/",
    "description": "Updated Empty plugin to demonstrate the UpdatePulse Updater.",
    "details_url": "https:\/\/domain.tld\/",
    "requires": "4.9.8",
    "tested": "4.9.8",
    "requires_php": "7.0",
    "sections": {
        "description": "<div class=\"readme-section\" data-name=\"Description\"><p>Update Plugin description. <strong>Basic HTML<\/strong> can be used in all sections.<\/p><\/div>",
        "dummy_section": "<div class=\"readme-section\" data-name=\"Dummy Section\"><p>An extra, dummy section.<\/p><\/div>",
        "installation": "<div class=\"readme-section\" data-name=\"Installation\"><p>Installation instructions.<\/p><\/div>",
        "changelog": "<div class=\"readme-section\" data-name=\"Changelog\"><p>This section will be displayed by default when the user clicks 'View version x.y.z details'.<\/p><\/div>",
        "frequently_asked_questions": "<div class=\"readme-section\" data-name=\"Frequently Asked Questions\"><h4>Question<\/h4><p>Answer<\/p><\/div>",
    },
    "icons": {
        "1x": "https:\/\/domain.tld\/path\/to\/icon-128x128.png",
        "2x": "https:\/\/domain.tld\/path\/to\/icon-256x256.png",
    },
    "banners": {
        "low": "https:\/\/domain.tld\/path\/to\/banner-772x250.png",
        "high": "https:\/\/domain.tld\/path\/to\/banner-1544x500.png",
    },
    "require_license": "1",
    "slug": "dummy-plugin",
    "type": "plugin",
    "download_url": "https:\/\/server.domain.tld\/updatepulse-server-update-api\/?action=download&package_id=dummy-plugin&token=tokenabcdef1234567890&license_key=abcdef1234567890&license_signature=signabcdef1234567890&update_type=Plugin",
    "license": {
        "license_key": "abcdef1234567890",
        "max_allowed_domains": 2,
        "allowed_domains": [
            "domain.tld",
            "domain2.tld"
        ],
        "status": "activated",
        "txn_id": "",
        "date_created": "2025-02-04",
        "date_renewed": "0000-00-00",
        "date_expiry": "2027-02-04",
        "package_slug": "dummy-plugin",
        "package_type": "plugin",
        "result": "success",
        "message": "License key details retrieved."
    },
    "time_elapsed": "0.139s"
}
```

Examples of a response (failure - invalid package):
```json
{
    "error": "no_server",
    "message": "No server found for this package."
}
```

Examples of a response (failure - invalid license):
```json
{
    "name": "Dummy Plugin",
    "version": "1.5.0",
    "homepage": "https:\/\/domain.tld\/",
    "author": "A Developer",
    "author_homepage": "https:\/\/domain.tld\/",
    "description": "Updated Empty plugin to demonstrate the UpdatePulse Updater.",
    "details_url": "https:\/\/domain.tld\/",
    "requires": "4.9.8",
    "tested": "4.9.8",
    "requires_php": "7.0",
    "sections": {
        "description": "<div class=\"readme-section\" data-name=\"Description\"><p>Update Plugin description. <strong>Basic HTML<\/strong> can be used in all sections.<\/p><\/div>",
        "dummy_section": "<div class=\"readme-section\" data-name=\"Dummy Section\"><p>An extra, dummy section.<\/p><\/div>",
        "installation": "<div class=\"readme-section\" data-name=\"Installation\"><p>Installation instructions.<\/p><\/div>",
        "changelog": "<div class=\"readme-section\" data-name=\"Changelog\"><p>This section will be displayed by default when the user clicks 'View version x.y.z details'.<\/p><\/div>",
        "frequently_asked_questions": "<div class=\"readme-section\" data-name=\"Frequently Asked Questions\"><h4>Question<\/h4><p>Answer<\/p><\/div>",
    },
    "icons": {
        "1x": "https:\/\/domain.tld\/path\/to\/icon-128x128.png",
        "2x": "https:\/\/domain.tld\/path\/to\/icon-256x256.png",
    },
    "banners": {
        "low": "https:\/\/domain.tld\/path\/to\/banner-772x250.png",
        "high": "https:\/\/domain.tld\/path\/to\/banner-1544x500.png",
    },
    "require_license": "1",
    "slug": "dummy-plugin",
    "type": "plugin",
        "license_error": {
        "code": "invalid_license",
        "message": "The license key or signature is invalid.",
        "data": {
            "license": false
        }
    },
    "time_elapsed": "0.139s"
}
```

### The `download` action

The `download` action is used to download the package. It accepts the following parameters:

| Parameter | Description | Required |
| --- | --- | --- |
| `action` | The action to perform. Must be `download`. | Yes |
| `package_id` | The ID of the package to download. | Yes |
| `token` | The cryptographic token to use to download the package. Generated by the Nonce API. | Yes |
| `license_key` | The license key of the package | Yes (if the package requires a license) |
| `license_signature` | The license signature of the package | Yes (if the package requires a license) |
| `update_type` | The type of update. Must be one of `Plugin`, `Theme`, or `Generic`. | Yes |

Generally, the URL to request this API endpoint would not be put together manually, but rather taken from the field `download_url` in the response of `get_metadata` action.

Example of a request to the Update API with:
- `download` action
- `package_id` set to `dummy-plugin`
- `token` set to `tokenabcdef1234567890`
- `license_key` set to `abcdef1234567890`
- `license_signature` set to `signabcdef1234567890`
- `update_type` set to `Plugin`

```bash
curl -X GET "https://server.domain.tld/updatepulse-server-update-api/?action=download&package_id=dummy-plugin&token=tokenabcdef1234567890&license_key=abcdef1234567890&license_signature=signabcdef1234567890&update_type=Plugin"
```

The response is a `zip` file containing the package.

## WP CLI

UpdatePulse Server provides a series of commands to interact with the plugin:

```bash
NAME

  wp updatepulse

SYNOPSIS

  wp updatepulse <command>

SUBCOMMANDS

  activate_license                 Activate a license for a domain.
  add_license                      Add a license.
  browse_licenses                  Browse licenses.
  build_nonce_api_signature        Build a Nonce API signature.
  check_license                    Check a license.
  check_remote_package_update      Checks for updates for a package.
  cleanup_all                      Cleans up the cache, logs and tmp folders in wp-content/updatepulse-server.
  cleanup_cache                    Cleans up the cache folder in wp-content/updatepulse-server.
  cleanup_logs                     Cleans up the logs folder in wp-content/updatepulse-server.
  cleanup_tmp                      Cleans up the tmp folder in wp-content/updatepulse-server.
  clear_nonces                     Clears nonces.
  create_nonce                     Creates a nonce.
  deactivate_license               Deactivate a license for a domain.
  delete_license                   Delete a license.
  delete_nonce                     Deletes a nonce.
  delete_package                   Deletes a package.
  download_remote_package          Downloads a package from a VCS.
  edit_license                     Edit a license.
  get_nonce_data                   Gets data saved along with a nonce.
  get_nonce_expiry                 Gets the expiry time of a nonce.
  get_package_info                 Gets package info.
  read_license                     Read a license by ID or key.
```

Subcommands overview:

```bash
wp updatepulse activate_license <license_key> <domain>
wp updatepulse add_license <license_data>
wp updatepulse browse_licenses <browse_query>
wp updatepulse build_nonce_api_signature <api_key_id> <api_key> <timestamp> <payload>
wp updatepulse check_license <license_key_or_id>
wp updatepulse check_remote_package_update <slug> <type>
wp updatepulse cleanup_all 
wp updatepulse cleanup_cache 
wp updatepulse cleanup_logs 
wp updatepulse cleanup_tmp 
wp updatepulse clear_nonces 
wp updatepulse create_nonce <true_nonce> <expiry_length> <data> <return_type> <store>
wp updatepulse deactivate_license <license_key> <domain>
wp updatepulse delete_license <license_key_or_id>
wp updatepulse delete_nonce <nonce>
wp updatepulse delete_package <slug>
wp updatepulse download_remote_package <slug> <type>
wp updatepulse edit_license <license_data>
wp updatepulse get_nonce_data <nonce>
wp updatepulse get_nonce_expiry <nonce>
wp updatepulse get_package_info <slug>
wp updatepulse read_license <license_key_or_id>
```

To get more help on a specific subcommand, use `wp updatepulse <subcommand> --help`.
___
## Consuming Webhooks

Webhooks's payload is sent in JSON format via a POST request and is signed with a `secret-key` secret key using the `sha256` algorithm.  
The resulting hash is made available in the `X-UpdatePulse-Signature-256` header.  

Below is an example of how to consume a Webhook on another installation of WordPress with a plugin (webhooks can however be consumed by any system):

```php
<?php
/*
Plugin Name: UpdatePulse Webhook Consumer
Plugin URI: https://domain.tld/up-webhook-consumer/
Description: Consume UpdatePulse Webhooks.
Version: 1.0
Author: A Developer
Author URI: https://domain.tld/
Text Domain: up-consumer
Domain Path: /languages
*/

/* This is a simple example.
 * We would normally want to use a proper class, add an endpoint,
 * use the `parse_request` action, and `query_vars` filter
 * and check the `query_vars` attribute of the global `$wp` variable
 * to identify the destination of the Webhook.
 *
 * Here instead we will attempt to `json_decode` the payload and
 * look for the `event` attribute to proceed.
 *
 * Also note that we only check for the actually secure `sha256` signature.
 */
add_action( 'plugins_loaded', function() {
    global $wp_filesystem;
    
    // We assume the secret is stored in environment variables
    $secret = getenv( 'UPDATEPULSE_HOOK_SECRET' );

    if ( empty( $wp_filesystem ) ) {
        require_once ABSPATH . '/wp-admin/includes/file.php';

        WP_Filesystem();
    }
    
    $payload = $wp_filesystem->get_contents( 'php://input' );
    $json    = json_decode( $payload );
    
    if ( $json && isset( $json->event ) ) {
        // Get the signature from headers
        $sign = isset( $_SERVER['HTTP_X_UPDATEPULSE_SIGNATURE_256'] ) ?
            $_SERVER['HTTP_X_UPDATEPULSE_SIGNATURE_256'] :
            false;

        if ( $sign ) {
            // Check our payload against the signature
            $sign_parts = explode( '=', $sign );
            $sign       = 2 === count( $sign_parts ) ? end( $sign_parts ) : false;
            $algo       = ( $sign ) ? reset( $sign_parts ) : false;
            $valid      = $sign && hash_equals( hash_hmac( $algo, $payload, $secret ), $sign );
            
            if ( $valid ) {
                error_log( 'The payload was successfully authenticated.' );
                // Log the headers and the body of the request
                // Typically, at this stage the client would use the consumed payload
                error_log(
                    print_r(
                        array(
                            'headers' => array(
                                'X-UpdatePulse-Action'        => $_SERVER['HTTP_X_UPDATEPULSE_ACTION'],
                                'X-UpdatePulse-Signature-256' => $_SERVER['HTTP_X_UPDATEPULSE_SIGNATURE_256'],
                            ),
                            'body' => $payload,
                        ),
                        true
                    )
                );
            } else {
                error_log( 'The payload could not be authenticated.' );
            }
        } else {
            error_log( 'Signature not found.' );
        }
    }
}, 10, 0 );

```
___
## Functions

The functions listed below are made publicly available by the plugin for theme and plugin developers. They can be used after the action `plugins_loaded` has been fired, or in a `plugins_loaded` action (just make sure the priority is above `-99`).  
Although the main classes can theoretically be instantiated without side effect if the `$hook_init` parameter is set to `false`, it is recommended to use only the following functions as there is no guarantee future updates won't introduce changes of behaviors.
___
### upserv_get_brand_icon

```php
upserv_get_brand_icon( string $brand );
```

**Description**

Return accessible, decorative SVG markup for an UpdatePulse-supported VCS brand. Every icon uses a normalized `0 0 512 512` view box and `currentColor`, allowing it to inherit the surrounding text color.

**Parameters**

`$brand`
> (string) one of `github`, `gitlab`, `gitlab-self-hosted`, `gitea`, `forgejo`, `gitee`, or `bitbucket`

**Return value**
> (string) SVG markup for a supported brand, or an empty string when the brand is unsupported

___
### upserv_is_doing_api_request

```php
upserv_is_doing_api_request();
```

**Description**  
Determine whether the current request is made by a remote client interacting with any of the APIs.

**Return value**
> (bool) `true` if the current request is made by a remote client interacting with any of the APIs, `false` otherwise

___
### upserv_is_doing_webhook_api_request

```php
upserv_is_doing_webhook_api_request();
```

**Description**  
Determine whether the current request is made by a Webhook.

**Return value**
> (bool) `true` if the current request is made by a Webhook, `false` otherwise

___
### upserv_init_nonce_auth

```php
upserv_init_nonce_auth( array $private_keys );
```

**Description**  
Set the private keys to check against when requesting nonces via the `updatepulse-server-token` and `updatepulse-server-nonce` endpoints.  

**Parameters**  
`$private_keys`
> (array) the private keys with the following format:  
```php
$private_keys = array(
    'api_key_id_1' => array(
        'key' => 'api_key_1',
        // ... other values are ignored
    ),
    'api_key_id_2' => array(
        'key' => 'api_key_2',
        // ... other values are ignored
    ),
);
```

___
### upserv_create_nonce

```php
upserv_create_nonce( bool $true_nonce = true, int $expiry_length = UPServ_Nonce::DEFAULT_EXPIRY_LENGTH, array $data = array(), int $return_type = UPServ_Nonce::NONCE_ONLY, bool $store = true, bool|callable );
```

**Description**  
Creates a cryptographic token - allows creation of tokens that are true one-time-use nonces, with custom expiry length and custom associated data.

**Parameters**  
`$true_nonce`
> (bool) whether the nonce is one-time-use; default `true`  

`$expiry_length`
> (int) the number of seconds after which the nonce expires; default `UPServ_Nonce::DEFAULT_EXPIRY_LENGTH` - 30 seconds 

`$data`
> (array) custom data to save along with the nonce; set an element with key `permanent` to a truthy value to create a nonce that never expires; default `array()`  

`$return_type`
> (int) whether to return the nonce, or an array of information; default `UPServ_Nonce::NONCE_ONLY`; other accepted value is `UPServ_Nonce::NONCE_INFO_ARRAY`  

`$store`
> (bool) whether to store the nonce, or let a third party mechanism take care of it; default `true`  

**Return value**
> (bool|string|array) `false` in case of failure; the cryptographic token string if `$return_type` is set to `UPServ_Nonce::NONCE_ONLY`; an array of information if `$return_type` is set to `UPServ_Nonce::NONCE_INFO_ARRAY` with the following format:
```php
array(
    'nonce'      => 'some_value',	// cryptographic token
    'true_nonce' => true,			// whether the nonce is one-time-use
    'expiry'     => 9999,			// the expiry timestamp
    'data'       => array(),		// custom data saved along with the nonce
);
```

___
### upserv_get_nonce_expiry

```php
upserv_get_nonce_expiry( string $nonce );
```

**Description**  
Get the expiry timestamp of a nonce.  

**Parameters**  
`$nonce`
> (string) the nonce  

**Return value**
> (int) the expiry timestamp  

___
### upserv_get_nonce_data

```php
upserv_get_nonce_data( string $nonce );
```

**Description**  
Get the data stored along a nonce.  

**Parameters**  
`$nonce`
> (string) the nonce  

**Return value**
> (int) the expiry timestamp  

___
### upserv_validate_nonce

```php
upserv_validate_nonce( string $value );
```

**Description**  
Check whether the value is a valid nonce.  
Note: if the nonce is a true nonce, it will be invalidated and further calls to this function with the same `$value` will return `false`.  

**Parameters**  
`$value`
> (string) the value to check  

**Return value**
> (bool) whether the value is a valid nonce  

___
### upserv_delete_nonce

```php
upserv_delete_nonce( string $value );
```

**Description**  
Delete a nonce from the system if the corresponding value exists.  

**Parameters**  
`$value`
> (string) the value to delete  

**Return value**
> (bool) whether the nonce was deleted  

___
### upserv_clear_nonce

```php
upserv_clear_nonces();
```

**Description**  
Clear expired nonces from the system.  

**Return value**
> (bool) whether some nonces were cleared  

___
### upserv_build_nonce_api_signature

```php
upserv_build_nonce_api_signature( string $api_key_id, string $api_key, int $timestamp, array $payload );
```

**Description**  
Build credentials and signature for UpdatePulse Server Nonce API  

**Parameters**  
`$api_key_id`
> (string) the ID of the Private API Key  

`$api_key`
> (string) the Private API Key - will not be sent over the Internet  

`$timestamp`
> (int) the timestamp used to limit the validity of the signature (validity is `MINUTE_IN_SECONDS`)  

`$payload`
> (array) the payload to acquire a reusable token or a true nonce  

**Return value**
> (array) an array with keys `credentials` and `signature`  

___
### upserv_schedule_webhook

```php
upserv_schedule_webhook( array $payload, string $event_type, bool $instant );
```

**Description**  
Schedule an event notification to be sent to registered Webhook URLs at next cron run.  

**Parameters**  
`$payload`
> (array) the data used to schedule the notification with the following format:  
```php
$payload = array(
    'event'       => 'event_name',                                // required - the name of the event that triggered the notification
    'description' => 'A description of what the event is about.', // optional - Description of the notification
    'content'     => 'The data of the payload',                   // required - the data to be consumed by the recipient
);
```

`$event_type`
> (string) the type of event; the payload will only be delivered to URLs subscribed to this type  

`$instant`
> (bool) whether to send the notification immediately; default `false`

**Return value**
> (null|WP_error) `null` in case of success, a `WP_Error` otherwise  

___
### upserv_fire_webhook

```php
upserv_fire_webhook( string $url, string $secret, string $body, string $action );
```

**Description**  
Immediately send a event notification to `$url`, signed with `$secret` with resulting hash stored in `X-UpdatePulse-Signature-256`, with `$action` in `X-UpdatePulse-Action`.  

**Parameters**  
`$url`
> (string) the destination of the notification  

`$secret`
> (string) the secret used to sign the notification  

`$body`
> (string) the JSON string sent in the notification  

`$action`
> (string) the WordPress action responsible for firing the webhook  

**Return value**
> (array|WP_Error) the response of the request in case of success, a `WP_Error` otherwise  
___
## Actions

UpdatePulse Server gives developers the possibility to have their plugins react to some events with a series of custom actions.  
**Warning**: the filters below with the mention "Fired during API requests" need to be used with caution. Although they may be triggered when using the functions above, these filters will possibly be called when the Update API, License API, Packages API or a Webhook is called. Registering functions doing heavy computation to these filters can seriously degrade the server's performances.  

___
### upserv_mu_optimizer_default_pre_apply

```php
do_action( 'upserv_mu_optimizer_default_pre_apply' );
```

**Description**
Fired before the Must Used Plugin `UpdatePulse Server Default Optimizer` behavior is applied.  
Use this hook in a Must Used Plugin to integrate with the default optimizer (See the [UpdatePulse Server Integration](https://github.com/Anyape/updatepulse-server-integration) repository for existing optimizers).  
Must be subscribed to in another MU plugin before or within the `muplugins_loaded` action; if within, the action must have a priority lower than `0`.

___
### upserv_mu_optimizer_default_applied

```php
do_action( 'upserv_mu_optimizer_default_applied' );
```

**Description**

Fired after the Must Used Plugin `UpdatePulse Server Default Optimizer` behavior is applied.  
Use this hook in a Must Used Plugin to integrate with the default optimizer (See the [UpdatePulse Server Integration](https://github.com/Anyape/updatepulse-server-integration) repository for existing optimizers).  
Must be subscribed to in another MU plugin before or within the `muplugins_loaded` action; if within, the action must have a priority lower than `0`.

___
### upserv_mu_optimizer_ready

```php
do_action( 'upserv_mu_optimizer_ready', bool $doing_api, array|bool $info );
```

**Description**
Fired when the Must Used Plugin `UpdatePulse Server Endpoint Optimizer` has been executed.
Must be subscribed to in another MU plugin before or within the `muplugins_loaded` action; if within, the action must have a priority lower than `0`.

**Parameters**
`$doing_api`
> (bool) whether the current request is made by a remote client interacting with any of the APIs

`$info`
> (array|bool) an array of information about modifications made when the optimizer executed (`false` if `$doing_api` is not truthy)

___
### upserv_mu_ready

```php
do_action( 'upserv_mu_ready' );
```

**Description**
Fired when UpdatePulse Server is starting up, after the Must Used Plugins have been loaded.

___
### upserv_ready

```php
do_action( 'upserv_ready', $objects );
```

**Description**
Fired when UpdatePulse Server is fully loaded.

**Parameters**
`$objects`
> (array) an array of objects representing the plugin's main classes. Particularly useful to deregister hooks or filters.  

___
### upserv_no_api_includes

```php
do_action( 'upserv_no_api_includes' );
```

**Description**  
Fired when the plugin is including files and the current request is not made by a remote client interacting with any of the plugin's API.

___
### upserv_no_priority_api_includes

```php
do_action( 'upserv_no_priority_api_includes' );
```

**Description**  
Fired when the plugin is including files and the current request is not made by a client plugin or theme interacting with the plugin's high priority API (typically the license API).

___
### upserv_api_options_updated

```php
do_action( 'upserv_api_options_updated', array $errors );
```

**Description**  
Fired after the options in "API & Webhooks" have been updated.

**Parameters**  
`$errors`
> (array) an array of containing errors if any  

___
## Filters

UpdatePulse Server gives developers the possibility to customize its behavior with a series of custom filters.  
**Warning**: the filters below with the mention "Fired during API requests" need to be used with caution. Although they may be triggered when using the functions above, these filters will possibly be called when the Update API, License API, Packages API or a Webhook is called. Registering functions doing heavy computation to these filters can seriously degrade the server's performances.  

___
### upserv_mu_optimizer_remove_all_hooks

```php
apply_filters( 'upserv_mu_optimizer_remove_all_hooks', array $hooks );
```

**Description**
Filter the hooks to remove when the Must Used Plugin `UpdatePulse Server Default Optimizer` is applied.
Must be subscribed to in another MU plugin before or within the `muplugins_loaded` action; if within, the action must have a priority lower than `0`.

**Parameters**
`$hooks`
> (array) the hooks to remove during an optimized API request

___
### upserv_mu_optimizer_doing_api_request

```php
apply_filters( 'upserv_mu_optimizer_doing_api_request', bool $doing_api );
```

**Description**
Filter whether the current request must be treated as an API request.  
The value is cached in `'upserv_mu_doing_api'` (group `''updatepulse-server''`) with `wp_cache_set()` and used before [upserv_is_api_request](#upserv_is_api_request) is fired.
Must be subscribed to in another MU plugin before or within the `muplugins_loaded` action; if within, the action must have a priority lower than `0`.

**Parameters**
`$doing_api`
> (bool) whether the current request must be treated as an API request  
> By default, `true` if the first fragment after `home_url()` matches the regex `/^updatepulse-server-((.*?)-api|nonce|token)$/`

___
### upserv_mu_optimizer_info

```php
apply_filters( 'upserv_mu_optimizer_info', array $info );
```

**Description**
Filter the information about what the optimizer has done when it was executed.  
Use this hook in a Must Used Plugin to integrate with the default optimizer (See the [UpdatePulse Server Integration](https://github.com/Anyape/updatepulse-server-integration) repository for existing optimizers).  
Must be subscribed to in another MU plugin before or within the `muplugins_loaded` action; if within, the action must have a priority lower than `0`.

**Parameters**
`$info`
> (array) an array of information about modifications made when the optimizer executed  
> The array has the following format:
```php
$info = array(
    'removed_hooks'               => $hooks,
    'info_from_another_optimizer' => 'your_value',
);
```
___
### upserv_mu_require

```php
apply_filters( 'upserv_mu_require', array $require );
```

**Description**
Filter the files to require when initializing UpdatePulse Server.  
Must be subscribed to in a MU plugin to guarantee the correct order of execution.

**Parameters**
`$require`
> (array) the absolute paths to the files to require when initializing UpdatePulse Server
___
### upserv_mu_plugin_registration_classes

```php
apply_filters( 'upserv_mu_plugin_registration_classes', array $classes );
```

**Description**
Filter the classes used to register `register_activation_hook`, `register_deactivation_hook` and `register_uninstall_hook`.  
Must be subscribed to in a MU plugin to guarantee the correct order of execution.

**Parameters**
`$classes`
> (array) the classes used to register the hooks; they may have at least one of the following methods implemented: `activation`, `deactivation`, `uninstall`

___
### upserv_is_api_request

```php
apply_filters( 'upserv_is_api_request', bool $is_api_request );
```

**Description**  
Filter whether the current request must be treated as an API request.  

**Parameters**  
`$is_api_request`
> (bool) whether the current request must be treated as an API request  
> By default, `true` if the value of `wp_cache_get( 'upserv_mu_doing_api', 'updatepulse-server' )` is truthy, or a recalculated value otherwise.

___
### upserv_scripts_l10n

```php
apply_filters( 'upserv_scripts_l10n', array $l10n, string $handle );
```

**Description**  
Filter the internationalization strings passed to the frontend scripts.  

**Parameters**  
`$l10n`
> (array) the internationalization strings passed to the frontend scripts  

`$handle`
> (string) the handle of the script

___
### upserv_nonce_api_payload

```php
apply_filters( 'upserv_nonce_api_payload', array $payload, string $action );
```

**Description**  
Filter the payload sent to the Nonce API.  

**Parameters**  
`$code`
> (string) the payload sent to the Nonce API  

`$action`
> (string) the api action - `token` or `nonce`  

___
### upserv_nonce_api_code

```php
apply_filters( 'upserv_nonce_api_code', string $code, array $request_params );
```

**Description**  
Filter the HTTP response code to be sent by the Nonce API.  

**Parameters**  
`$code`
> (string) the HTTP response code to be sent by the Nonce API  

`$request_params`
> (array) the request's parameters  

___
### upserv_nonce_api_response

```php
apply_filters( 'upserv_nonce_api_response', array $response, string $code, array $request_params );
```

**Description**  
Filter the response to be sent by the Nonce API.  

**Parameters**  
`$response`
> (array) the response to be sent by the Nonce API  

`$code`
> (string) the HTTP response code sent by the Nonce API  

`$request_params`
> (array) the request's parameters  

___
### upserv_created_nonce

```php
apply_filters( 'upserv_created_nonce', bool|string|array $nonce_value, bool $true_nonce, int $expiry_length, array $data, int $return_type );
```

**Description**  
Filter the value of the nonce before it is created; if `$nonce_value` is truthy, the value is used as nonce and the default generation algorithm is bypassed; developers must respect the `$return_type`.

**Parameters**  
`$nonce_value`
> (bool|string|array) the value of the nonce before it is created - if truthy, the nonce is considered created with this value  

`$true_nonce`
> (bool) whether the nonce is a true, one-time-use nonce  

`$expiry_length`
> (int) the expiry length of the nonce in seconds  

`$data`
> (array) data to store along the nonce  

`$return_type`
> (int) `UPServ_Nonce::NONCE_ONLY` or `UPServ_Nonce::NONCE_INFO_ARRAY`  

___
### upserv_clear_nonces_query

```php
apply_filters( 'upserv_clear_nonces_query', string $sql, array $sql_args );
```

**Description**  
Filter the SQL query used to clear expired nonces.

**Parameters**  
`$sql`
> (string) the SQL query used to clear expired nonces  

`$sql_args`
> (array) the arguments passed to the SQL query used to clear expired nonces  

___
### upserv_clear_nonces_query_args

```php
apply_filters( 'upserv_clear_nonces_query_args', array $sql_args, string $sql );
```

**Description**  
Filter the arguments passed to the SQL query used to clear expired nonces.

**Parameters**  
`$sql_args`
> (array) the arguments passed to the SQL query used to clear expired nonces  

`$sql`
> (string) the SQL query used to clear expired nonces  

___
### upserv_expire_nonce

```php
apply_filters( 'upserv_expire_nonce', bool $expire_nonce, string $nonce_value, bool $true_nonce, int $expiry, array $data, object $row );
```

**Description**  
Filter whether to consider the nonce has expired.

**Parameters**  
`$expire_nonce`
> (bool) whether to consider the nonce has expired  

`$nonce_value`
> (string) the value of the nonce  

`$true_nonce`
> (bool) whether the nonce is a true, one-time-use nonce  

`$expiry`
> (int) the timestamp at which the nonce expires  

`$data`
> (array) data stored along the nonce  

`$row`
> (object) the database record corresponding to the nonce  

___
### upserv_delete_nonce

```php
apply_filters( 'upserv_delete_nonce', bool $delete, string $nonce_value, bool $true_nonce, int $expiry, array $data, object $row );
```

**Description**  
Filter whether to delete the nonce.

**Parameters**  
`$delete`
> (bool) whether to delete the nonce  

`$nonce_value`
> (string) the value of the nonce  

`$true_nonce`
> (bool) whether the nonce is a true, one-time-use nonce  

`$expiry`
> (int) the timestamp at which the nonce expires  

`$data`
> (array) data stored along the nonce  

`$row`
> (object) the database record corresponding to the nonce  

___
### upserv_fetch_nonce

```php
apply_filters( 'upserv_fetch_nonce', string $nonce_value, bool $true_nonce, int $expiry, array $data, object $row );
```

**Description**  
Filter the value of the nonce after it has been fetched from the database.

**Parameters**  
`$nonce_value`
> (string) the value of the nonce after it has been fetched from the database  

`$true_nonce`
> (bool) whether the nonce is a true, one-time-use nonce  

`$expiry`
> (int) the timestamp at which the nonce expires  

`$data`
> (array) data stored along the nonce  

`$row`
> (object) the database record corresponding to the nonce  

___
### upserv_nonce_authorize

```php
apply_filters( 'upserv_nonce_authorize', $authorized, $received_key, $private_auth_keys );
```

**Description**  
Filter whether the request for a nonce is authorized.

**Parameters**  
`$authorized`
> (bool) whether the request is authorized  

`$received_key`
> (string) the key use to attempt the authorization  

`$private_auth_keys`
> (array) the valid authorization keys  

___
### upserv_api_option_update

```php
apply_filters( 'upserv_api_option_update', bool $update, string $option_name, array $option_info, array $options );
```

**Description**  
Filter whether to update the API plugin option.  

**Parameters**  
`$update`
> (bool) whether to update the API option  

`$option_name`
> (string) the name of the option  

`$option_info`
> (array) the info related to the option  

`$options`
> (array) the values submitted along with the option  

___
### upserv_api_option_save_value

```php
apply_filters( 'upserv_api_option_save_value', mixed $value, string $option_name, array $option_info, array $options );
```

**Description**
Filter the value of the API option before saving it.

**Parameters**
`$value`
> (mixed) the value of the API option

`$option_name`
> (string) the name of the option

`$option_info`
> (array) the info related to the option

`$options`
> (array) the values submitted along with the option

___
### upserv_api_webhook_events

```php
apply_filters( 'upserv_api_webhook_events', array $events );
```

**Description**  
Filter the available webhook events.  

**Parameters**  
`$events`
> (array) the available webhook events  

___
### upserv_webhook_fire

```php
apply_filters( 'upserv_webhook_fire', bool $fire, array $payload, string $url, array $webhook_setting );
```

**Description**  
Filter whether to fire the webhook event.  

**Parameters**  
`$fire`
> (bool) whether to fire the event  

`$payload`
> (array) the payload of the event  

`$url`
> (string) the target url of the event  

`$webhook_setting`
> (array) the settings of the webhook  
___
### upserv_schedule_webhook_is_instant

```php
apply_filters( 'upserv_schedule_webhook_is_instant', bool $instant, array $payload, string $event_type );
```

**Description**
Filter whether to send the webhook notification immediately.

**Parameters**
`$instant`
> (bool) whether to send the notification immediately

`$payload`
> (array) the payload of the event

`$event_type`
> (string) the type of event
___
