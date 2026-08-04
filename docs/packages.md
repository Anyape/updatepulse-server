# UpdatePulse Server - Packages - Developer documentation
(Looking for the main documentation page instead? [See here](https://github.com/anyape//blob/main/README.md))  

UpdatePulse Server offers a series of functions, actions and filters for developers to use in their own plugins and themes to modify the behavior of the plugin when managing packages.  

* [UpdatePulse Server - Packages - Developer documentation](#updatepulse-server---packages---developer-documentation)
    * [API](#api)
        * [Public API](#public-api)
            * [download](#download)
        * [Private API](#private-api)
            * [browse](#browse)
            * [read](#read)
            * [edit](#edit)
            * [add](#add)
            * [delete](#delete)
            * [signed\_url](#signed_url)
    * [Functions](#functions)
        * [upserv\_get\_data\_dir](#upserv_get_data_dir)
        * [upserv\_get\_root\_data\_dir](#upserv_get_root_data_dir)
        * [upserv\_get\_packages\_data\_dir](#upserv_get_packages_data_dir)
        * [upserv\_get\_logs\_data\_dir](#upserv_get_logs_data_dir)
        * [upserv\_get\_cache\_data\_dir](#upserv_get_cache_data_dir)
        * [upserv\_is\_doing\_update\_api\_request](#upserv_is_doing_update_api_request)
        * [upserv\_is\_doing\_package\_api\_request](#upserv_is_doing_package_api_request)
        * [upserv\_check\_remote\_package\_update](#upserv_check_remote_package_update)
        * [upserv\_check\_remote\_plugin\_update](#upserv_check_remote_plugin_update)
        * [upserv\_check\_remote\_theme\_update](#upserv_check_remote_theme_update)
        * [upserv\_download\_remote\_package](#upserv_download_remote_package)
        * [upserv\_get\_package\_vcs\_config](#upserv_get_package_vcs_config)
        * [upserv\_download\_remote\_plugin](#upserv_download_remote_plugin)
        * [upserv\_download\_remote\_theme](#upserv_download_remote_theme)
        * [upserv\_force\_cleanup\_cache](#upserv_force_cleanup_cache)
        * [upserv\_force\_cleanup\_logs](#upserv_force_cleanup_logs)
        * [upserv\_force\_cleanup\_tmp](#upserv_force_cleanup_tmp)
        * [upserv\_get\_local\_package\_path](#upserv_get_local_package_path)
        * [upserv\_download\_local\_package](#upserv_download_local_package)
        * [upserv\_delete\_package](#upserv_delete_package)
        * [upserv\_get\_package\_info](#upserv_get_package_info)
        * [upserv\_get\_batch\_package\_info](#upserv_get_batch_package_info)
        * [upserv\_is\_package\_require\_license](#upserv_is_package_require_license)
        * [upserv\_is\_package\_whitelisted](#upserv_is_package_whitelisted)
        * [upserv\_whitelist\_package](#upserv_whitelist_package)
        * [upserv\_unwhitelist\_package](#upserv_unwhitelist_package)
        * [upserv\_get\_package\_metadata\_data\_dir](#upserv_get_package_metadata_data_dir)
        * [upserv\_get\_package\_metadata](#upserv_get_package_metadata)
        * [upserv\_set\_package\_metadata](#upserv_set_package_metadata)
    * [Actions](#actions)
        * [upserv\_registered\_package\_from\_vcs](#upserv_registered_package_from_vcs)
        * [upserv\_did\_manual\_upload\_package](#upserv_did_manual_upload_package)
        * [upserv\_before\_packages\_download](#upserv_before_packages_download)
        * [upserv\_triggered\_package\_download](#upserv_triggered_package_download)
        * [upserv\_scheduled\_check\_remote\_event](#upserv_scheduled_check_remote_event)
        * [upserv\_registered\_check\_remote\_schedule](#upserv_registered_check_remote_schedule)
        * [upserv\_cleared\_check\_remote\_schedule](#upserv_cleared_check_remote_schedule)
        * [upserv\_scheduled\_cleanup\_event](#upserv_scheduled_cleanup_event)
        * [upserv\_registered\_cleanup\_schedule](#upserv_registered_cleanup_schedule)
        * [upserv\_cleared\_cleanup\_schedule](#upserv_cleared_cleanup_schedule)
        * [upserv\_did\_cleanup](#upserv_did_cleanup)
        * [upserv\_before\_handle\_update\_request](#upserv_before_handle_update_request)
        * [upserv\_pre\_filter\_package\_info](#upserv_pre_filter_package_info)
        * [upserv\_filter\_package\_info](#upserv_filter_package_info)
        * [upserv\_download\_remote\_package\_aborted](#upserv_download_remote_package_aborted)
        * [upserv\_downloaded\_remote\_package](#upserv_downloaded_remote_package)
        * [upserv\_saved\_remote\_package\_to\_local](#upserv_saved_remote_package_to_local)
        * [upserv\_checked\_remote\_package\_update](#upserv_checked_remote_package_update)
        * [upserv\_removed\_package](#upserv_removed_package)
        * [upserv\_before\_remote\_package\_zip](#upserv_before_remote_package_zip)
        * [upserv\_did\_browse\_package](#upserv_did_browse_package)
        * [upserv\_did\_read\_package](#upserv_did_read_package)
        * [upserv\_did\_edit\_package](#upserv_did_edit_package)
        * [upserv\_did\_add\_package](#upserv_did_add_package)
        * [upserv\_pre\_delete\_package](#upserv_pre_delete_package)
        * [upserv\_did\_delete\_package](#upserv_did_delete_package)
        * [upserv\_did\_download\_package](#upserv_did_download_package)
        * [upserv\_did\_signed\_url\_package](#upserv_did_signed_url_package)
        * [upserv\_package\_api\_request](#upserv_package_api_request)
        * [upserv\_remote\_sources\_options\_updated](#upserv_remote_sources_options_updated)
        * [upserv\_package\_options\_updated](#upserv_package_options_updated)
        * [upserv\_check\_remote\_update](#upserv_check_remote_update)
        * [upserv\_udpdate\_manager\_request\_action](#upserv_udpdate_manager_request_action)
        * [upserv\_package\_manager\_pre\_delete\_package](#upserv_package_manager_pre_delete_package)
        * [upserv\_package\_manager\_deleted\_package](#upserv_package_manager_deleted_package)
        * [upserv\_package\_manager\_pre\_delete\_packages\_bulk](#upserv_package_manager_pre_delete_packages_bulk)
        * [upserv\_package\_manager\_deleted\_packages\_bulk](#upserv_package_manager_deleted_packages_bulk)
        * [upserv\_before\_packages\_download\_repack](#upserv_before_packages_download_repack)
        * [upserv\_triggered\_packages\_download](#upserv_triggered_packages_download)
        * [upserv\_after\_packages\_download](#upserv_after_packages_download)
        * [upserv\_get\_package\_info](#upserv_get_package_info-1)
        * [upserv\_find\_package\_no\_cache](#upserv_find_package_no_cache)
        * [upserv\_update\_server\_action\_download](#upserv_update_server_action_download)
        * [upserv\_webhook\_before\_handling\_request](#upserv_webhook_before_handling_request)
        * [upserv\_webhook\_before\_processing\_request](#upserv_webhook_before_processing_request)
        * [upserv\_webhook\_after\_processing\_request](#upserv_webhook_after_processing_request)
        * [upserv\_webhook\_after\_handling\_request](#upserv_webhook_after_handling_request)
        * [upserv\_webhook\_invalid\_request](#upserv_webhook_invalid_request)
        * [upserv\_packages\_table\_cell](#upserv_packages_table_cell)
        * [upserv\_whitelist\_package](#upserv_whitelist_package-1)
        * [upserv\_unwhitelist\_package](#upserv_unwhitelist_package-1)
        * [upserv\_set\_package\_metadata](#upserv_set_package_metadata-1)
        * [upserv\_delete\_package\_metadata](#upserv_delete_package_metadata)
    * [Filters](#filters)
        * [upserv\_submitted\_package\_config](#upserv_submitted_package_config)
        * [upserv\_submitted\_remote\_sources\_config](#upserv_submitted_remote_sources_config)
        * [upserv\_schedule\_cleanup\_frequency](#upserv_schedule_cleanup_frequency)
        * [upserv\_check\_remote\_frequency](#upserv_check_remote_frequency)
        * [upserv\_handle\_update\_request\_params](#upserv_handle_update_request_params)
        * [upserv\_update\_checker](#upserv_update_checker)
        * [upserv\_cloud\_storage\_virtual\_dir](#upserv_cloud_storage_virtual_dir)
        * [upserv\_cloud\_storage\_config](#upserv_cloud_storage_config)
        * [upserv\_package\_api\_config](#upserv_package_api_config)
        * [upserv\_package\_browse](#upserv_package_browse)
        * [upserv\_package\_read](#upserv_package_read)
        * [upserv\_package\_edit](#upserv_package_edit)
        * [upserv\_package\_add](#upserv_package_add)
        * [upserv\_package\_delete](#upserv_package_delete)
        * [upserv\_package\_signed\_url](#upserv_package_signed_url)
        * [upserv\_package\_signed\_url\_token](#upserv_package_signed_url_token)
        * [upserv\_package\_public\_api\_actions](#upserv_package_public_api_actions)
        * [upserv\_package\_api\_request\_authorized](#upserv_package_api_request_authorized)
        * [upserv\_packages\_table\_columns](#upserv_packages_table_columns)
        * [upserv\_packages\_table\_sortable\_columns](#upserv_packages_table_sortable_columns)
        * [upserv\_packages\_table\_bulk\_actions](#upserv_packages_table_bulk_actions)
        * [upserv\_server\_class\_name](#upserv_server_class_name)
        * [upserv\_server\_constructor\_args](#upserv_server_constructor_args)
        * [upserv\_delete\_packages\_bulk\_paths](#upserv_delete_packages_bulk_paths)
        * [upserv\_package\_manager\_get\_package\_info](#upserv_package_manager_get_package_info)
        * [upserv\_package\_manager\_package\_info](#upserv_package_manager_package_info)
        * [upserv\_package\_info\_include](#upserv_package_info_include)
        * [upserv\_package\_manager\_get\_batch\_package\_info](#upserv_package_manager_get_batch_package_info)
        * [upserv\_package\_manager\_batch\_package\_info](#upserv_package_manager_batch_package_info)
        * [upserv\_check\_remote\_package\_update\_local\_meta](#upserv_check_remote_package_update_local_meta)
        * [upserv\_check\_remote\_package\_update\_no\_local\_meta\_needs\_update](#upserv_check_remote_package_update_no_local_meta_needs_update)
        * [upserv\_remove\_package\_result](#upserv_remove_package_result)
        * [upserv\_update\_server\_action\_download\_handled](#upserv_update_server_action_download_handled)
        * [upserv\_save\_remote\_to\_local](#upserv_save_remote_to_local)
        * [upserv\_download\_remote\_package](#upserv_download_remote_package-1)
        * [upserv\_vcs\_filter\_packages](#upserv_vcs_filter_packages)
        * [upserv\_pre\_filter\_packages\_info](#upserv_pre_filter_packages_info)
        * [upserv\_filter\_packages\_info](#upserv_filter_packages_info)
        * [upserv\_filter\_packages\_filename](#upserv_filter_packages_filename)
        * [upserv\_webhook\_vcs\_url](#upserv_webhook_vcs_url)
        * [upserv\_webhook\_secret](#upserv_webhook_secret)
        * [upserv\_webhook\_signature](#upserv_webhook_signature)
        * [upserv\_webhook\_validate\_request](#upserv_webhook_validate_request)
        * [upserv\_webhook\_package\_exists](#upserv_webhook_package_exists)
        * [upserv\_webhook\_process\_request](#upserv_webhook_process_request)
        * [upserv\_webhook\_response](#upserv_webhook_response)
        * [upserv\_package\_option\_update](#upserv_package_option_update)
        * [upserv\_remote\_sources\_option\_save\_value](#upserv_remote_sources_option_save_value)
        * [upserv\_remote\_source\_option\_update](#upserv_remote_source_option_update)
        * [upserv\_api\_package\_actions](#upserv_api_package_actions)
        * [upserv\_is\_package\_whitelisted](#upserv_is_package_whitelisted-1)
        * [upserv\_whitelist\_package\_data](#upserv_whitelist_package_data)
        * [upserv\_set\_package\_metadata\_data](#upserv_set_package_metadata_data)
        * [upserv\_did\_set\_package\_metadata](#upserv_did_set_package_metadata)
        * [upserv\_get\_package\_metadata](#upserv_get_package_metadata-1)
        * [upserv\_did\_delete\_package\_metadata](#upserv_did_delete_package_metadata)

## API

The Package API is accessible via POST and GET requests on the `/updatepulse-server-package-api/` endpoint for both the Public and Private API, and via POST only for the Private API. It accepts form-data payloads (arrays, basically). This documentation page uses `wp_remote_post`, but `wp_remote_get` would work as well for the Public API.

In case the API is accessed with an invalid `action` parameter, the following response is returned (message's language depending on available translations):

Code `400` - invalid action:
```json
{
    "code": "action_not_found",
    "message": "Package API action not found."
}
```

The description of the API further below is using the following code as reference, where `$payload` is the body sent to the API, `$headers` are the headers sent to the API, and `$response` is the response received from the API:

```php
$url      = 'https://domain.tld/updatepulse-server-package-api/package-type/package-slug/'; // Replace domain.tld with the domain where UpdatePulse Server is installed, package-type with the type of package (plugin, theme. generic), and package-slug with the slug of the package  
$payload  = array(
    'action'    => $action, // Action to perform when calling the Package API (required)
    'api_token' => 'token', // The authentication token (optional - must provided via X-UpdatePulse-Token header if absent)
);
$headers  = array(
    'X-UpdatePulse-Token' => 'token', // The authentication token (optional - must provided via `api_token` in the body if absent)
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

Note that in case the body needs to include a file (such as in the case of [add](#add) or [edit](#edit) actions of the [Private API](#private-api)), it is not possible to reliably use `wp_remote_post`.
Also note the presence of the `'FILE_HASH'` header - it is **required** when uploading a file and is used to verify the integrity of the file.
In these cases, the examples below use the following code as reference:

```php
$url      = 'https://domain.tld/updatepulse-server-package-api/package-type/package-slug/'; // Replace domain.tld with the domain where UpdatePulse Server is installed, package-type with the type of package (plugin, theme. generic), and package-slug with the slug of the package  
$payload  = array(
    'file'   => new CURLFile(  // The file to upload
        $file_path,
        mime_content_type( $file_path ),
        $file_name
    ),
    'action'    => $action,    // Action to perform when calling the Package API (required)
    'api_token' => 'token',    // The authentication token (optional - must provided via X-UpdatePulse-Token header if absent)
);
$headers  = array(
    'X-UpdatePulse-Token: ' . $token,                  // The authentication token
    'FILE_HASH: ' . hash_file( 'sha256', $file_path ), // The hash of the file
);

$ch = curl_init();

curl_setopt_array(
    $ch,
    array(
        CURLOPT_URL            => $url,
        CURLOPT_POST           => true,
        CURLOPT_HTTPHEADER     => $headers,
        CURLOPT_POSTFIELDS     => $payload,
        // other CURLOPT_* options can be set here
    )
);

$response_body = curl_exec( $ch );
$response_code = curl_getinfo( $ch, CURLINFO_HTTP_CODE );
$curl_error    = curl_error( $ch );

curl_close( $ch );

if ( ! empty( $curl_error ) ) {
    printf( esc_html__( 'Something went wrong: %s', 'text-domain' ), esc_html( $curl_error ) );
} else {
    $data = json_decode( $response_body, true );

    if ( 200 === intval( $response_code ) ) {
        // Handle success with $data
    } else {
        // Handle failure with $data
    }
}
```

___
### Public API

The public API requires authorization through a nonce or token acquired via the [Nonce API](https://github.com/anyape//blob/master/misc.md#nonce-api).

It provides a single operations: `download`.  

**The full URL is more easily acquired through the [signed_url](#signed_url) operation of the Private API. The URL provided by the `signed_url` operation already contains a valid token.**  

The URL can also be built manually, with a token can also be acquired with the following required parameters:

```php
$payload = array(
    'data' => array(
        'package_slug' => 'package-slug', // The slug of the package  
        'type'         => 'package-type', // The type of package (plugin, theme, generic)
        'actions'      => array(          // The actions the token can be used for
            'download',
        ),
    ),
);
```

___
#### download

The `download` operation retrieves a package file. If no corresponding package exists on the file system, the operation fails.

```php
$url = 'https://domain.tld/updatepulse-server-package-api/package-type/package-slug/'; // Replace domain.tld with the domain where UpdatePulse Server is installed, package-type with the type of package (plugin, theme. generic), and package-slug with the slug of the package  
```

```php
$payload = array(
    'action' => 'download',    // Action to perform when calling the Package API (required)
    'token'  => 'nonce_value', // The authorization token (required)
);
```

Response - **success**:
```
[filestream]
```

Code `404` - **failure** (no result):
```json
{
    "code": "package_not_found",
    "message": "Package not found."
}
```

___
### Private API

The Private API, only accessible via the POST method, requires extra authentication for all its actions - `browse`, `edit`, `add`, `delete`.  
The first action, `browse`, is particular in the sense that, unlike the other actions, its endpoint must not include the `package-type/package-slug` part of the query string (`$url = 'https://domain.tld/updatepulse-server-package-api/';`).  
With the Private API, depending on granted privileges, developers can theoretically perform any operation on the packages stored by UpdatePulse Server - **be careful to keep the Private API Authentication Key an absolute secret!**

To access the Private API, an authentication token must first be obtained with the [Nonce API](https://github.com/anyape//blob/master/misc.md#nonce-api) - for example:

```php
$url        = 'https://domain.tld/updatepulse-server-token/'; // Replace domain.tld with the domain where UpdatePulse Server is installed.
$api_key_id = getenv( 'UPSERV_LICENSE_API_KEY_ID' );          // This exampe assumes the API Key ID is stored in environment variables
$api_key    = getenv( 'UPSERV_LICENSE_API_KEY' );             // This exampe assumes the API Key is stored in environment variables
$payload    = array(
    'api' => 'package', // The target API (required - must be `'package'` to access the package Private API)
);
$cred_sign  = upserv_build_nonce_api_signature( $api_key_id, $api_key, time(), $payload );

$payload['api_credentials'] = $cred_sign['credentials'], // The credentials acting as public key `timestamp|key_id`, where `timestamp` is a past timestamp no older than 1 minutes, and `key_id` is the ID corresponding to the Private API Key (optional - must be provided in case X-UpdatePulse-API-Credentials header is absent)
$payload['api_signature']   = $cred_sign['signature'],   // The signature built using the Private API Key (optional - must be provided in case X-UpdatePulse-API-Signature header is absent)

$response = wp_remote_post(
    $url,
    array(
        'headers' => array(
            'X-UpdatePulse-API-Credentials' => $cred_sign['credentials'], // The credentials acting as public key `timestamp|key_id`, where `timestamp` is a past timestamp no older than 1 minutes, and `key_id` is the ID corresponding to the Private API Key (optional - must be provided in case `api_credentials` is absent from the body)
            'X-UpdatePulse-API-Signature'   => $cred_sign['signature'],   // The signature built using the Private API Key (optional - must be provided in case `api_signature` is absent from the body)
        ),
        'body'    => $payload
    );
);

if ( is_wp_error( $response ) ) {
    printf( esc_html__( 'Something went wrong: %s', 'text-domain' ), esc_html( $response->get_error_message() ) );
} else {
    $data = wp_remote_retrieve_body( $response );

    if ( '200' === $response['response']['code'] ) {
        $data       = json_decode( $data, true );
        $api_token  = $data['nonce'];
        $single_use = (bool) $data['true_nonce'];
        $expiry     = $data['expiry'];
        $data       = $data['data'];
        
        // Handle success with $api_token, $single_use, $expiry, $data, for example byt storing the token and implementing a mechanism to renew it when it expires
    } else {
        // Handle failure with $data
    }
}
```

In the above example, the `$data` variable looks like:

```json
{
    "nonce": "e7466375e8c851564653c6f7de81cd8f", // the authentication token
    "true_nonce": false,                         // whether the token can be only used once before it expires
    "expiry": 9999999999,                        // when the token expires - default is +30 minutes
    "data": {                                    // the data stored with the token
        "package_api": {                         // the package API data corresponding to the API key, generated by UpdatePulse Server
            "id": "api_key_id",                  // the ID of the API key
            "access": [                          // the list of authorized access privileges - `all` means access to everything related to packages on UpdatePulse Server
                ...
            ]
        }
    }
}
```
Once an authentication token has been obtained, it needs to be provided to API actions, either via the `api_token` parameter, or by passing a `X-UpdatePulse-Token` header (recommended - it is then found in `$_SERVER['HTTP_X_UPDATEPULSE_TOKEN']` in PHP).  
In case the token is invalid, all the actions of the Private API return the same response (message's language depending on available translations):

Code `403` - forbidden:
```json
{
    "code": "unauthorized",
    "message": "Unauthorized access."
}
```
In case the Private API is accessed via the `GET` method, all the actions return the same response (message's language depending on available translations):

Code `405` - unauthorized method:

```json
{
    "code": "method_not_allowed",
    "message": "Unauthorized GET method."
}
```
___
#### browse

The `browse` operation retrieves package information, optionally filtered by a search keyword. If no corresponding package exists on the file system, or in the Version Control System, the operation fails.

```php
$url = 'https://domain.tld/updatepulse-server-package-api/'; // Replace domain.tld with the domain where UpdatePulse Server is installed  
```

```php
$payload = array(
    'action'       => 'browse',         // Action to perform when calling the Package API (required)
    'browse_query' => wp_json_encode(   
        array( 'search' => 'keyword' )
    ),                                 // the JSON representation of an array with a single key 'search' with the value being the keyword used to search in package's slug and package's name (optional - case insensitive)
    'api_token'    => 'token',         // The authentication token (optional - must provided via X-UpdatePulse-Token header if absent)
);
```

Code `200` - **success**:
```json
{
    "theme-slug": {
        ...
    },
    "plugin-slug": {
       ...
    },
    "generic-slug": {
       ...
    },
    ...,
    "count": 99,
    "time_elapsed": "0.025"
}
```

Code `404` - **failure** (no result):

```json
{
    "code": "no_packages_found",
    "message": "No packages found."
}
```

___
#### read

The `read` operation retrieves information for the specified package. If the package does not exist on the file system, or if the package does not exist in the Version Control System, the operation fails.

```php
$url = 'https://domain.tld/updatepulse-server-package-api/package-type/package-slug/'; // Replace domain.tld with the domain where UpdatePulse Server is installed, package-type with the type of package (plugin, theme, generic), and package-slug with the slug of the package  
```

```php
$payload = array(
    'action'    => 'read',  // Action to perform when calling the Package API (required)
    'api_token' => 'token', // The authentication token (optional - must provided via X-UpdatePulse-Token header if absent)
);
```

Code `200` - **success**:

Values format in case of a plugin package:
```json
{
    "name": "Plugin Name",
    "version": "1.4.14",
    "homepage": "https:\/\/domain.tld\/",
    "author": "Author",
    "author_homepage": "https:\/\/domain.tld\/",
    "requires": "9.9.9",
    "tested": "9.9.9",
    "requires_php": "8.2",
    "sections": {
        "description": "<p>Plugin description. <strong>Basic HTML<\/strong> can be used in all sections.<\/p>",
        "extra_section": "<p>An extra section.<\/p>",
        "installation": "<p>Installation instructions.<\/p>",
        "changelog": "<p>This section will be displayed by default when the user clicks 'View version x.y.z details'.<\/p>"
    },
    "last_updated": "9999-00-00 99:99:99",
    "icons": {
        "1x": "https:\/\/domain.tld\/icon-128x128.png",
        "2x": "https:\/\/domain.tld\/icon-256x256.png"
    },
    "banners": {
        "low": "https:\/\/domain.tld\/banner-772x250.png",
        "high": "https:\/\/domain.tld\/banner-1544x500.png"
    },
    "require_license": "",
    "slug": "plugin-slug",
    "type": "plugin",
    "file_name": "plugin-slug.zip",
    "file_path": "\/webroot\/wp-content\/updatepulse-server\/packages\/plugin-slug.zip",
    "file_size": 999,
    "file_last_modified": 9999,
    "etag": "659b08d161917b3730ec864f032ddfd2",
    "digests": {
        "crc32": "2e052a03",
        "crc32c": "96f0852b",
        "sha1": "a9c1a1578b66a8f7c9af14b6e250355adb93350c",
        "sha256": "6e1acdb295fc9ba4a394397eec23e8b49d07b124256ff90655e1eb4e434a6b2d",
        "sha512": "f6f2b9e05164bc5c7355c7e43decabab90ddc2198aa22a8c7057d221575b06570df9a571797b5b070475e3ddb10b73a28d1440dbc86ea6a76479046e19c12488"
    },
    "time_elapsed": "0.025"
}
```

Values format in case of a theme package:
```json
{
    "name": "Theme Name",
    "version": "1.0.0",
    "homepage": "https:\/\/domain.tld\/",
    "author": "Author",
    "author_homepage": "https:\/\/domain.tld",
    "details_url": "https:\/\/domain.tld\/",
    "last_updated": "9999-00-00 99:99:99",
    "icons": {
        "1x": "https:\/\/domain.tld\/icon-128x128.png",
        "2x": "https:\/\/domain.tld\/icon-256x256.png"
    },
    "banners": {
        "low": "https:\/\/domain.tld\/banner-772x250.png",
        "high": "https:\/\/domain.tld\/banner-1544x500.png"
    },
    "require_license": "",
    "slug": "theme-slug",
    "type": "theme",
    "file_name": "theme-slug.zip",
    "file_path": "cloudStorage:\/updatepulse-packages\/theme-slug.zip",
    "file_size": 999,
    "file_last_modified": 9999,
    "etag": "3430580951143a26c4990e51b20f10d8",
    "digests": {
        "crc32": "9c589e52",
        "crc32c": "f5b14062",
        "sha1": "7d62fd20935526bfd976abdd773b251cae17fb17",
        "sha256": "3d61933a6d8a71e6debf0635b697fbf947913d0cacf310a6ddd69b2a1e7b6437",
        "sha512": "1a38954ced94dba57081eed3579e60f6b666a6a795490937b210a9818bfe871bafc8a29a137dde6807ae03f203a57e5eb09173dd81788017f4711414f3d76c17"
    },
    "time_elapsed": "0.025"
}
```

Values format in case of a generic package:
```json
{
    "name": "Generic Package Name",
    "version": "1.0.0",
    "homepage": "https:\/\/domain.tld\/",
    "author": "Author",
    "author_homepage": "https:\/\/domain.tld",
    "description": "Generic package description.",
    "last_updated": "9999-00-00 99:99:99",
    "icons": {
        "1x": "https:\/\/domain.tld\/icon-128x128.png",
        "2x": "https:\/\/domain.tld\/icon-256x256.png"
    },
    "banners": {
        "low": "https:\/\/domain.tld\/banner-772x250.png",
        "high": "https:\/\/domain.tld\/banner-1544x500.png"
    },
    "require_license": "",
    "slug": "generic-slug",
    "type": "generic",
    "file_name": "generic-slug.zip",
    "file_path": "cloudStorage:\/updatepulse-packages\/generic-slug.zip",
    "file_size": 999,
    "file_last_modified": 9999,
    "etag": "5029323eae58a054885b870303e51437",
    "digests": {
        "crc32": "fbd0e585",
        "crc32c": "e4f2f215",
        "sha1": "53b1078d4a3cc3f72ec3140b76d35d21e3a503be",
        "sha256": "dc3b9a4cccd038fb5c335664891ecb0edd95f1314a2c19afe63e6334590364bc",
        "sha512": "c6685c6cb30b9e63fc189de5cce4694fe0719afe44067b55fbc9234aee6dde0308f0575f0ffac9c7dbc1cf9ba87b587e9fd0c980738bf3e7bb25dd5946ab142d"
    },
    "time_elapsed": "0.025"
}
```

Code `404` - **failure** (no result):

```json
{
    "code": "package_not_found",
    "message": "Package not found."
}
```

___
#### edit

The `edit` operation replaces a package on the file system.
The operation fails if:
- the package does not exist on the file system
- a file is provided in the request, but the file is not a valid package file
- *not* using a VCS, and no package file is provided in the request
- a file is provided in the request, but the hash provided in the headers does not match the hash of the file
- VCS information is provided, but the VCS information is invalid

```php
$url = 'https://domain.tld/updatepulse-server-package-api/package-type/package-slug/'; // Replace domain.tld with the domain where UpdatePulse Server is installed, package-type with the type of package (plugin, theme, generic), and package-slug with the slug of the package  
```

```php
// In addition to already set headers, headers must include the hash of the file if a package file is provided
$headers = array_merge( $headers, array( 'FILE_HASH' => hash_file( 'sha256', $file_path ) ) );
```

```php
$payload = array(
    'action'    => 'edit',                            // Action to perform when calling the Package API (required)
    'api_token' => 'token',                           // The authentication token (optional - must provided via X-UpdatePulse-Token header if absent)
    'vcs_url'   => 'https://vcs-url.tld/identifier/', // The URL of a VCS configured in UpdatePulse Server. If provided along with a valid branch, and a file is not provided, the package will be downloaded from the VCS. The replaced package file will also be associated with that VCS. (optional - default `false`)
    'branch'    => 'main',                            // The branch of the VCS to download the package from. If provided along with a valid VCS URL, and a file is not provided, the package will be downloaded from the VCS. The replaced package file will also be associated with that VCS. (optional - default `main`)
    'file'      => new CURLFile(                      // The package file to upload (optional)
        $file_path,
        mime_content_type( $file_path ),
        $file_name
    ),
);
```

Code `200` - **success**:

Values format in case of a plugin package:
```json
{
    "name": "Plugin Name",
    "version": "1.4.14",
    "homepage": "https:\/\/domain.tld\/",
    "author": "Author",
    "author_homepage": "https:\/\/domain.tld\/",
    "requires": "9.9.9",
    "tested": "9.9.9",
    "requires_php": "8.2",
    "sections": {
        "description": "<p>Plugin description. <strong>Basic HTML<\/strong> can be used in all sections.<\/p>",
        "extra_section": "<p>An extra section.<\/p>",
        "installation": "<p>Installation instructions.<\/p>",
        "changelog": "<p>This section will be displayed by default when the user clicks 'View version x.y.z details'.<\/p>"
    },
    "last_updated": "9999-00-00 99:99:99",
    "icons": {
        "1x": "https:\/\/domain.tld\/icon-128x128.png",
        "2x": "https:\/\/domain.tld\/icon-256x256.png"
    },
    "banners": {
        "low": "https:\/\/domain.tld\/banner-722x250.png",
        "high": "https:\/\/domain.tld\/banner-1544x500.png"
    },
    "require_license": "",
    "slug": "plugin-slug",
    "type": "plugin",
    "file_name": "plugin-slug.zip",
    "file_path": "\/webroot\/wp-content\/updatepulse-server\/packages\/plugin-slug.zip",
    "file_size": 999,
    "file_last_modified": 9999,
    "etag": "659b08d161917b3730ec864f032ddfd2",
    "digests": {
        "crc32": "2e052a03",
        "crc32c": "96f0852b",
        "sha1": "a9c1a1578b66a8f7c9af14b6e250355adb93350c",
        "sha256": "6e1acdb295fc9ba4a394397eec23e8b49d07b124256ff90655e1eb4e434a6b2d",
        "sha512": "f6f2b9e05164bc5c7355c7e43decabab90ddc2198aa22a8c7057d221575b06570df9a571797b5b070475e3ddb10b73a28d1440dbc86ea6a76479046e19c12488"
    },
    "time_elapsed": "0.025"
}
```

Values format in case of a theme package:
```json
{
    "name": "Theme Name",
    "version": "1.0.0",
    "homepage": "https:\/\/domain.tld\/",
    "author": "Author",
    "author_homepage": "https:\/\/domain.tld",
    "details_url": "https:\/\/domain.tld\/",
    "last_updated": "9999-00-00 99:99:99",
    "icons": {
        "1x": "https:\/\/domain.tld\/icon-128x128.png",
        "2x": "https:\/\/domain.tld\/icon-256x256.png"
    },
    "banners": {
        "low": "https:\/\/domain.tld\/banner-772x250.png",
        "high": "https:\/\/domain.tld\/banner-1544x500.png"
    },
    "require_license": "",
    "slug": "theme-slug",
    "type": "theme",
    "file_name": "theme-slug.zip",
    "file_path": "cloudStorage:\/updatepulse-packages\/theme-slug.zip",
    "file_size": 999,
    "file_last_modified": 9999,
    "etag": "3430580951143a26c4990e51b20f10d8",
    "digests": {
        "crc32": "9c589e52",
        "crc32c": "f5b14062",
        "sha1": "7d62fd20935526bfd976abdd773b251cae17fb17",
        "sha256": "3d61933a6d8a71e6debf0635b697fbf947913d0cacf310a6ddd69b2a1e7b6437",
        "sha512": "1a38954ced94dba57081eed3579e60f6b666a6a795490937b210a9818bfe871bafc8a29a137dde6807ae03f203a57e5eb09173dd81788017f4711414f3d76c17"
    },
    "time_elapsed": "0.025"
}
```

Values format in case of a generic package:
```json
{
    "name": "Generic Package Name",
    "version": "1.0.0",
    "homepage": "https:\/\/domain.tld\/",
    "author": "Author",
    "author_homepage": "https:\/\/domain.tld",
    "description": "Generic package description.",
    "last_updated": "9999-00-00 99:99:99",
    "icons": {
        "1x": "https:\/\/domain.tld\/icon-128x128.png",
        "2x": "https:\/\/domain.tld\/icon-256x256.png"
    },
    "banners": {
        "low": "https:\/\/domain.tld\/banner-772x250.png",
        "high": "https:\/\/domain.tld\/banner-1544x500.png"
    },
    "require_license": "",
    "slug": "generic-slug",
    "type": "generic",
    "file_name": "generic-slug.zip",
    "file_path": "cloudStorage:\/updatepulse-packages\/generic-slug.zip",
    "file_size": 999,
    "file_last_modified": 9999,
    "etag": "5029323eae58a054885b870303e51437",
    "digests": {
        "crc32": "fbd0e585",
        "crc32c": "e4f2f215",
        "sha1": "53b1078d4a3cc3f72ec3140b76d35d21e3a503be",
        "sha256": "dc3b9a4cccd038fb5c335664891ecb0edd95f1314a2c19afe63e6334590364bc",
        "sha512": "c6685c6cb30b9e63fc189de5cce4694fe0719afe44067b55fbc9234aee6dde0308f0575f0ffac9c7dbc1cf9ba87b587e9fd0c980738bf3e7bb25dd5946ab142d"
    },
    "time_elapsed": "0.025"
}
```

Code `404` - **failure** (no result):

```json
{
    "code": "package_not_found",
    "message": "Package not found."
}
```

Code `400` - **failure** (invalid file hash):

```json
{
    "code": "invalid_hash",
    "message": "The provided file does not match the provided hash."
}
```

Code `400` - **failure** (invalid file type):

```json
{
    "code": "invalid_file_type",
    "message": "The provided file is not a valid ZIP file."
}
```

Code `400` - **failure** (invalid package):
```json
{
    "code": "invalid_package",
    "message": "The provided file is not a valid package."
}
```

Code `400` - **failure** (invalid parameters):
```json
{
    "code": "invalid_parameters",
    "message": "Package could not be edited - invalid parameters."
}
```

___
#### add

The `add` operation adds a package to the file system.
- the package exist on the file system
- a file is provided in the request, but the file is not a valid package file
- *not* using a VCS, and no package file is provided in the request
- a file is provided in the request, but the hash provided in the headers does not match the hash of the file
- VCS information is provided, but the VCS information is invalid

```php
$url = 'https://domain.tld/updatepulse-server-package-api/package-type/package-slug/'; // Replace domain.tld with the domain where UpdatePulse Server is installed, package-type with the type of package (plugin, theme, generic), and package-slug with the slug of the package  
```

```php
// In addition to already set headers, headers must include the hash of the file if a package file is provided
$headers = array(
    'FILE_HASH' => hash_file( 'sha256', $file_path ),
);
```

```php
$payload = array(
    'action'    => 'add',                             // Action to perform when calling the Package API (required)
    'api_token' => 'token',                           // The authentication token (optional - must provided via X-UpdatePulse-Token header if absent)
    'vcs_url'   => 'https://vcs-url.tld/identifier/', // The URL of a VCS configured in UpdatePulse Server. If provided along with a valid branch, and a file is not provided, the package will be downloaded from the VCS. The added package file will also be associated with that VCS. (optional - default `false`)
    'branch'    => 'main',                            // The branch of the VCS to download the package from. If provided along with a valid VCS URL, and a file is not provided, the package will be downloaded from the VCS. The added package file will also be associated with that VCS. (optional - default `main`)
    'file'      => new CURLFile(                      // The package file to upload (optional)
        $file_path,
        mime_content_type( $file_path ),
        $file_name
    ),
);
```

Code `200` - **success**:

Values format in case of a plugin package:
```json
{
    "name": "Plugin Name",
    "version": "1.4.14",
    "homepage": "https:\/\/domain.tld\/",
    "author": "Author",
    "author_homepage": "https:\/\/domain.tld\/",
    "requires": "9.9.9",
    "tested": "9.9.9",
    "requires_php": "8.2",
    "sections": {
        "description": "<p>Plugin description. <strong>Basic HTML<\/strong> can be used in all sections.<\/p>",
        "extra_section": "<p>An extra section.<\/p>",
        "installation": "<p>Installation instructions.<\/p>",
        "changelog": "<p>This section will be displayed by default when the user clicks 'View version x.y.z details'.<\/p>"
    },
    "last_updated": "9999-00-00 99:99:99",
    "icons": {
        "1x": "https:\/\/domain.tld\/icon-128x128.png",
        "2x": "https:\/\/domain.tld\/icon-256x256.png"
    },
    "banners": {
        "low": "https:\/\/domain.tld\/banner-722x250.png",
        "high": "https:\/\/domain.tld\/banner-1544x500.png"
    },
    "slug": "plugin-slug",
    "type": "plugin",
    "file_name": "plugin-slug.zip",
    "file_path": "\/webroot\/wp-content\/updatepulse-server\/packages\/plugin-slug.zip",
    "file_size": 999,
    "file_last_modified": 9999,
    "etag": "659b08d161917b3730ec864f032ddfd2",
    "digests": {
        "crc32": "2e052a03",
        "crc32c": "96f0852b",
        "sha1": "a9c1a1578b66a8f7c9af14b6e250355adb93350c",
        "sha256": "6e1acdb295fc9ba4a394397eec23e8b49d07b124256ff90655e1eb4e434a6b2d",
        "sha512": "f6f2b9e05164bc5c7355c7e43decabab90ddc2198aa22a8c7057d221575b06570df9a571797b5b070475e3ddb10b73a28d1440dbc86ea6a76479046e19c12488"
    },
    "time_elapsed": "0.025"
}
```

Values format in case of a theme package:
```json
{
    "name": "Theme Name",
    "version": "1.0.0",
    "homepage": "https:\/\/domain.tld\/",
    "author": "Author",
    "author_homepage": "https:\/\/domain.tld",
    "details_url": "https:\/\/domain.tld\/",
    "last_updated": "9999-00-00 99:99:99",
    "icons": {
        "1x": "https:\/\/domain.tld\/icon-128x128.png",
        "2x": "https:\/\/domain.tld\/icon-256x256.png"
    },
    "banners": {
        "low": "https:\/\/domain.tld\/banner-772x250.png",
        "high": "https:\/\/domain.tld\/banner-1544x500.png"
    },
    "require_license": "",
    "slug": "theme-slug",
    "type": "theme",
    "file_name": "theme-slug.zip",
    "file_path": "cloudStorage:\/updatepulse-packages\/theme-slug.zip",
    "file_size": 999,
    "file_last_modified": 9999,
    "etag": "3430580951143a26c4990e51b20f10d8",
    "digests": {
        "crc32": "9c589e52",
        "crc32c": "f5b14062",
        "sha1": "7d62fd20935526bfd976abdd773b251cae17fb17",
        "sha256": "3d61933a6d8a71e6debf0635b697fbf947913d0cacf310a6ddd69b2a1e7b6437",
        "sha512": "1a38954ced94dba57081eed3579e60f6b666a6a795490937b210a9818bfe871bafc8a29a137dde6807ae03f203a57e5eb09173dd81788017f4711414f3d76c17"
    },
    "time_elapsed": "0.025"
}
```

Values format in case of a generic package:
```json
{
    "name": "Generic Package Name",
    "version": "1.0.0",
    "homepage": "https:\/\/domain.tld\/",
    "author": "Author",
    "author_homepage": "https:\/\/domain.tld",
    "description": "Generic package description.",
    "last_updated": "9999-00-00 99:99:99",
    "icons": {
        "1x": "https:\/\/domain.tld\/icon-128x128.png",
        "2x": "https:\/\/domain.tld\/icon-256x256.png"
    },
    "banners": {
        "low": "https:\/\/domain.tld\/banner-772x250.png",
        "high": "https:\/\/domain.tld\/banner-1544x500.png"
    },
    "require_license": "",
    "slug": "generic-slug",
    "type": "generic",
    "file_name": "generic-slug.zip",
    "file_path": "cloudStorage:\/updatepulse-packages\/generic-slug.zip",
    "file_size": 999,
    "file_last_modified": 9999,
    "etag": "5029323eae58a054885b870303e51437",
    "digests": {
        "crc32": "fbd0e585",
        "crc32c": "e4f2f215",
        "sha1": "53b1078d4a3cc3f72ec3140b76d35d21e3a503be",
        "sha256": "dc3b9a4cccd038fb5c335664891ecb0edd95f1314a2c19afe63e6334590364bc",
        "sha512": "c6685c6cb30b9e63fc189de5cce4694fe0719afe44067b55fbc9234aee6dde0308f0575f0ffac9c7dbc1cf9ba87b587e9fd0c980738bf3e7bb25dd5946ab142d"
    },
    "time_elapsed": "0.025"
}
```

Code `409` - **failure** (package exists):
```json
{
    "code": "package_exists",
    "message": "Package already exists."
}
```

Code `400` - **failure** (invalid file hash):
```json
{
    "code": "invalid_hash",
    "message": "The provided file does not match the provided hash."
}
```

Code `400` - **failure** (invalid file type):
```json
{
    "code": "invalid_file_type",
    "message": "The provided file is not a valid ZIP file."
}
```

Code `400` - **failure** (invalid package):
```json
{
    "code": "invalid_package",
    "message": "The provided file is not a valid package."
}
```

Code `400` - **failure** (invalid parameters):
```json
{
    "code": "invalid_parameters",
    "message": "Package could not be added - invalid parameters."
}
```

___
#### delete

The `delete` operation deletes the package from the file system. If the package does not exist on the file system, the operation fails.

```php
$url = 'https://domain.tld/updatepulse-server-package-api/package-type/package-slug/'; // Replace domain.tld with the domain where UpdatePulse Server is installed, package-type with the type of package (plugin, theme, generic), and package-slug with the slug of the package  
```

```php
$payload = array(
    'action'    => 'delete', // Action to perform when calling the Package API (required)
    'api_token' => 'token',  // The authentication token (optional - must provided via X-UpdatePulse-Token header if absent)
);
```

Code `200` - **success**:
```json
true
```

Code `404` - **failure** (no result):

```json
{
    "code": "package_not_found",
    "message": "Package not found."
}
```
___
#### signed_url

The `signed_url` operation returns a public URL signed with a token to download a package with the `download` [operation](#download). By default, the token is reusable and the URL is valid for 60 minutes. If the package does not exist on the file system or in the Version Control System, the operation fails.

```php
$url = 'https://domain.tld/updatepulse-server-package-api/package-type/package-slug/'; // Replace domain.tld with the domain where UpdatePulse Server is installed, package-type with the type of package (plugin, theme, generic), and package-slug with the slug of the package  
```

```php
$payload = array(
    'action'    => 'signed_url', // Action to perform when calling the Package API (required)
    'api_token' => 'token',      // The authentication token (optional - must provided via X-UpdatePulse-Token header if absent)
);
```

Code `200` - **success**:
```json
{
    "url": "https://domain.tld/updatepulse-server-package-api/package-type/package-slug/?token=nonce_value&action=download",
    "token": "nonce_value",
    "expiry": 999999999,
    "time_elapsed": "0.025"
}
```

Code `404` - **failure** (no result):

```json
{
    "code": "package_not_found",
    "message": "Package not found."
}
```

___
## Functions

The functions listed below are made publicly available by the plugin for theme and plugin developers. They can be used after the action `plugins_loaded` has been fired, or in a `plugins_loaded` action (just make sure the priority is above `-99`).  
Although the main classes can theoretically be instantiated without side effect if the `$hook_init` parameter is set to `false`, it is recommended to use only the following functions as there is no guarantee future updates won't introduce changes of behaviors.

___
### upserv_get_data_dir

```php
upserv_get_data_dir( $dir );
```

**Description**
Get the path to a specific directory within the plugin's content directory.

**Parameters**
`$dir`
> (string) the directory to get the path for

**Return value**
> (string) the path to the specified directory within the plugin's content directory

__
### upserv_get_root_data_dir

```php
upserv_get_root_data_dir();
```

**Description**  
Get the path to the plugin's content directory.

**Return value**
> (string) the path to the plugin content's directory

___
### upserv_get_packages_data_dir

```php
upserv_get_packages_data_dir();
```

**Description**  
Get the path to the packages directory on the file system.

**Return value**
> (string) the path to the packages directory on the file system

___
### upserv_get_logs_data_dir

```php
upserv_get_logs_data_dir();
```

**Description**  
Get the path to the plugin's log directory.

**Return value**
> (string) the path to the plugin's log directory.

___
### upserv_get_cache_data_dir

```php
upserv_get_cache_data_dir();
```

**Description**
Get the path to the plugin's package cache directory.

**Return value**
> (string) the path to the plugin's package cache directory

___
### upserv_is_doing_update_api_request

```php
upserv_is_doing_update_api_request();
```

**Description**  
Determine whether the current request is made by a client plugin, theme, or generic package interacting with the plugin's API.

**Return value**
> (bool) `true` if the current request is a client plugin, theme, or generic package interacting with the plugin's API, `false` otherwise

___
### upserv_is_doing_package_api_request

```php
upserv_is_doing_package_api_request();
```

**Description**
Determine whether the current request is made by a remote client interacting with the plugin's package API.

**Return value**
> (bool) `true` the current request is made by a remote client interacting with the plugin's package API, `false` otherwise

___
### upserv_check_remote_package_update

```php
upserv_check_remote_package_update( $package_slug, $type );
```

**Description**  
Determine whether the remote package is an updated version compared to the one on the file system.

**Parameters**  
`$package_slug`
> (string) slug of the package to check   

`$type`
> (string) type of the package  

**Return value**
> (string) path of the package on the file system

___
### upserv_check_remote_plugin_update

```php
upserv_check_remote_plugin_update( string $package_slug );
```

**Description**  
Determine whether the remote plugin package is an updated version compared to one on the file system.

**Parameters**  
`$package_slug`
> (string) slug of the plugin package to check  

**Return value**
> (bool) `true` if the remote plugin package is an updated version, `false` otherwise. If the local package does not exist, returns `true`

___
### upserv_check_remote_theme_update

```php
upserv_check_remote_theme_update( string $package_slug );
```

**Description**  
Determine whether the remote theme package is an updated version compared to the one on the file system.

**Parameters**  
`$package_slug`
> (string) slug of the theme package to check   

**Return value**
> (bool) `true` if the remote theme package is an updated version, `false` otherwise. If the package does not exist on the file system, returns `true`

___
### upserv_download_remote_package

```php
upserv_download_remote_package( string $package_slug, string $type = 'generic', string|false $vcs_url = false, string $branch = 'main' );
```

**Description**  
Download a package from the Version Control System to the package directory on the file system.
If `$vcs_url` and `$branch` are provided, the plugin will attempt to get an existing VCS configuration and register the package with it.

**Parameters**  
`$package_slug`
> (string) slug of the package to download  

`$type`
> (string) type of the package; default to `'generic'`

`$vcs_url`
> (string|false) the URL of a VCS configured in UpdatePulse Server; default to `false`

`$branch`
> (string) the branch as provided in a VCS configured in UpdatePulse Server; default to `'main'`

**Return value**
> (bool|WP_Error) `WP_Error` if provided VCS information is invalid, `true` if the package was successfully downloaded, `false` otherwise

___
### upserv_get_package_vcs_config

```php
upserv_get_package_vcs_config( string $package_slug );
```

**Description**
Get the Version Control System (VCS) configuration for a package.

**Parameters**
`$package_slug`
> (string) slug of the package

**Return value**
> (array) the VCS configuration for the package
___
### upserv_download_remote_plugin

```php
upserv_download_remote_plugin( string $package_slug, string|false $vcs_url = false, string $branch = 'main' );
```

**Description**  
Download a plugin package from the Version Control System to the package directory on the file system.
If `$vcs_url` and `$branch` are provided, the plugin will attempt to get an existing VCS configuration and register the package with it.

**Parameters**  
`$package_slug`
> (string) slug of the plugin package to download  

`$vcs_url`
> (string|false) the URL of a VCS configured in UpdatePulse Server; default to `false`

`$branch`
> (string) the branch as provided in a VCS configured in UpdatePulse Server; default to `'main'`

**Return value**
> (bool|WP_Error) whether the package was downloaded, or an error when the VCS information is invalid

___
### upserv_download_remote_theme

```php
upserv_download_remote_theme( string $package_slug, string|false $vcs_url = false, string $branch = 'main' );
```

**Description**  
Download a theme package from the Version Control System to the package directory on the file system.
If `$vcs_url` and `$branch` are provided, the plugin will attempt to get an existing VCS configuration and register the package with it.

**Parameters**  
`$package_slug`
> (string) slug of the theme package to download  

`$vcs_url`
> (string|false) the URL of a VCS configured in UpdatePulse Server; default to `false`

`$branch`
> (string) the branch as provided in a VCS configured in UpdatePulse Server; default to `'main'`

**Return value**
> (bool|WP_Error) whether the package was downloaded, or an error when the VCS information is invalid

___
### upserv_force_cleanup_cache

```php
upserv_force_cleanup_cache();
```

**Description**  
Force clean up the `cache` plugin data.

**Return value**
> (bool) `true` in case of success, `false` otherwise

___
### upserv_force_cleanup_logs

```php
upserv_force_cleanup_logs();
```

**Description**  
Force clean up the `logs` plugin data.

**Return value**
> (bool) `true` in case of success, `false` otherwise

___
### upserv_force_cleanup_tmp

```php
upserv_force_cleanup_tmp();
```

**Description**  
Force clean up the `tmp` plugin data.

**Return value**
> (bool) `true` in case of success, `false` otherwise

___
### upserv_get_local_package_path

```php
upserv_get_local_package_path( string $package_slug );
```

**Description**  
Get the path of a plugin, theme, or generic package on the file system

**Parameters**  
`$package_slug`
> (string) slug of the package  

**Return value**
> (string) path of the package on the **local** file system

___
### upserv_download_local_package

```php
upserv_download_local_package( string $package_slug, ?string $package_path = null, bool $exit_or_die = true );
```

**Description**  
Stream a package from the file system and optionally terminate the request.

**Parameters**  
`$package_slug`
> (string) slug of the package  

`$package_path`
> (string|null) package path on the **local** file system, or `null` to locate it with `upserv_get_local_package_path( $package_slug )`

`$exit_or_die`
> (bool) whether to terminate the request after handling the download; default `true`

___
### upserv_delete_package

```php
upserv_delete_package( string $package_slug );
```

**Description**  
Deletes a package on the file system

**Parameters**  
`$package_slug`
> (string) slug of the package  

**Return value**
> (bool) whether the operation was successful
___
### upserv_get_package_info

```php
upserv_get_package_info( string $package_slug, bool $json_encode = true );
```

**Description**  
Get information about a package on the file system

**Parameters**  
`$package_slug`
> (string) slug of the package  

`$json_encode`
> (bool) whether to return a JSON string instead of a PHP associative array; default to `true`

**Return value**
> (array|string) the package information as a PHP associative array or a JSON string

Values format in case of a plugin package:
```json
{
    "name": "Plugin Name",
    "version": "1.4.14",
    "homepage": "https:\/\/domain.tld\/",
    "author": "Author",
    "author_homepage": "https:\/\/domain.tld\/",
    "requires": "9.9.9",
    "tested": "9.9.9",
    "requires_php": "8.2",
    "sections": {
        "description": "<p>Plugin description. <strong>Basic HTML<\/strong> can be used in all sections.<\/p>",
        "extra_section": "<p>An extra section.<\/p>",
        "installation": "<p>Installation instructions.<\/p>",
        "changelog": "<p>This section will be displayed by default when the user clicks 'View version x.y.z details'.<\/p>"
    },
    "last_updated": "9999-00-00 99:99:99",
    "icons": {
        "1x": "https:\/\/domain.tld\/icon-128x128.png",
        "2x": "https:\/\/domain.tld\/icon-256x256.png"
    },
    "banners": {
        "low": "https:\/\/domain.tld\/banner-772x250.png",
        "high": "https:\/\/domain.tld\/banner-1544x500.png"
    },
    "require_license": "",
    "slug": "plugin-slug",
    "type": "plugin",
    "file_name": "plugin-slug.zip",
    "file_path": "\/webroot\/wp-content\/updatepulse-server\/packages\/plugin-slug.zip",
    "file_size": 999,
    "file_last_modified": 9999
}
```

Values format in case of a theme package:
```json
{
    "name": "Theme Name",
    "version": "1.0.0",
    "homepage": "https:\/\/domain.tld\/",
    "author": "Author",
    "author_homepage": "https:\/\/domain.tld",
    "details_url": "https:\/\/domain.tld\/",
    "last_updated": "9999-00-00 99:99:99",
    "icons": {
        "1x": "https:\/\/domain.tld\/icon-128x128.png",
        "2x": "https:\/\/domain.tld\/icon-256x256.png"
    },
    "banners": {
        "low": "https:\/\/domain.tld\/banner-772x250.png",
        "high": "https:\/\/domain.tld\/banner-1544x500.png"
    },
    "require_license": "",
    "slug": "theme-slug",
    "type": "theme",
    "file_name": "theme-slug.zip",
    "file_path": "cloudStorage:\/updatepulse-packages\/theme-slug.zip",
    "file_size": 999,
    "file_last_modified": 9999
}
```

Values format in case of a generic package:
```json
{
    "name": "Generic Package Name",
    "version": "1.0.0",
    "homepage": "https:\/\/domain.tld\/",
    "author": "Author",
    "author_homepage": "https:\/\/domain.tld",
    "description": "Generic package description.",
    "last_updated": "9999-00-00 99:99:99",
    "icons": {
        "1x": "https:\/\/domain.tld\/icon-128x128.png",
        "2x": "https:\/\/domain.tld\/icon-256x256.png"
    },
    "banners": {
        "low": "https:\/\/domain.tld\/banner-772x250.png",
        "high": "https:\/\/domain.tld\/banner-1544x500.png"
    },
    "require_license": "",
    "slug": "generic-slug",
    "type": "generic",
    "file_name": "generic-slug.zip",
    "file_path": "cloudStorage:\/updatepulse-packages\/generic-slug.zip",
    "file_size": 999,
    "file_last_modified": 9999
}
```
___
### upserv_get_batch_package_info

```php
upserv_get_batch_package_info( string $search, bool $json_encode = true );
```

**Description**  
Get batch information of packages on the file system

**Parameters**  
`$search`
> (string) search string to be used in package's slug and package's name (case insensitive)  

`$json_encode`
> (bool) whether to return a JSON string (default) instead of a PHP associative array

**Return value**
> (array|string) the batch information as a PHP associative array or a JSON string; each entry is formatted like in [upserv_get_package_info](#upserv_get_package_info)

Values format:
```json
{
    "theme-slug": {
        ...
    },
    "plugin-slug": {
       ...
    },
    "generic-slug": {
       ...
    },
    ...
}

```
___
### upserv_is_package_require_license

```php
upserv_is_package_require_license( string $package_slug );
```

**Description**  
Determine whether a package requires a license key.

**Parameters**  
`$package_slug`
> (string) slug of the package  

**Return value**
> (bool) `true` if the package requires a license key, `false` otherwise

___
### upserv_is_package_whitelisted

```php
upserv_is_package_whitelisted( string $package_slug );
```

**Description**
Determine whether a package is whitelisted.

**Parameters**
`$package_slug`
> (string) slug of the package

**Return value**
> (bool) `true` if the package is whitelisted, `false` otherwise

___
### upserv_whitelist_package

```php
upserv_whitelist_package( string $package_slug );
```

**Description**
Whitelist a package.

**Parameters**
`$package_slug`
> (string) slug of the package

**Return value**
> (bool) `true` if the package was successfully whitelisted, `false` otherwise

___
### upserv_unwhitelist_package

```php
upserv_unwhitelist_package( string $package_slug );
```

**Description**
Unwhitelist a package.

**Parameters**
`$package_slug`
> (string) slug of the package

**Return value**
> (bool) `true` if the package was successfully unwhitelisted, `false` otherwise

___
### upserv_get_package_metadata_data_dir

```php
upserv_get_package_metadata_data_dir();
```

**Description**
Get the path to the packages metadata directory.

**Return value**
> (string) the path to the packages metadata directory

___
### upserv_get_package_metadata

```php
upserv_get_package_metadata( string $package_slug, bool $json_encode = false );
```

**Description**
Get metadata of a package.
By default, contains the metadata UpdatePulse needs to keep track of the package in case it is deleted from the file system.

**Parameters**
`$package_slug`
> (string) slug of the package

`$json_encode`
> (bool) whether to return a JSON string instead of a PHP associative array; default to `false`

**Return value**
> (array|string) the package metadata

### upserv_set_package_metadata

```php
upserv_set_package_metadata( string $package_slug, ?array $metadata );
```

**Description**
Set metadata of a package.
Setting metadata will overwrite the existing metadata.
Setting metadata to an empty value, including `null`, will delete the metadata record.

**Parameters**
`$package_slug`
> (string) slug of the package

`$metadata`
> (array|null) metadata to set, or an empty value to delete the metadata record

___
## Actions

UpdatePulse Server gives developers the possibility to have their plugins react to some events with a series of custom actions.  
**Warning**: the actions below with the mention "Fired during client update API request" need to be used with caution. Although they may also be triggered when using the functions above, these actions will possibly be called when client packages request for updates. Registering functions doing heavy computation to these actions when client update API requests are handled can seriously degrade the server's performances.  

___
### upserv_registered_package_from_vcs

```php
do_action( 'upserv_registered_package_from_vcs', bool $result, string $package_slug );
```

**Description**  
Fired after an attempt to register a package from a Version Control System has been performed.  

**Parameters**  
`$result`
> (bool) `true` the operation was successful, `false` otherwise  

`$package_slug`
> (string) slug of the package  

___
### upserv_did_manual_upload_package

```php
do_action( 'upserv_did_manual_upload_package', bool $result, string $type, string $package_slug );
```

**Description**  
Fired after an attempt to upload a package manually has been performed.  

**Parameters**  
`$result`
> (bool) `true` the operation was successful, `false` otherwise  

`$type`
> (string) type of package - `"Plugin"`, `"Theme"`, or `"Generic"`  

`$package_slug`
> (string) slug of the package  

___
### upserv_before_packages_download

```php
do_action( 'upserv_before_packages_download', string $archive_name, string $archive_path, array $package_slugs );
```

**Description**  
Fired before a packages download using the "Download" bulk action is triggered.  

**Parameters**  
`$archive_name`
> (string) the name of the archive containing the packages to be downloaded  

`$archive_path`
> (string) the path of the archive containing the packages to be downloaded  

`$package_slugs`
> (string) the slug of the packages to be downloaded  

___
### upserv_triggered_package_download

```php
do_action( 'upserv_triggered_package_download', string $archive_name, string $archive_path );
```

**Description**  
Fired after a download of an archive containing one or multiple packages has been triggered.  
Remark: at this point, headers have already been sent to the client of the request and registered actions should not produce any output.  

**Parameters**  
`$archive_name`
> (string) the name of the archive containing the packages to be downloaded  

`$archive_path`
> (string) the path of the archive containing the packages to be downloaded  

___
### upserv_scheduled_check_remote_event

```php
do_action( 'upserv_scheduled_check_remote_event', bool|int $result, string $package_slug, int $timestamp, string|false $frequency, string $hook, array $params );
```

**Description**  
Fired after a remote check event has been scheduled for a package.  
Fired during client update API request.  

**Parameters**  
`$result`
> (bool|int) the Action Scheduler action ID or WordPress cron scheduling result

`$package_slug`
> (string) slug of the package for which the event was scheduled  

`$timestamp`
> (int) timestamp for when to run the event the first time after it's been scheduled  

`$frequency`
> (string|false) recurring frequency, or `false` for a one-time webhook-triggered check

`$hook`
> (string) event hook to fire when the event is ran  

`$params`
> (array) parameters passed to the actions registered to $hook when the event is ran  

___
### upserv_registered_check_remote_schedule

```php
do_action( 'upserv_registered_check_remote_schedule', string $package_slug, string $scheduled_hook, callable $action_hook );
```

**Description**  
Fired during scheduler initialization after a remote check callback has been registered. This action is not fired during client update API requests because callback registration is explicitly skipped for those requests.

**Parameters**  
`$package_slug`
> (string) the slug of the package for which an action has been registered  

`$scheduled_hook`
> (string) the event hook the action has been registered to (see `upserv_scheduled_check_remote_event` action)  

`$action_hook`
> (callable) the callback that has been registered

___
### upserv_cleared_check_remote_schedule

```php
do_action( 'upserv_cleared_check_remote_schedule', string $package_slug, string $scheduled_hook );
```

**Description**  
Fired after a remote check schedule event has been unscheduled for a package. Depending on the caller, this occurs while disabling or rescheduling VCS checks, deleting a package, or handling a webhook.

**Parameters**  
`$package_slug`
> (string) the slug of the package for which a remote check event has been unscheduled  

`$scheduled_hook`
> (string) the remote check event hook that has been unscheduled  

___
### upserv_scheduled_cleanup_event

```php
do_action( 'upserv_scheduled_cleanup_event', bool|int $result, string $type, int $timestamp, string $frequency, string $hook, array $params );
```

**Description**  
Fired after a cleanup event has been scheduled for a type of plugin data.  

**Parameters**  
`$result`
> (bool|int) the Action Scheduler action ID or WordPress cron scheduling result

`$type`
> (string) plugin data type for which the event was scheduled (`cache`, `logs`,or `tmp`)  

`$timestamp`
> (int) timestamp for when to run the event the first time after it's been scheduled  

`$frequency`
> (string) frequency at which the event will run

`$hook`
> (string) event hook to fire when the event is ran  

`$params`
> (array) parameters passed to the actions registered to $hook when the event is ran  

___
### upserv_registered_cleanup_schedule

```php
do_action( 'upserv_registered_cleanup_schedule', string $type, array $params );
```

**Description**  
Fired during scheduler initialization after a cleanup callback has been registered for a type of plugin data. Callback registration is skipped during client update API requests.

**Parameters**  
`$type`
> (string) plugin data type for which a callback has been registered (`cache`, `logs`, or `tmp`)

`$params`
> (array) the parameters passed to the registered cleanup action  

___
### upserv_cleared_cleanup_schedule

```php
do_action( 'upserv_cleared_cleanup_schedule', string $type, array $params );
```

**Description**  
Fired after a cleanup schedule event has been unscheduled for a type of plugin data.  

**Parameters**  
`$type`
> (string) plugin data type for which a cleanup event has been unscheduled (`cache`, `logs`,or `tmp`)  

`$params`
> (array) the parameters that were passed to the actions registered to the cleanup event  

___
### upserv_did_cleanup

```php
do_action( 'upserv_did_cleanup', bool $result, string $type, int $size, bool $force );
```

**Description**  
Fired after cleanup was attempted for a type of plugin data.  

**Parameters**  
`$result`
> (bool) `true` if the clean up was successful, `false` otherwise  

`$type`
> (string) type of data to clean up (`cache`, `logs`,or `tmp`)  

`$size`
> (int) the size of the data in before the clean up attempt (in bytes)  

`$force`
> (bool) `true` if it was a forced cleanup (without checking if the size was beyond the maximum set size), `false` otherwise  

___
### upserv_before_handle_update_request

```php
do_action( 'upserv_before_handle_update_request', array $request_params );
```

**Description**  
Fired before handling the request made by a client plugin, theme, or generic package to the plugin's API.  
Fired during client update API request.  

**Parameters**
`$request_params`
> (array) the parameters or the request to the API.

___
### upserv_pre_filter_package_info

```php
do_action( 'upserv_pre_filter_package_info', array $info );
```

**Description**
Fired after pre-filtering the information of the package before it is retrieved from the Version Control System.  
Fired during client update API request.  

**Parameters**
`$info`
> (array) the information of the package

___
### upserv_filter_package_info

```php
do_action( 'upserv_filter_package_info', array $info );
```

**Description**
Fired after filtering the information of the package retrieved from the Version Control System.  
Fired during client update API request.  

**Parameters**
`$info`
> (array) the information of the package

___
### upserv_download_remote_package_aborted

```php
do_action( 'upserv_download_remote_package_aborted', string $package_slug, string $type, array $info );
```

**Description**
Fired after an attempt to download a package from the Version Control System has been aborted.
Fired during client update API request.  

**Parameters**
`$package_slug`
> (string) the slug of the package

`$type`
> (string) the type of the package - `"Plugin"`, `"Theme"`, or `"Generic"`

`$info`
> (array) the information of the package retrieved from the Version Control System

___
### upserv_downloaded_remote_package

```php
do_action( 'upserv_downloaded_remote_package', string|WP_Error $package, string $type, string $package_slug );
```

**Description**  
Fired after an attempt to download a package from the Version Control System to the file system has been performed.  
Fired during client update API request.  

**Parameters**  
`$package`
> (string|WP_Error) full path to the temporary package file, or a download error

`$type`
> (string) type of the downloaded package - `"Plugin"`, `"Theme"`, or `"Generic"`   

`$package_slug`
> (string) slug of the downloaded package  

___
### upserv_saved_remote_package_to_local

```php
do_action( 'upserv_saved_remote_package_to_local', bool $result, string $type, string $package_slug );
```

**Description**  
Fired after an attempt to save a downloaded package on the file system has been performed.  
Fired during client update API request.  

**Parameters**  
`$result`
> (bool) `true` in case of success, `false` otherwise  

`$type`
> (string) type of the saved package - `"Plugin"`, `"Theme"`, or `"Generic"`   

`$package_slug`
> (string) slug of the saved package  

___
### upserv_checked_remote_package_update

```php
do_action( 'upserv_checked_remote_package_update', bool $has_update, string $type, string $package_slug );
```

**Description**  
Fired after an update check on the Version Control System has been performed for a package.  
Fired during client update API request.  

**Parameters**  
`$has_update`
> (bool) `true` is the package has updates on the Version Control System, `false` otherwise  

`$type`
> (string) type of the package checked - `"Plugin"`, `"Theme"`, or `"Generic"`   

`$package_slug`
> (string) slug of the package checked  

___
### upserv_removed_package

```php
do_action( 'upserv_removed_package', bool $result, string $type, string $package_slug );
```

**Description**  
Fired after a package has been removed from the file system.  
Fired during client update API request.  

**Parameters**  
`$result`
> (bool) `true` if the package has been removed on the file system  

`$type`
> (string) type of the removed package - `"Plugin"`, `"Theme"`, or `"Generic"`   

`$package_slug`
> (string) slug of the removed package  

___
### upserv_before_remote_package_zip


```php
do_action( 'upserv_before_remote_package_zip', string $package_slug, string $files_path, string $archive_path );
```

**Description**  
Fired during VCS package repacking, before the extracted files are packed. Can be used for extra file manipulation.
Fired during client update API request.  

**Parameters**  
`$package_slug`
> (string) the slug of the package

`$files_path`
> (string) the path of the directory where the package files are located  

`$archive_path`
> (string) the path where the package archive will be located after packing  

___
### upserv_did_browse_package

```php
do_action( 'upserv_did_browse_package', array $result );
```

**Description**  
Fired after the `browse` Package API action.

**Parameters**  
`$result`
> (array) the result of the action  

___
### upserv_did_read_package

```php
do_action( 'upserv_did_read_package', array $result );
```

**Description**  
Fired after the `read` Package API action.

**Parameters**  
`$result`
> (array) the result of the action  

___
### upserv_did_edit_package

```php
do_action( 'upserv_did_edit_package', array $result );
```

**Description**  
Fired after the `edit` Package API action.

**Parameters**  
`$result`
> (array) the result of the action  

___
### upserv_did_add_package

```php
do_action( 'upserv_did_add_package', array $result );
```

**Description**  
Fired after the `add` Package API action.

**Parameters**  
`$result`
> (array) the result of the action  

___
### upserv_pre_delete_package

```php
do_action( 'upserv_pre_delete_package', string $package_slug, string $type );
```

**Description**  
Fired before the `delete` Package API action.  

**Parameters**  
`$package_slug`
> (string) the slug of the package to be deleted  

`$type`
> (string) the type of the package to be deleted  

___
### upserv_did_delete_package

```php
do_action( 'upserv_did_delete_package', bool $result, string $package_slug, string $type );
```

**Description**  
Fired after the `delete` Package API action.  

**Parameters**  
`$result`
> (bool) the result of the `delete` operation  

`$package_slug`
> (string) the slug of the deleted package  

`$type`
> (string) the type of the deleted package  

___
### upserv_did_download_package

```php
do_action( 'upserv_did_download_package', string $package_slug );
```

**Description**  
Fired after the `download` Package API action.

**Parameters**  
`$package_slug`
> (string) the slug of the downloaded package  

___
### upserv_did_signed_url_package

```php
do_action( 'upserv_did_signed_url_package', array $result );
```

**Description**  
Fired after the `signed_url` Package API action.

**Parameters**  
`$result`
> (array) the result of the action  

___
### upserv_package_api_request

```php
do_action( 'upserv_package_api_request', string $method, array $payload );
```

**Description**  
Fired before the Package API request is processed; useful to bypass the execution of currently implemented actions, or implement new actions. 

**Parameters**  
`$action`
> (string) the Package API action  

`$payload`
> (array) the payload of the request  

___
### upserv_remote_sources_options_updated

```php
do_action( 'upserv_remote_sources_options_updated', array $errors );
```

**Description**  
Fired after the "Remote Sources" options form has been processed.

**Parameters**  
`$errors`
> (array) an array of containing errors if any  

___

### upserv_package_options_updated

```php
do_action( 'upserv_package_options_updated', array $errors );
```

**Description**  
Fired after the package options form has been processed.

**Parameters**  
`$errors`
> (array) an array of containing errors if any  

___
### upserv_check_remote_update

```php
do_action( 'upserv_check_remote_update', string $package_slug );
```

**Description**  
Fired before checking if the package on the VCS has updates.  
Fired during client update API request.

**Parameters**  
`$package_slug`
> (string) the slug of the package on the VCS  

___
### upserv_udpdate_manager_request_action

```php
do_action( 'upserv_udpdate_manager_request_action', string $action, array $package_slugs );
```

**Description**  
Fired if the action sent by the admin interface is not `'delete'` or `'download'`.

**Parameters**  
`$action`
> (string) the action sent by the admin interface  

`$package_slugs`
> (array) the slugs of the packages on which to perform the action  

___
### upserv_package_manager_pre_delete_package

```php
do_action( 'upserv_package_manager_pre_delete_package', string $package_slug );
```

**Description**  
Fired before a package is deleted as part of a bulk from the file system.

**Parameters**  
`$package_slug`
> (string) the slug of the package to be deleted  

___
### upserv_package_manager_deleted_package

```php
do_action( 'upserv_package_manager_deleted_package', string $package_slug, bool $result );
```

**Description**  
Fired after a package deletion has been attempted as part of a bulk operation.

**Parameters**  
`$package_slug`
> (string) the slug of the package

`$result`
> (bool) whether the package was successfully deleted

___
### upserv_package_manager_pre_delete_packages_bulk

```php
do_action( 'upserv_package_manager_pre_delete_packages_bulk', array $package_slugs );
```

**Description**  
Fired before packages are deleted in bulk from the file system.

**Parameters**  
`$package_slugs`
> (array) the slugs of the packages to be deleted  

___
### upserv_package_manager_deleted_packages_bulk

```php
do_action( 'upserv_package_manager_deleted_packages_bulk', array $deleted_package_slugs );
```

**Description**  
Fired after packages were deleted in bulk from the file system.

**Parameters**  
`$deleted_package_slugs`
> (array) the slugs of the deleted packages  

___
### upserv_before_packages_download_repack

```php
do_action( 'upserv_before_packages_download_repack', string $archive_name, string $archive_path, array $package_slugs );
```

**Description**  
Fired before an archive containing multiple packages for download is created.

**Parameters**  
`$archive_name`
> (string) the name of the archive to create  

`$archive_path`
> (string) the absolute path of the archive to create  

`$package_slugs`
> (array) the slugs of the packages to include in the archive  

___
### upserv_triggered_packages_download

```php
do_action( 'upserv_triggered_packages_download', string $archive_name, string $archive_path );
```

**Description**  
Fired after download for an archive containing one or multiple packages has been triggered and before the content is streamed.

**Parameters**  
`$archive_name`
> (string) the name of the archive  

`$archive_path`
> (string) the absolute path of the archive  

___
### upserv_after_packages_download

```php
do_action( 'upserv_after_packages_download', string $archive_name, string $archive_path );
```

**Description**  
Fired after package download handling completes, including when the archive could not be streamed.

**Parameters**  
`$archive_name`
> (string) the name of the archive  

`$archive_path`
> (string) the absolute path of the archive  

___
### upserv_get_package_info

```php
do_action( 'upserv_get_package_info', array $package_info, string $package_slug );
```

**Description**  
Fired before getting information from a package.  

**Parameters**  
`$package_info`
> (array) the information of the package in WP cache  

`$package_slug`
> (string) the slug of the package  

___
### upserv_find_package_no_cache

```php
do_action( 'upserv_find_package_no_cache', string $package_slug, string $package_path, Cache $cache );
```

**Description**  
Fired if a package exist and was found, but the cache containing the package information does not.  
Fired during client update API request.

**Parameters**  
`$package_slug`
> (string) the slug of the package  

`$package_path`
> (string) the absolute path of the package on the **local** file system  

`$cache`
> (Cache) the cache object

___
### upserv_update_server_action_download

```php
do_action( 'upserv_update_server_action_download', Request $request );
```

**Description**  
Fired before starting a package download from the update API.  
Fired during client update API request.  

**Parameters**  
`$request`
> (Request) the request object

___
### upserv_webhook_before_handling_request

```php
do_action( 'upserv_webhook_before_handling_request', array $config );
```

**Description**
Fired before handling a webhook request; fired whether it will be processed or not.

**Parameters**
`$config`
> (array) the configuration used to handle webhook requests
___
### upserv_webhook_before_processing_request

```php
do_action( 'upserv_webhook_before_processing_request', array $payload, string $package_slug, string $type, bool $package_exists, array $config );
```

**Description**  
Fired before processing a webhook request.  

**Parameters**  
`$payload`
> (array) the data sent by the Version Control System  

`$package_slug`
> (string) the slug of the package triggering the webhook  

`$type`
> (string) the type of the package triggering the webhook  

`$package_exists`
> (bool) whether the package exists on the file system  

`$config`
> (array) the configuration used to handle webhook requests

___
### upserv_webhook_after_processing_request

```php
do_action( 'upserv_webhook_after_processing_request', array $payload, string $package_slug, string $type, bool $package_exists, array $config );
```

**Description**  
Fired after a webhook request has been processed.  

**Parameters**  
`$payload`
> (array) the data sent by the Version Control System  

`$package_slug`
> (string) the slug of the package triggering the webhook  

`$type`
> (string) the type of the package triggering the webhook  

`$package_exists`
> (bool) whether the package exists on the file system  

`$config`
> (array) the configuration used to handle webhook requests

___
### upserv_webhook_after_handling_request

```php
do_action( 'upserv_webhook_after_handling_request', array $config, array $response );
```

**Description**
Fired after handling a webhook request; fired whether it was processed or not.

**Parameters**
`$config`
> (array) the configuration used to handle webhook requests

`$response`
> (array) the response data that will be sent to the Version Control System

___
### upserv_webhook_invalid_request

```php
do_action( 'upserv_webhook_invalid_request', array $config );
```

**Description**
Fired when a webhook request is invalid.

**Parameters**
`$config`
> (array) the configuration used to handle webhook requests

___
### upserv_packages_table_cell

```php
do_action( 'upserv_packages_table_cell', string $column_name, array $record, string $record_key );
```

**Description**  
Fired when outputing a table cell in the admin interface where `$column_name` is not one of the following: `col_name`, `col_version`, `col_type`, `col_file_name`, `col_file_size`, `col_file_last_modified`.

**Parameters**  
`$column_name`
> (string) the name of the column of the cell  

`$record`
> (array) the record corresponding to the cell  

`$record_key`
> (string) the record key  

___
### upserv_whitelist_package

```php
do_action( 'upserv_whitelist_package', string $package_slug, array $data, bool $result );
```

**Description**
Fired after a package has been whitelisted.

**Parameters**
`$package_slug`
> (string) the slug of the package

`$data`
> (array) the data used to whitelist the package

`$result`
> (bool) `true` if the package was successfully whitelisted, `false` otherwise

___
### upserv_unwhitelist_package

```php
do_action( 'upserv_unwhitelist_package', string $package_slug, bool $result );
```

**Description**
Fired after a package has been unwhitelisted.

**Parameters**
`$package_slug`
> (string) the slug of the package

`$result`
> (bool) `true` if the package was successfully unwhitelisted, `false` otherwise

___
### upserv_set_package_metadata

```php
do_action( 'upserv_set_package_metadata', string $package_slug, array $metadata, bool $result );
```

**Description**
Fired after metadata of a package has been set.

**Parameters**
`$package_slug`
> (string) the slug of the package

`$metadata`
> (array) the metadata set

`$result`
> (bool) `true` if the metadata was successfully set, `false` otherwise

___
### upserv_delete_package_metadata

```php
do_action( 'upserv_delete_package_metadata', string $package_slug, bool $result );
```

**Description**
Fired after metadata of a package has been deleted.

**Parameters**
`$package_slug`
> (string) the slug of the package

`$result`
> (bool) `true` if the metadata was successfully deleted, `false` otherwise

___
## Filters

UpdatePulse Server gives developers the possibility to customize its behavior with a series of custom filters.  
**Warning**: the filters below with the mention "Fired during client update API request" need to be used with caution. Although they may be triggered when using the functions above, these filters will possibly be called when client packages request for updates. Registering functions doing heavy computation to these filters when client update API requests are handled can seriously degrade the server's performances.  

___
### upserv_submitted_package_config

```php
apply_filters( 'upserv_submitted_package_config', array $config );
```

**Description**  
Filter the submitted package configuration values before saving them.  

**Parameters**  
`$config`
> (array) the submitted package configuration values

___
### upserv_submitted_remote_sources_config

```php
apply_filters( 'upserv_submitted_remote_sources_config', array $config );
```

**Description**  
Filter the submitted remote sources configuration values before saving them.  

**Parameters**  
`$config`
> (array) the submitted remote sources configuration values

___
### upserv_schedule_cleanup_frequency

```php
apply_filters( 'upserv_schedule_cleanup_frequency', string $frequency, string $type );
```

**Description**  
Filter the cleanup frequency. 

**Parameters**  
`$frequency`
> (string) the frequency - default 'hourly'  

`$type`
> (string) plugin data type to be clened up (`cache`, `logs`,or `tmp`)  

___
### upserv_check_remote_frequency

```php
apply_filters( 'upserv_check_remote_frequency', string $frequency, string $package_slug );
```

**Description**  
Filter the package update remote check frequency set in the configuration.  
Fired during client update API request.  

**Parameters**  
`$frequency`
> (string) the frequency set in the configuration  

`$package_slug`
> (string) the slug of the package to check for updates  

___
### upserv_handle_update_request_params

```php
apply_filters( 'upserv_handle_update_request_params' , array $params );
```

**Description**  
Filter the parameters used to handle the request made by a client plugin, theme, or generic package to the plugin's API.  
Fired during client update API request.  

**Parameters**  
`$params`
> (array) the parameters of the request to the API  

___
### upserv_update_checker

```php
apply_filters( 'upserv_update_checker', mixed $update_checker, string $package_slug, string $type, string $package_file_name, string $vcs_url, string $vcs_branch, mixed $vcs_credentials, bool $vcs_self_hosted );
```

**Description**  
Filter the checker object used to perform remote checks and downloads.  
Fired during client update API request.  

**Parameters**  
`$update_checker`
> (mixed) the checker object  

`$package_slug`
> (string) the slug of the package using the checker object  

`$type`
> (string) the type of the package using the checker object - `"Plugin"`, `"Theme" `, or `"Generic"`

`$package_file_name`
> (string) the name of the main plugin file or "styles.css" for the package using the checker object  

`$vcs_url`
> (string) URL of the VCS where the remote packages are located  

`$vcs_branch`
> (string) the branch of the VCS repository where the packages are located  

`$vcs_credentials`
> (mixed) the credentials to access the VCS where the packages are located  

`$vcs_self_hosted`
> (bool) `true` if the VCS is self-hosted, `false` otherwise

___
### upserv_cloud_storage_virtual_dir

```php
apply_filters( 'upserv_cloud_storage_virtual_dir', string $virtual_dir );
```

**Description**  
Filter the name of the virtual directory where the packages are stored in the Cloud Storage Service.  
Fired during client update API request.

**Parameters**  
`$virtual_dir`
> (string) the name of the virtual directory where the packages are stored in the Cloud Storage Service - default `updatepulse-packages`  

___
### upserv_cloud_storage_config

```php
apply_filters( 'upserv_cloud_storage_config', array $config );
```

**Description**  
Filter the configuration to use the Cloud Storage Service.  
Fired during client update API request.

**Parameters**  
`$config`
> (array) the configuration to use the Cloud Storage Service  

___
### upserv_package_api_config

```php
apply_filters( 'upserv_package_api_config', array $config );
```

**Description**  
Filter the configuration of the Package API.

**Parameters**  
`$config`
> (array) the configuration of the Package API  

___
### upserv_package_browse

```php
apply_filters( 'upserv_package_browse', array $result, array $query );
```

**Description**  
Filter the result of the `browse` operation of the Package API.

**Parameters**  
`$result`
> (array) the result of the `browse` operation  

`$query`
> (array) the query - see [browse](#browse)  

___
### upserv_package_read

```php
apply_filters( 'upserv_package_read', array $result, string $package_slug, string $type );
```

**Description**  
Filter the result of the `read` operation of the Package API.

**Parameters**  
`$result`
> (array) the result of the `read` operation  

`$package_slug`
> (string) the slthe slug of the read package  

`$type`
> (string) the type of the read package  

___
### upserv_package_edit

```php
apply_filters( 'upserv_package_edit', array $result, string $package_slug, string $type );
```

**Description**  
Filter the result of the `edit` operation of the Package API.

**Parameters**  
`$result`
> (array) the result of the `edit` operation  

`$package_slug`
> (string) the slthe slug of the edited package  

`$type`
> (string) the type of the edited package  

___
### upserv_package_add

```php
apply_filters( 'upserv_package_add', array $result, $package_slug, string $type );
```

**Description**  
Filter the result of the `add` operation of the Package API.

**Parameters**  
`$result`
> (array) the result of the `add` operation  

`$package_slug`
> ($package_slug)the slug of the added package   

`$type`
> (string) the type of the added package  

___
### upserv_package_delete

```php
apply_filters( 'upserv_package_delete', bool $result, string $package_slug, string $type );
```

**Description**  
Filter the result of the `delete` operation of the Package API.

**Parameters**  
`$result`
> (bool) the result of the `delete` operation  

`$package_slug`
> (string) the slug of the deleted package  

`$type`
> (string) the type of the deleted package  

___
### upserv_package_signed_url

```php
apply_filters( 'upserv_package_signed_url', array $result, string $package_slug, string $type );
```

**Description**  
Filter the result of the `signed_url` operation of the Package API.

**Parameters**  
`$result`
> (array) the result of the `signed_url` operation  

`$package_slug`
> (string) the slug of the package for which the URL was signed  

`$type`
> (string) the type of the package for which the URL was signed  

___
### upserv_package_signed_url_token

```php
apply_filters( 'upserv_package_signed_url_token', $token, string $package_slug, string $type );
```

**Description**  
Filter the token used to sign the URL.  

**Parameters**  
`$token`
> ($token) the token used to sign the URL

`$package_slug`
> (string) the slug of the package for which the URL needs to be signed  

`$type`
> (string) the type of the package for which the URL needs to be signed  

___
### upserv_package_public_api_actions

```php
apply_filters( 'upserv_package_public_api_actions', array $public_api_actions );
```

**Description**  
Filter the public API actions; public actions can be accessed via the `GET` method and a token, all other actions are considered private and can only be accessed via the `POST` method.

**Parameters**  
`$public_api_actions`
> (array) the public API actions  

___
### upserv_package_api_request_authorized

```php
apply_filters( 'upserv_package_api_request_authorized', bool $authorized, string $method, array $payload );
```

**Description**  
Filter whether the Package API request is authorized

**Parameters**  
`$authorized`
> (bool) whether the Package API request is authorized  

`$method`
> (string) the method of the request - `GET` or `POST`  

`$payload`
> (array) the payload of the request  

___
### upserv_packages_table_columns

```php
apply_filters( 'upserv_packages_table_columns', array $columns );
```

**Description**  
Filter the columns to display in the packages Overview table.  

**Parameters**  
`$columns`
> (array) the columns to display in the packages Overview table  

___
### upserv_packages_table_sortable_columns

```php
apply_filters( 'upserv_packages_table_sortable_columns', array $columns );
```

**Description**  
Filter the sortable columns in the packages Overview table.  

**Parameters**  
`$columns`
> (array) the sortable columns in the packages Overview table  

___
### upserv_packages_table_bulk_actions

```php
apply_filters( 'upserv_packages_table_bulk_actions', array $actions );
```

**Description**  
Filter the bulk actions in the packages Overview table.

**Parameters**  
`$actions`
> (array) the bulk actions in the packages Overview table  

___
### upserv_server_class_name

```php
apply_filters( 'upserv_server_class_name', string $class_name, string $package_slug, array $config );
```

**Description**  
Filter the class name to use to instantiate a `Anyape\UpdatePulse\Server\Server\Update\Update_Server` object.
Fired during client update API request.

**Parameters**  
`$class_name`
> (string) the class name to use to instantiate a `Anyape\UpdatePulse\Server\Server\Update\Update_Server` object  

`$package_slug`
> (string) the slug of the package to serve  

`$config`
> (array) the configuration to use to serve the package

___
### upserv_server_constructor_args

```php
apply_filters( 'upserv_server_constructor_args', array $args, string $package_slug, array $config );
```

**Description**
Filter the arguments to pass to the constructor of the `Anyape\UpdatePulse\Server\Server\Update\Update_Server` object.
Fired during client update API request.

**Parameters**
`$args`
> (array) the arguments to pass to the constructor of the `Anyape\UpdatePulse\Server\Server\Update\Update_Server` object

`$package_slug`
> (string) the slug of the package to serve

`$config`
> (array) the configuration to use to serve the package

___
### upserv_delete_packages_bulk_paths

```php
apply_filters( 'upserv_delete_packages_bulk_paths', array $package_paths, array $package_slugs );
```

**Description**  
Filter the paths of the package archives to delete.

**Parameters**  
`$package_paths`
> (array) the paths of the package archives to delete from the file system

`$package_slugs`
> (array) the slugs of the packages to delete from the file system

___
### upserv_package_manager_get_package_info

```php
apply_filters( 'upserv_package_manager_get_package_info', array|false $package_info, string $package_slug );
```

**Description**  
Filter the package information before it gets retrieved by the admin interface or through [`upserv_get_package_info`](#upserv_get_package_info).
Effectively bypasses the default file storage retrieval (WordPress File System) to use an alternate package file storage method.
By default, this filter is used when the Object Storage method is enabled.

**Parameters**  
`$package_info`
> (array|false) the package information, or `false` when it has not been found

`$package_slug`
> (string) the slug of the package  
___
### upserv_package_manager_package_info

```php
apply_filters( 'upserv_package_manager_package_info', array $package_info, string $package_slug );
```

**Description**  
Filter the package information retrieved by the admin interface or through [`upserv_get_package_info`](#upserv_get_package_info).

**Parameters**  
`$package_info`
> (array) the information of the package  

`$package_slug`
> (string) the slug of the package  

___
### upserv_package_info_include

```php
apply_filters( 'upserv_package_info_include', bool $include, array $package_info );
```

**Description**  
Filter whether to include the result when getting package information.

**Parameters**  
`$include`
> (bool) whether to include the result  

`$package_info`
> (array) the information of the package  

___
### upserv_package_manager_get_batch_package_info

```php
apply_filters( 'upserv_package_manager_get_batch_package_info', array $packages_information, string $search );
```

**Description**  
Filter the array of package information before it gets retrieved by the admin interface or through [`upserv_get_batch_package_info`](#upserv_get_batch_package_info).
Effectively bypasses the default file storage retrieval (WordPress File System) to use an alternate package file storage method.
By default, this filter is used when the Object Storage method is enabled.

**Parameters**  
`$packages_information`
> (array) the array of package information  

`$search`
> (string) the keyword used to search in package's slug and package's name  

___
### upserv_package_manager_batch_package_info

```php
apply_filters( 'upserv_package_manager_batch_package_info', array $packages_information, string $search );
```

**Description**  
Filter the array of package information retrieved by the admin interface or through [`upserv_get_batch_package_info`](#upserv_get_batch_package_info).

**Parameters**  
`$packages_information`
> (array) the array of package information  

`$search`
> (string) the keyword used to search in package's slug and package's name  

___
### upserv_check_remote_package_update_local_meta

```php
apply_filters( 'upserv_check_remote_package_update_local_meta', array|false $package_info, Package $package, string $package_slug );
```

**Description**  
Filter the package information gathered from the file system before checking for updates in the Version Control System.  
Fired during client update API request.

**Parameters**  
`$package_info`
> (array|false) the package information, or `false` when unavailable

`$package`
> (Package) the package object retrieved from the file system, either from cache or from the package archive

`$package_slug`
> (string) the slug of the package  

___
### upserv_check_remote_package_update_no_local_meta_needs_update

```php
apply_filters( 'upserv_check_remote_package_update_no_local_meta_needs_update', bool $needs_update, Package $package, string $package_slug );
```

**Description**  
Filter whether the package in the file system needs to be updated with the one hosted on the Version Control System when no corresponding package information was found on the file system.  
Fired during client update API request.

**Parameters**  
`$needs_update`
> (bool) whether the package in the file system needs to be updated  

`$package`
> (Package) the package object retrieved from the file system, either from cache or from the package archive

`$package_slug`
> (string) the slug of the package  

___
### upserv_remove_package_result

```php
apply_filters( 'upserv_remove_package_result', bool $removed, string $type, string $package_slug );
```

**Description**  
Filter whether the package was removed from the file system.  

**Parameters**  
`$removed`
> (bool) whether the package was removed from the file system  

`$type`
> (string) the type of the package  

`$package_slug`
> (string) the slug of the package  

___
### upserv_update_server_action_download_handled

```php
apply_filters( 'upserv_update_server_action_download_handled', bool $download_handled, Request $request );
```

**Description**  
Filter whether the package download has been handled. Returning `true` considers the download handled and prevents UpdatePulse Server from streaming the file to the remote client.  
Fired during client update API request.

**Parameters**  
`$download_handled`
> (bool) whether the package download has been handled  

`$request`
> (Request) the request object

___
### upserv_save_remote_to_local

```php
apply_filters( 'upserv_save_remote_to_local', bool $save_to_local, string $package_slug, string $package_path, bool $check_remote );
```

**Description**  
Filter whether UpdatePulse Server needs to attempt to download a package from the Version Control System onto the file system.  
Fired during client update API request.

**Parameters**  
`$save_to_local`
> (bool) whether UpdatePulse Server needs to attempt to download a package from the Version Control System onto the file system  

`$package_slug`
> (string) the slug of the package  

`$package_path`
> (string) the absolute path of the package on the **local** file system  

`$check_remote`
> (bool) `true` if the Version Control System is about to be checked and the package downloaded, `false` if the local cache is about to be used  

___
### upserv_download_remote_package

```php
apply_filters( 'upserv_download_remote_package', bool $download, string $package_slug, string $type, array $info );
```

**Description**
Filter whether to download the package from the Version Control System after it has been confirmed to exist.
Fired during client update API request.

**Parameters**  
`$download`
> (bool) whether to download the package from the Version Control System  

`$package_slug`
> (string) the slug of the package  

`$type`
> (string) the type of the package; one of `"Plugin"`, `"Theme"`, `"Generic"`, or `null`  

`$info`
> (array) the information of the package from the VCS

___
### upserv_vcs_filter_packages

```php
apply_filters( 'upserv_vcs_filter_packages', bool $filter_packages, array $info );
```

**Description**
Filter whether to filter the packages retrieved from the Version Control System.

**Parameters**  
`$filter_packages`
> (bool) whether to filter the packages retrieved from the Version Control System  

`$info`
> (array) the information of the package from the VCS  

### upserv_pre_filter_packages_info

```php
apply_filters( 'upserv_pre_filter_packages_info', array $info, string $file_content );
```

**Description**
Filter the information of the package before it is retrieved from the Version Control System to extend it with additional information.

**Parameters**  
`$info`
> (array) the information of the package from the VCS  

`$file_content`
> (string) the content of the file used to filter the packages retrieved from the Version Control System

___

### upserv_filter_packages_info

```php
apply_filters( 'upserv_filter_packages_info', array $info, string $file_content );
```

**Description**
Filter the information of the package retrieved from the Version Control System to extend it with additional information.

**Parameters**  
`$info`
> (array) the information of the package from the VCS  

`$file_content`
> (string) the content of the file used to filter the packages retrieved from the Version Control System

___
### upserv_filter_packages_filename

```php
apply_filters( 'upserv_filter_packages_filename', string $file_name );
```

**Description**
Filter the name of the file used to filter the packages retrieved from the Version Control System.

**Parameters**  
`$file_name`
> (string) the name of the file used to filter the packages retrieved from the Version Control System

___
### upserv_webhook_vcs_url

```php
apply_filters( 'upserv_webhook_vcs_url', string $url, array $payload );
```

**Description**
Filter the URL of the Version Control System extracted from the webhook payload.

**Parameters**
`$url`
> (string) the URL of the Version Control System

`$payload`
> (array) the payload of the request

___
### upserv_webhook_secret

```php
apply_filters( 'upserv_webhook_secret', string $shared_secret, array $config );
```

**Description**
Filter the shared secret used to verify the signature of the webhook request.

**Parameters**
`$shared_secret`
> (string) the shared secret used to verify the signature of the webhook request

`$config`
> (array) the configuration used to handle webhook requests

___
### upserv_webhook_signature

```php
apply_filters( 'upserv_webhook_signature', string $signature, string $shared_secret, array $config );
```

**Description**
Filter the signature of the webhook request.

**Parameters**
`$signature`
> (string) the signature of the webhook request

`$shared_secret`
> (string) the shared secret used to verify the signature of the webhook request

`$config`
> (array) the configuration used to handle webhook requests

___
### upserv_webhook_validate_request

```php
apply_filters( 'upserv_webhook_validate_request', bool $valid, string $sign, string $shared_secret, array $config );
```

**Description**
Filter whether the webhook request is valid.

**Parameters**
`$valid`
> (bool) whether the webhook request is valid

`$sign`
> (string) the signature of the webhook request

`$shared_secret`
> (string) the shared secret used to verify the signature of the webhook request

`$config`
> (array) the configuration used to handle webhook requests

___
### upserv_webhook_package_exists

```php
apply_filters( 'upserv_webhook_package_exists', bool|null $package_exists, array $payload, string $package_slug, string $type, array $config );
```

**Description**  
Filter whether the package exists on the file system before processing the Webhook.  

**Parameters**  
`$package_exists`
> (bool|null) whether the package exists on the file system; return `null` to leave the decision to the default behavior    

`$payload`
> (array) the payload of the request  

`$package_slug`
> (string) the slug of the package  

`$type`
> (string) the type of the package  


`$config`
> (array) the configuration used to handle webhook requests

___
### upserv_webhook_process_request

```php
apply_filters( 'upserv_webhook_process_request', bool $process_request, array $payload, string $package_slug, string $type, bool $package_exists, array $config );
```

**Description**  
Filter whether to process the Webhook request.  

**Parameters**  
`$process_request`
> (bool) whether to process the Webhook request  

`$payload`
> (array) the payload of the request  

`$package_slug`
> (string) the slug of the package  

`$type`
> (string) the type of the package  

`$package_exists`
> (bool) whether the package exists on the file system  

`$config`
> (array) the configuration used to handle webhook requests

___
### upserv_webhook_response

```php
apply_filters( 'upserv_webhook_response', array $response, int $http_response_code, array $config );
```

**Description**
Filter the response data to send to the Version Control System after handling the webhook request.

**Parameters**
`$response`
> (array) the response data to send to the Version Control System

`$http_response_code`
> (int) the HTTP response code

`$config`
> (array) the configuration used to handle webhook requests

___
### upserv_package_option_update

```php
apply_filters( 'upserv_package_option_update', bool $update, string $option_name, array $option_info, array $options );
```

**Description**  
Filter whether to update the packages plugin option.  

**Parameters**  
`$update`
> (bool) whether to update the package option  

`$option_name`
> (string) the name of the option  

`$option_info`
> (array) the info related to the option  

`$options`
> (array) the values submitted along with the option  

### upserv_remote_sources_option_save_value

```php
apply_filters( 'upserv_remote_sources_option_save_value', mixed $value, string $option_name, array $option_info, array $options );
```

**Description**
Filter the value of the Remote Sources option before saving it.

**Parameters**
`$value`
> (mixed) the value of the Remote Sources option

`$option_name`
> (string) the name of the option

`$option_info`
> (array) the info related to the option

`$options`
> (array) the values submitted along with the option

___
### upserv_remote_source_option_update

```php
apply_filters( 'upserv_remote_source_option_update', bool $update, string $option_name, array $option_info, array $options );
```

**Description**  
Filter whether to update the remote sources plugin option.  

**Parameters**  
`$update`
> (bool) whether to update the remote sources option  

`$option_name`
> (string) the name of the option  

`$option_info`
> (array) the info related to the option  

`$options`
> (array) the values submitted along with the option  

___
### upserv_api_package_actions

```php
apply_filters( 'upserv_api_package_actions', array $actions );
```

**Description**  
Filter the Package API actions available for API access control.  

**Parameters**  
`$actions`
> (array) the API actions  
___
### upserv_is_package_whitelisted

```php
apply_filters( 'upserv_is_package_whitelisted', bool $whitelisted, string $package_slug );
```

**Description**
Filter whether the package is whitelisted.

**Parameters**
`$whitelisted`
> (bool) whether the package is whitelisted

`$package_slug`
> (string) the slug of the package

___
### upserv_whitelist_package_data

```php
apply_filters( 'upserv_whitelist_package_data', array $data, string $package_slug );
```

**Description**
Filter the data used to whitelist the package.

**Parameters**
`$data`
> (array) the data used to whitelist the package

`$package_slug`
> (string) the slug of the package

___
### upserv_set_package_metadata_data

```php
apply_filters( 'upserv_set_package_metadata_data', array|null $data, string $package_slug );
```

**Description**
Filter the data used to set the package metadata.

**Parameters**
`$data`
> (array|null) the metadata to set, or an empty value to delete the metadata record

`$package_slug`
> (string) the slug of the package

___
### upserv_did_set_package_metadata

```php
apply_filters( 'upserv_did_set_package_metadata', bool $handled, string $package_slug, array $data );
```

**Description**
Filter whether the package metadata was set.
The default metadata setting behavior is not executed when this filter is added.

**Parameters**
`$handled`
> (bool) whether the package metadata was set

`$package_slug`
> (string) the slug of the package

`$data`
> (array) the data used to set the metadata

___
### upserv_get_package_metadata

```php
apply_filters( 'upserv_get_package_metadata', array $metadata, string $package_slug, bool $json_encode );
```

**Description**
Filter the package metadata.
The default metadata retrieval is not executed when this filter is added.

**Parameters**
`$metadata`
> (array) the package metadata

`$package_slug`
> (string) the slug of the package

`$json_encode`
> (bool) whether to JSON encode the data

___
### upserv_did_delete_package_metadata

```php
apply_filters( 'upserv_did_delete_package_metadata', bool $handled, string $package_slug );
```

**Description**
Filter whether the package metadata was deleted.
The default metadata deletion behavior is not executed when this filter is added.

**Parameters**
`$handled`
> (bool) whether the package metadata was deleted

`$package_slug`
> (string) the slug of the package

___
