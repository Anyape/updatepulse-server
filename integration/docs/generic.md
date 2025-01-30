# UpdatePulse Server - Generic Updates Integration - Developer documentation
(Looking for the main documentation page instead? [See here](https://github.com/anyape/updatepulse-server/blob/main/README.md))

API calls can be used by generic client packages to interact with the UpdatePulse Server from any language or framework to request updates.  
This document focuses on generic packages - WordPress plugins and themes are supported out of the box, and full integration examples are provided.  

In this document, only 3 types of API calls are described:
- Requesting update information
- Activating/Deactivating a license
- Downloading a package

The rest of the API (License, Package, Nonce) follows the same pattern as described here for all languages, as all the examples rely on `wp_remote_get` or `wp_remote_post` to make the API calls.  
Similarly, the actual update process, persisting license key & signature, security concerns such as validating the integrity of the package once it has been downloaded, and the scheduling of the update checks are not addressed here: they vary depending on the package and are the responsibility of the developer of the package.

Also of note, in the context of a generic package, the "domain" added to or removed from the list of `allowed_domains` needs not be an internet domain name, but can be any string that uniquely identifies the client (referred to as `allowedClientIdentifier` throughout the examples).

* [UpdatePulse Server - Generic Updates Integration - Developer documentation](#updatepulse-server---generic-updates-integration---developer-documentation)
    * [Using the provided examples](#using-the-provided-examples)
        * [Disclaimer](#disclaimer)
        * [Configuration](#configuration)
        * [Usage](#usage)
    * [API](#api)
        * [Requesting update information](#requesting-update-information)
            * [Parameters](#parameters)
            * [Sample url](#sample-url)
            * [Example response](#example-response)
            * [Examples request](#examples-request)
        * [Activating/Deactivating a license](#activatingdeactivating-a-license)
            * [Parameters](#parameters-1)
            * [Sample urls](#sample-urls)
            * [Example response](#example-response-1)
            * [Examples request](#examples-request-1)
        * [Downloading a package](#downloading-a-package)
            * [Parameters](#parameters-2)
            * [Sample url](#sample-url-1)
            * [Example response](#example-response-2)
            * [Examples request](#examples-request-2)

## Using the provided examples

Examples of implementations in Node.js, PHP, Bash, and Python are provided in `wp-content/plugins/updatepulse-server/integration/dummy-generic`.  
Although they can be executed, the examples are meant to be used as a starting point for your own implementation, and are **not meant to be used as-is**.  
All the examples have been tested on Linux and MacOS.

### Disclaimer

Each `updatepulse-api.[js|php|sh|py]` file contains a header with the following disclaimer:

```php
### EXAMPLE INTEGRATION WITH UPDATEPULSE SERVER ###

# DO NOT USE THIS FILE AS IT IS IN PRODUCTION !!!
# It is just a collection of basic functions and snippets, and they do not
# perform the necessary checks to ensure data integrity; they assume that all
# the requests are successful, and do not check paths or permissions.
# They also assume that the package necessitates a license key.

# replace https://server.domain.tld/ with the URL of the server where
# UpdatePulse Server is installed in updatepulse.json
```

### Configuration

Example of package configuration file `updatepulse.json` (all properties required except `RequireLicense` which defaults to `false`):

```json
{
   "server": "https://server.domain.tld/",
    "packageData": {
        "Name": "Dummy Generic Package",
        "Version": "1.4.14",
        "Homepage": "https://domain.tld/",
        "Author": "Developer Name",
        "AuthorURI": "https://domain.tld/",
        "Description": "Empty generic package to demonstrate the UpdatePulse Updater.",
        "RequireLicense": true
    }
}
```

### Usage

In a terminal, use the example by typing (replace `[js|php|sh|py]` with the extension of the file you want to test):

```bash
cd wp-content/plugins/updatepulse-server/integration/dummy-generic
# show the help
./dummy-generic.[js|php|sh|py]
# install the package
./dummy-generic.[js|php|sh|py] install [license_key]
# activate the license
./dummy-generic.[js|php|sh|py] activate
# get the update info
./dummy-generic.[js|php|sh|py] get_update_info
# update the package
./dummy-generic.[js|php|sh|py] update
# deactivate the license
./dummy-generic.[js|php|sh|py] deactivate [license_key]
# uninstall the package
./dummy-generic.[js|php|sh|py] uninstall
```

Typing `./dummy-generic.[js|php|sh|py]` without any argument will display the help:

```bash
Usage: ./dummy-generic.[js|php|sh|py] [command] [arguments]
Commands:
  install [license] - install the package
  uninstall - uninstall the package
  activate - activate the license
  deactivate - deactivate the license
  get_update_info - output information about the remote package update
  update - update the package if available
  status - output the package status
Note: this package assumes it needs a license.
```

## API

### Requesting update information

#### Parameters

| Parameter | Required | Description |
| --- | --- | --- |
| action | yes | The action for the API - `get_metadata` |
| package_id | yes | The package identifier |
| installed_version | no | The installed version of the package |
| license_key | no | The license key of the package, saved by the client |
| license_signature | no | The license signature of the package, saved by the client |
| update_type | yes | The type of the update - `Generic` |

#### Sample url

```
https://server.domain.tld/updatepulse-server-update-api/?action=get_metadata&package_id=dummy-generic&installed_version=1.4.13&license_key=41ec1eba0f17d47f76827a33c7daab2c&license_signature=ZaH%2Ba_p1_EkM3BUIpqn7T53htuVPBem2lDtGIxr28oHjdCycvo_ZkxItYqb7mOHhfCMSwnMofWW7UchztEo0k2TwRgk81rNvZyYv6GfRZIxzDP5SzgREjnSAu6JVxDa5yvdd6uqWHWi_U1wRxff0nItItoAloWsek1SVbWbmQXs%3D&update_type=Generic
```

#### Example response

Raw - usable result depends on the language & framework used to get the response.  
Only the relevant headers are shown here, and the JSON content has been prettified for readability.

___

In case a license key is not required for the package:

```
HTTP/1.1 200 OK

{
    "name": "Dummy Generic Package",
    "version": "1.4.14",
    "homepage": "https:\/\/domain.tld\/",
    "author": "Developer Name",
    "author_homepage": "https:\/\/domain.tld\/",
    "description": "Empty Generic Package",
    "last_updated": "2024-01-01 00:00:00",
    "slug": "dummy-generic",
    "download_url": "https:\/\/server.domain.tld\/updatepulse-server-update-api\/?action=download&token=c0c403841752170640518823d752baba&package_id=dummy-generic",
    "time_elapsed": "0.392"
}

```
___

In case a license key is required, but an invalid or no license key is provided:

```
HTTP/1.1 200 OK

{
    "name": "Dummy Generic Package",
    "version": "1.4.14",
    "homepage": "https:\/\/domain.tld\/",
    "author": "Developer Name",
    "author_homepage": "https:\/\/domain.tld\/",
    "description": "Empty Generic Package",
    "last_updated": "2024-01-01 00:00:00",
    "slug": "dummy-generic",
    "license_error": {},
    "time_elapsed": "0.328"
}
```
___

In case a valid license key is provided:

```
HTTP/1.1 200 OK

{
   "name": "Dummy Generic Package",
    "version": "1.4.14",
    "homepage": "https:\/\/domain.tld\/",
    "author": "Developer Name",
    "author_homepage": "https:\/\/domain.tld\/",
    "description": "Empty Generic Package",
    "last_updated": "2024-01-01 00:00:00",
    "slug": "dummy-generic",
    "download_url": "https:\/\/server.domain.tld\/updatepulse-server-update-api\/?action=download&token=c0c403841752170640518823d752baba&package_id=dummy-generic&license_key=41ec1eba0f17d47f76827a33c7daab2c&license_signature=ZaH%2Ba_p1_EkM3BUIpqn7T53htuVPBem2lDtGIxr28oHjdCycvo_ZkxItYqb7mOHhfCMSwnMofWW7UchztEo0k2TwRgk81rNvZyYv6GfRZIxzDP5SzgREjnSAu6JVxDa5yvdd6uqWHWi_U1wRxff0nItItoAloWsek1SVbWbmQXs%3D",
    "license": {
        "id": "9999",
        "license_key": "41ec1eba0f17d47f76827a33c7daab2c",
        "max_allowed_domains": 999,
        "allowed_domains": [
            "allowedClientIdentifier"
        ],
        "status": "activated",
        "txn_id": "0000012345678",
        "date_created": "2024-01-01",
        "date_renewed": "0000-00-00",
        "date_expiry": "2025-01-01",
        "package_slug": "dummy-generic",
        "package_type": "generic",
        "result": "success",
        "message": "License key details retrieved."
    },
    "time_elapsed": "0.302"
}
```

#### Examples request

**Wordpress**

```php
$signature = raw_url_encode( 'ZaH+a_p1_EkM3BUIpqn7T53htuVPBem2lDtGIxr28oHjdCycvo_ZkxItYqb7mOHhfCMSwnMofWW7UchztEo0k2TwRgk81rNvZyYv6GfRZIxzDP5SzgREjnSAu6JVxDa5yvdd6uqWHWi_U1wRxff0nItItoAloWsek1SVbWbmQXs=' ); 
$args      = array(
    'action'            => 'get_metadata',
    'package_id'        => 'dummy-generic',
    'installed_version' => '1.4.13',
    'license_key'       => '41ec1eba0f17d47f76827a33c7daab2c',
    'license_signature' => $signature,
    'update_type'       => 'Generic',
);
$url      = add_query_arg( $args, 'https://server.domain.tld/updatepulse-server-update-api/' );
$response = wp_remote_get(
    $url,
    array
        'timeout' => 3,
        'headers' => array(
            'Accept' => 'application/json',
        ),
    )
);
```

**PHP Curl**

```php
$signature = raw_url_encode( 'ZaH+a_p1_EkM3BUIpqn7T53htuVPBem2lDtGIxr28oHjdCycvo_ZkxItYqb7mOHhfCMSwnMofWW7UchztEo0k2TwRgk81rNvZyYv6GfRZIxzDP5SzgREjnSAu6JVxDa5yvdd6uqWHWi_U1wRxff0nItItoAloWsek1SVbWbmQXs=' ); 
$args = array(
    'action'            => 'get_metadata',
    'package_id'        => 'dummy-generic',
    'installed_version' => '1.4.13',
    'license_key'       => '41ec1eba0f17d47f76827a33c7daab2c',
    'license_signature' => $signature,
    'update_type'       => 'Generic',
);
$url = 'https://server.domain.tld/updatepulse-server-update-api/?' . http_build_query( $args );
$ch = curl_init( $url );

curl_setopt( $ch, CURLOPT_RETURNTRANSFER, true );
curl_setopt( $ch, CURLOPT_HTTPHEADER, array( 'Accept: application/json' ) );
curl_setopt( $ch, CURLOPT_TIMEOUT, 3 );

$response = curl_exec( $ch );

curl_close( $ch );

```

**Node.js**

```js
const https = require('https');
const querystring = require('querystring');
const signature = encodeURIComponent('ZaH+a_p1_EkM3BUIpqn7T53htuVPBem2lDtGIxr28oHjdCycvo_ZkxItYqb7mOHhfCMSwnMofWW7UchztEo0k2TwRgk81rNvZyYv6GfRZIxzDP5SzgREjnSAu6JVxDa5yvdd6uqWHWi_U1wRxff0nItItoAloWsek1SVbWbmQXs=');
const args = {
    action: 'get_metadata',
    package_id: 'dummy-generic',
    installed_version: '1.4.13',
    license_key: '41ec1eba0f17d47f76827a33c7daab2c',
    license_signature: signature
    update_type: 'Generic',
};
const url = 'https://server.domain.tld/updatepulse-server-update-api/?' + querystring.stringify(args);
const options = {
    headers: {
        'Accept': 'application/json'
    }
};

https.get(url, options, (res) => {
    let data = '';

    // A chunk of data has been received.
    res.on('data', (chunk) => {
        data += chunk;
    });

    // The whole response has been received.
    res.on('end', () => {
        console.log(JSON.parse(data));
    });

}).on('error', (err) => {
    console.error("Error: " + err.message);
});
```

**JavaScript**

```js
const signature = encodeURIComponent('ZaH+a_p1_EkM3BUIpqn7T53htuVPBem2lDtGIxr28oHjdCycvo_ZkxItYqb7mOHhfCMSwnMofWW7UchztEo0k2TwRgk81rNvZyYv6GfRZIxzDP5SzgREjnSAu6JVxDa5yvdd6uqWHWi_U1wRxff0nItItoAloWsek1SVbWbmQXs=');
const args = {
    action: 'get_metadata',
    package_id: 'dummy-generic',
    installed_version: '1.4.13',
    license_key: '41ec1eba0f17d47f76827a33c7daab2c',
    license_signature: signature,
    update_type: 'Generic',
};
const url = new URL('https://server.domain.tld/updatepulse-server-update-api/');

url.search = new URLSearchParams(args).toString();

fetch(url, {
    headers: {
        'Accept': 'application/json'
    }
})
.then(response => response.json())
.then(data => {
    console.log(data);
})
.catch(error => {
    console.error(error);
});

```

**Python**

```python
import requests
import urllib.parse

signature = urllib.parse.quote_plus('ZaH+a_p1_EkM3BUIpqn7T53htuVPBem2lDtGIxr28oHjdCycvo_ZkxItYqb7mOHhfCMSwnMofWW7UchztEo0k2TwRgk81rNvZyYv6GfRZIxzDP5SzgREjnSAu6JVxDa5yvdd6uqWHWi_U1wRxff0nItItoAloWsek1SVbWbmQXs=')
args = {
    'action': 'get_metadata',
    'package_id': 'dummy-generic',
    'installed_version': '1.4.13',
    'license_key': '41ec1eba0f17d47f76827a33c7daab2c',
    'license_signature': signature,
    'update_type': 'Generic',
}
url = 'https://server.domain.tld/updatepulse-server-update-api/'
response = requests.get(url, params=args, headers={'Accept': 'application/json'})

if response.status_code == 200:
    print(response.json())
else:
    print('Error:', response.status_code)
```

**Bash curl**

```bash
#!/bin/bash

function urlencode() {
    local old_lc_collate=$LC_COLLATE

    LC_COLLATE=C

    local length="${#1}"

    for (( i = 0; i < length; i++ )); do
        local c="${1:$i:1}"

        case $c in
            [a-zA-Z0-9.~_-]) printf '%s' "$c" ;;
            *) printf '%%%02X' "'$c" ;;
        esac
    done

    LC_COLLATE=$old_lc_collate
}

signature=$(urlencode "ZaH+a_p1_EkM3BUIpqn7T53htuVPBem2lDtGIxr28oHjdCycvo_ZkxItYqb7mOHhfCMSwnMofWW7UchztEo0k2TwRgk81rNvZyYv6GfRZIxzDP5SzgREjnSAu6JVxDa5yvdd6uqWHWi_U1wRxff0nItItoAloWsek1SVbWbmQXs=")
url="https://server.domain.tld/updatepulse-server-update-api/"
args=(
    "action=get_metadata"
    "package_id=dummy-generic"
    "installed_version=1.4.13"
    "license_key=41ec1eba0f17d47f76827a33c7daab2c"
    "license_signature=$signature"
    "update_type=Generic"
)
full_url="${url}?$(IFS=\& ; echo "${args[*]}")"
response=$(curl -s -H "Accept: application/json" "$full_url")

echo "$response"
```

### Activating/Deactivating a license

#### Parameters

| Parameter | Required | Description |
| --- | --- | --- |
| action | yes | The action for the API - `activate` or `deactivate` |
| license_key | yes | The license key of the package to activate or deactivate |
| allowed_domains | yes | A single domain to add or remove from the allowed domains list, depending on `action` |
| package_slug | yes | The package identifier |


#### Sample urls

Activation:
```
https://server.domain.tld/updatepulse-server-update-api/?action=activate&license_key=41ec1eba0f17d47f76827a33c7daab2c&allowed_domains=allowedClientIdentifier&package_slug=dummy-generic
```
___

Deactivation:
```
https://server.domain.tld/updatepulse-server-update-api/?action=deactivate&license_key=41ec1eba0f17d47f76827a33c7daab2c&allowed_domains=allowedClientIdentifier&package_slug=dummy-generic
```

#### Example response

Raw - usable result depends on the language & framework used to get the response.
Only the relevant headers are shown here, and the JSON content has been prettified for readability.

___

Success - activation:
```
HTTP/1.1 200 OK

{
    "license_key":"41ec1eba0f17d47f76827a33c7daab2c",
    "max_allowed_domains":999,
    "allowed_domains": [
        "allowedClientIdentifier"
    ],
    "status":"activated",
    "txn_id":"",
    "date_created":"2024-01-01",
    "date_renewed":"0000-00-00",
    "date_expiry":"2025-01-01",
    "package_slug":"dummy-generic",
    "package_type":"generic",
    "license_signature":"ZaH+a_p1_EkM3BUIpqn7T53htuVPBem2lDtGIxr28oHjdCycvo_ZkxItYqb7mOHhfCMSwnMofWW7UchztEo0k2TwRgk81rNvZyYv6GfRZIxzDP5SzgREjnSAu6JVxDa5yvdd6uqWHWi_U1wRxff0nItItoAloWsek1SVbWbmQXs="
}
```

This is when the license signature must be saved by the client to be used for future update requests.
The client may remove the signature from their record in all other cases.
___

Success - deactivation:
```
HTTP/1.1 200 OK

{
    "license_key":"41ec1eba0f17d47f76827a33c7daab2c",
    "max_allowed_domains":999,
    "allowed_domains":[],
    "status":"deactivated",
    "txn_id":"",
    "date_created":"2024-01-01",
    "date_renewed":"0000-00-00",
    "date_expiry":"2025-01-01",
    "package_slug":"dummy-generic",
    "package_type":"generic"
}

```
___

The license is invalid:
```
HTTP/1.1 400 Bad Request

{
    "code": "invalid_license_key",
    "message": "The provided license key is invalid.",
    "data": {
        "license_key": "example-license"
    }
}
```
___

The license has an illegal status - illegal statuses for activation/deactivation are `on-hold`, `expired` and `blocked`:
```
HTTP/1.1 403 Forbidden

{
    "code": "illegal_license_status",
    "message": "The license cannot be activated due to its current status.",
    "data": {
        "status": "expired"
    }
}
```
___

The license cannot be deactivated yet:
```
HTTP/1.1 403 Forbidden

{
    "code": "too_early_deactivation",
    "message": "The license cannot be deactivated before the specified date.",
    "data": {
        "next_deactivate": "999999999"
    }
}
```
___

The license is already activated for the domain:
```
HTTP/1.1 409 Conflict

{
    "code": "license_already_activated",
    "message": "The license is already activated for the specified domain(s).",
    "data": {
        "allowed_domains": [
            "example.com"
        ]
    }
}
```
___

The license is already deactivated for the domain:
```
HTTP/1.1 409 Conflict

{
    "code": "license_already_deactivated",
    "message": "The license is already deactivated for the specified domain.",
    "data": {
        "allowed_domains": [
            "example.com"
        ]
    }
}
```
___

The license has no more allowed domains left for activation:
```
HTTP/1.1 422 Unprocessable Entity

{
    "code": "max_domains_reached",
    "message": "The license has reached the maximum allowed activations for domains.",
    "data": {
        "max_allowed_domains": 2
    }
}
```
___

Failure (in case of unexpected error):
```
HTTP/1.1 500 Internal Server Error

{
    "code": "unexpected_error",
    "message": "An unexpected error occurred while processing the request.",
    "data": {
        "errors": [
            ...
        ]
    }
}
```

#### Examples request

**Wordpress**

```php
$args      = array(
    'action'           => 'activate', // or 'deactivate'
    'license_key'      => '41ec1eba0f17d47f76827a33c7daab2c',
    'allowed_domains'  => 'allowedClientIdentifier',
    'package_slug'     => 'dummy-generic',
);
$url      = add_query_arg( $args, 'https://server.domain.tld/updatepulse-server-license-api/' );

wp_remote_get(
    $query,
    array(
        'timeout'   => 20,
        'sslverify' => true,
    )
);
```

**PHP Curl**

```php
$args = array(
    'action'           => 'activate', // or 'deactivate'
    'license_key'      => '41ec1eba0f17d47f76827a33c7daab2c',
    'allowed_domains'  => 'allowedClientIdentifier',
    'package_slug'     => 'dummy-generic',
);
$url = 'https://server.domain.tld/updatepulse-server-license-api/?' . http_build_query($args);

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 20);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);

$response = curl_exec($ch);

curl_close($ch);
```

**Node.js**

```js
const https = require('https');
const querystring = require('querystring');
const args = {
    action: 'activate', // or 'deactivate'
    license_key: '41ec1eba0f17d47f76827a33c7daab2c',
    allowed_domains: 'allowedClientIdentifier',
    package_slug: 'dummy-generic',
};
const url = 'https://server.domain.tld/updatepulse-server-license-api/?' + querystring.stringify(args);

https.get(url, (res) => {
    let data = '';

    // A chunk of data has been received.
    res.on('data', (chunk) => {
        data += chunk;
    });

    // The whole response has been received.
    res.on('end', () => {
        console.log(data);
    });

}).on('error', (err) => {
    console.log("Error: " + err.message);
});
```

**JavaScript**

```js
const args = {
    action: 'activate', // or 'deactivate'
    license_key: '41ec1eba0f17d47f76827a33c7daab2c',
    allowed_domains: 'allowedClientIdentifier',
    package_slug: 'dummy-generic',
};

const url = new URL('https://server.domain.tld/updatepulse-server-license-api/');
Object.keys(args).forEach(key => url.searchParams.append(key, args[key]));

fetch(url)
    .then(response => response.text())
    .then(data => {
        console.log(data);
    })
    .catch((error) => {
        console.error('Error:', error);
    });
```

**Python**

```python
import requests
import urllib.parse

args = {
    'action': 'activate', # or 'deactivate'
    'license_key': '41ec1eba0f17d47f76827a33c7daab2c',
    'allowed_domains': 'allowedClientIdentifier',
    'package_slug': 'dummy-generic',
}

url = 'https://server.domain.tld/updatepulse-server-license-api/?' + urllib.parse.urlencode(args)

try:
    response = requests.get(url)
    response.raise_for_status()
    print(response.text)
except requests.exceptions.HTTPError as err:
    print(f'Error: {err}')
```

**Bash curl**

```bash
#!/bin/bash

url="https://server.domain.tld/updatepulse-server-license-api/"
args=(
    "action=activate"
    "license_key=41ec1eba0f17d47f76827a33c7daab2c"
    "allowed_domains=allowedClientIdentifier"
    "package_slug=dummy-generic"
)
full_url="${url}?$(IFS=\& ; echo "${args[*]}")"
response=$(curl -s "$full_url")

echo $response
```

### Downloading a package

Note: the download URL with its one-time use token is acquired from the response to the `get_metadata` API call (see [Requesting update information](#requesting-update-information)).

#### Parameters

| Parameter | Required | Description |
| --- | --- | --- |
| action | yes | The action for the API - `download` |
| token | yes | A one-time use security token - expires after 12h |
| package_id | yes | The package identifier |
| license_key | no | The license key of the package, saved by the client |
| license_signature | no | The license signature of the package, saved by the client |

#### Sample url

```
https://server.domain.tld/updatepulse-server-update-api/?action=download&token=c0c403841752170640518823d752baba&package_id=dummy-generic&license_key=41ec1eba0f17d47f76827a33c7daab2c&license_signature=ZaH%2Ba_p1_EkM3BUIpqn7T53htuVPBem2lDtGIxr28oHjdCycvo_ZkxItYqb7mOHhfCMSwnMofWW7UchztEo0k2TwRgk81rNvZyYv6GfRZIxzDP5SzgREjnSAu6JVxDa5yvdd6uqWHWi_U1wRxff0nItItoAloWsek1SVbWbmQXs%3D
```

#### Example response

Raw - usable result depends on the language & framework used to get the response.
Only the relevant headers are shown here.

___

In case of success (`PK...` is the start of a zip archive, and is followed by binary data):
```
HTTP/1.1 200 OK

PK...
```
___

In case the token has expired or is invalid:
```
HTTP/1.1 403 Forbidden

<html>
<head> <title>403 Forbidden</title> </head>
<body> <h1>403 Forbidden</h1> <p>The download URL token has expired.</p> </body>
</html>
```
___

In case the license key or the signature is invalid:
```
HTTP/1.1 403 Forbidden

<html>
<head> <title>403 Forbidden</title> </head>
<body> <h1>403 Forbidden</h1> <p>Invalid license key or signature.</p> </body>
</html>
```
___

In case the package is not found:
```
HTTP/1.1 404 Not Found

<html>
<head> <title>404 Not Found</title> </head>
<body> <h1>404 Not Found</h1> <p>Package not found</p> </body>
</html>
```

#### Examples request

**Wordpress**

```php
$download_url = "https://server.domain.tld/updatepulse-server-update-api/?action=download&token=c0c403841752170640518823d752baba&package_id=dummy-generic&license_key=41ec1eba0f17d47f76827a33c7daab2c&license_signature=ZaH%2Ba_p1_EkM3BUIpqn7T53htuVPBem2lDtGIxr28oHjdCycvo_ZkxItYqb7mOHhfCMSwnMofWW7UchztEo0k2TwRgk81rNvZyYv6GfRZIxzDP5SzgREjnSAu6JVxDa5yvdd6uqWHWi_U1wRxff0nItItoAloWsek1SVbWbmQXs%3D";
$response     = wp_remote_get(
    $download_url,
    array(
        'timeout'   => 20,
        'sslverify' => true,
    )
);

if ( 200 === wp_remote_retrieve_response_code( $response ) ) {
    global $wp_filesystem;

    $package = wp_remote_retrieve_body( $response );

    if ( ! $wp_filesystem ) {
        require_once ABSPATH . 'wp-admin/includes/file.php';
        WP_Filesystem();
    }

    $wp_filesystem->put_contents( '/tmp/dummy-generic.zip', $package, FS_CHMOD_FILE );
}
```

**PHP Curl**

```php
$download_url = "https://server.domain.tld/updatepulse-server-update-api/?action=download&token=c0c403841752170640518823d752baba&package_id=dummy-generic&license_key=41ec1eba0f17d47f76827a33c7daab2c&license_signature=ZaH%2Ba_p1_EkM3BUIpqn7T53htuVPBem2lDtGIxr28oHjdCycvo_ZkxItYqb7mOHhfCMSwnMofWW7UchztEo0k2TwRgk81rNvZyYv6GfRZIxzDP5SzgREjnSAu6JVxDa5yvdd6uqWHWi_U1wRxff0nItItoAloWsek1SVbWbmQXs%3D";
$options = [
    'http' => [
        'timeout' => 20,
        'ignore_errors' => true,
    ],
    'ssl' => [
        'verify_peer' => true,
    ],
];
$context = stream_context_create($options);
$response = file_get_contents($download_url, false, $context);

if ($http_response_header[0] == 'HTTP/1.1 200 OK') {
    file_put_contents('/tmp/dummy-generic.zip', $response);
}
```

**Node.js**

```js
const https = require('follow-redirects').https;
const fs = require('fs');
const url = "https://server.domain.tld/updatepulse-server-update-api/?action=download&token=c0c403841752170640518823d752baba&package_id=dummy-generic&license_key=41ec1eba0f17d47f76827a33c7daab2c&license_signature=ZaH%2Ba_p1_EkM3BUIpqn7T53htuVPBem2lDtGIxr28oHjdCycvo_ZkxItYqb7mOHhfCMSwnMofWW7UchztEo0k2TwRgk81rNvZyYv6GfRZIxzDP5SzgREjnSAu6JVxDa5yvdd6uqWHWi_U1wRxff0nItItoAloWsek1SVbWbmQXs%3D";

https.get(url, (res) => {

    if (res.statusCode === 200) {
        const file = fs.createWriteStream("/tmp/dummy-generic.zip");

        res.pipe(file);
    }
}).on('error', (e) => {
    console.error(e);
});
```

**JavaScript**

```js
const url = "https://server.domain.tld/updatepulse-server-update-api/?action=download&token=c0c403841752170640518823d752baba&package_id=dummy-generic&license_key=41ec1eba0f17d47f76827a33c7daab2c&license_signature=ZaH%2Ba_p1_EkM3BUIpqn7T53htuVPBem2lDtGIxr28oHjdCycvo_ZkxItYqb7mOHhfCMSwnMofWW7UchztEo0k2TwRgk81rNvZyYv6GfRZIxzDP5SzgREjnSAu6JVxDa5yvdd6uqWHWi_U1wRxff0nItItoAloWsek1SVbWbmQXs%3D";

fetch(url)
    .then(response => response.blob())
    .then(blob => {
        const url = window.URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.style.display = 'none';
        a.href = url;
        a.download = 'dummy-generic.zip';

        document.body.appendChild(a);
        a.click();
        window.URL.revokeObjectURL(url);
    })
    .catch((error) => {
        console.error('Error:', error);
    });
```

**Python**

```python
import requests

url = "https://server.domain.tld/updatepulse-server-update-api/?action=download&token=c0c403841752170640518823d752baba&package_id=dummy-generic&license_key=41ec1eba0f17d47f76827a33c7daab2c&license_signature=ZaH%2Ba_p1_EkM3BUIpqn7T53htuVPBem2lDtGIxr28oHjdCycvo_ZkxItYqb7mOHhfCMSwnMofWW7UchztEo0k2TwRgk81rNvZyYv6GfRZIxzDP5SzgREjnSAu6JVxDa5yvdd6uqWHWi_U1wRxff0nItItoAloWsek1SVbWbmQXs%3D"
response = requests.get(url, stream=True)

if response.status_code == 200:
    with open('/tmp/dummy-generic.zip', 'wb') as f:
        for chunk in response.iter_content(chunk_size=1024):
            if chunk:
                f.write(chunk)
else:
    print(f'Error: HTTP status code {response.status_code}')
```
**Bash curl**

```bash
#!/bin/bash

url="https://server.domain.tld/updatepulse-server-update-api/?action=download&token=c0c403841752170640518823d752baba&package_id=dummy-generic&license_key=41ec1eba0f17d47f76827a33c7daab2c&license_signature=ZaH%2Ba_p1_EkM3BUIpqn7T53htuVPBem2lDtGIxr28oHjdCycvo_ZkxItYqb7mOHhfCMSwnMofWW7UchztEo0k2TwRgk81rNvZyYv6GfRZIxzDP5SzgREjnSAu6JVxDa5yvdd6uqWHWi_U1wRxff0nItItoAloWsek1SVbWbmQXs%3D"
output_file="/tmp/dummy-generic.zip"

curl -o $output_file $url
```
