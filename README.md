# Islandora Bagger

Utility to generate [Bags](https://en.wikipedia.org/wiki/BagIt) for objects using Islandora's REST interface using either a command-line tool or via a batch-oriented queue. In addition, Islandora Bagger provides its own REST interface that allows population of the queue. Specific content is added to the Bag's `data` directory and `bag-info.txt` file using plugins. Bags are compliant with version 0.96 of the BagIt specification.

This utility is for Islandora 8.x-1.x. For creating Bags for Islandora 7.x, use [Islandora Fetch Bags](https://github.com/mjordan/islandora_fetch_bags).

## Requirements

* PHP 7.1.3 or higher
* [composer](https://getcomposer.org/)

## Installation

1. Clone this git repository.
1. `cd islandora_bagger`
1. `php composer.phar install` (or equivalent on your system, e.g., `./composer install`)

## Configuration

Even though each Bag is created using options defined in its own configuration file (see next section), Islandora Bagger uses several application-wide configuration options defined in the `parameters` section of `config/services.yaml`.

You probably don't need to change `app.queue.path` and `app.location.log.path` since these specify default locations for some data files. However, if you are providing the ability for users to download serialized Bags, you will need to change the `app.bag.download.prefix` parameter to the hostname/path to append to each Bag's filename as described in the "Making Bags downloadable" section below.

## Command-line usage

The command to generate a Bag takes two required parameters, `--settings` and `--node`. Assuming the configuration file is named `sample_config.yml`, and the Drupal node ID you want to generate a Bag from is 112, the command would look like this:

`./bin/console app:islandora_bagger:create_bag --settings=sample_config.yml --node=112`

### The configuration file

Islandora Bagger requires a configuration file in YAML format:

```yaml
####################
# General settings #
####################

# Required.
drupal_base_url: 'http://localhost:8000'
drupal_basic_auth: ['admin', 'islandora']

# Required. How to name the Bag directory (or file if serialized). One of 'nid' or 'uuid'.
bag_name: nid

# Both temp_dir and output_dir are required.
temp_dir: /tmp/islandora_bagger_temp
output_dir: /tmp

# Required. Whether or not to zip up the Bag. One of 'false', 'zip', or 'tgz'.
serialize: zip

# Required. Whether or not to log Bag creation. Set log output path in config/packages/{environment}/monolog.yaml.
log_bag_creation: true

# Optional. Static bag-info.txt tags. No plugin needed. You can use any combination
# of tag name / value here, as long as ou seprate tags from values using a colon (:).
bag-info:
    Contact-Name: Mark Jordan
    Contact-Email: bags@sfu.ca
    Source-Organization: Simon Fraser University
    Foo: Bar

# Optional. Whether or not to include the Payload-Oxum tag in bag-info.txt. Defaults to true.
# include_payload_oxum: false

# Optional. Which hash algorithm to use. One of 'sha1' or 'md5'. Default is sha1.
# hash_algorithm: md5

# Optional. Timeout to use for Guzzle requests, in seconds. Default is 60.
# http_timeout: 120

# Optional. Whether or not to verify the Certificate Authority in Guzzle requests
# against websites that implement HTTPS. Used on Mac OSX if Islandora Bagger is
# interacting with websites running HTTPS. Default is true. Note that if you set
# verify_ca to false, you are bypassing HTTPS encryption between Islandora Bagger
# and the remote website. Use at your own risk.
# verify_ca: false

# Optional. Whether or not to delete the settings file upon successful creation
# of the Bag. Default is false.
# delete_settings_file: true

# Optional. Whether or not to log the serialized Bag's location so Islandora can
# retrieve the Bag's download URL. Default is false.
log_bag_location: true

############################
# Plugin-specific settings #
############################

# Required. Register plugins to populate bag-info.txt and the data directory.
# Plugins are executed in the order they are listed here.
plugins: ['AddBasicTags', 'AddMedia', 'AddNodeJson', 'AddNodeJsonld', 'AddMediaJson', 'AddMediaJsonld', 'AddFedoraTurtle']

# Used by the 'AddFedoraTurtle' plugin.
fedora_base_url: 'http://localhost:8080/fcrepo/rest/'

# Used by the 'AddMedia' plugin. These are the Drupal taxomony term IDs
# from the "Islandora Media Use" vocabulary. Use an emply list (e.g., [])
# to include all media.
drupal_media_tags: ['/taxonomy/term/15']

# Used by the 'AddMedia' plugin. Indicates whether the Bag should contain a file
# named 'media_use_summary.tsv' that lists all the media files plus the taxonomy
# name corresponding to the 'drupal_media_tags' list. Default is false.
include_media_use_list: true

####################
# Post-Bag scripts #
####################

# post_bag_scripts: ["php /tmp/test.php", "python /path/to/script.py"]
```

The resulting Bag would look like this:

```
/tmp/112
├── bag-info.txt
├── bagit.txt
├── data
│   ├── IMG_1410.JPG
│   ├── media.json
│   ├── media.jsonld
│   ├── node.json
│   ├── node.jsonld
│   └── node.turtle.rdf
├── manifest-sha1.txt
└── tagmanifest-sha1.txt
```

## REST interface usage

Islandora Bagger can also create Bags via a simple REST interface. It does this by 1) receiving a `PUT` request containing the node ID of the Islandora object to be bagged in a "Islandora-Node-ID" header and 2) receiving a YAML configuration file as the body of the request. Using this data, it adds the request to a queue (see below), which is then processed at a later time. The REST interface also provides the ability to `GET` a Bag's download URL.

Note that requests to the REST interface do not generate Bags directly, they only populate a queue as described below.

To use the REST API to add a Bag-creation job to the queue:

1. Prepare a YAML configuration file for posting to the REST API.
1. Run `php bin/console server:start`
1. Run `curl -v -X POST -H "Islandora-Node-ID: 4" --data-binary "@sample_config.yml" http://127.0.0.1:8001/api/createbag`

To use the REST API to get a serialized Bag's location for download:

1. Create a Bag using the command-line or via a REST `PUT` request. The configuration file's `serialize` setting must be either "zip" or "tgz", and the `log_bag_creation` setting must be `true`.
1. Start the web server, as above, if not already started.
1. Run `curl -v -H "Islandora-Node-ID: 4" http://127.0.0.1:8001/api/createbag`. Your response will be a JSON string containing the node ID, the Bag's location, and an ISO8601 timestamp of when the Bag was created, e.g.:

 `{"nid":"4","location":"http:\/\/example.com\/bags\/4.zip","created":"2019-05-06T19:31:33-0700"}`

This API is in its early stages of development and will change before it is ready for production use. For example, the API lacks credential-based authentication. In the meantime, using Symfony's firewall to provide IP-based access to the API should provide sufficient security.

### Making Bags downloadable

As described in the previous section, the location of each Bag is available via a `GET` request to Islandora Bagger's REST interface. If you want to use this information to provide a way to download Bags from Islandora Bagger, follow these steps:

* In the Bag-specific configuration file 
  1. Make sure the `serialize` option is set to `zip` or `tgz` (only serialized Bags can be downloaded).
  1. Make sure the `log_bag_location` option is set to `true`.
  1. Make sure the directory specified in the `output_dir` option is exposed to the web.
* In Islandora Bagger's `config/services.yml` file
  1. make sure the `app.bag.download.prefix` parameter contains the hostname/path leading to the directory specified in the configuration file's `output_dir` option.

`GET` requests to the REST API will now return `location` values that contain URLs that combine the path specified in `app.bag.download.prefix` with the serialized Bag's filename.

This is obviously insecure, since anyone who knows the location of the directory where the Bags are exposed for download will have access to them. Please join the discussion at [this issue](https://github.com/mjordan/islandora_bagger/issues/17) if you have a suggestion on implementing more robust security on Bag downloads.

## The queue

Islandora Bagger implements a simple processing queue, which is populated mainly by REST requests to generate Bags. However, the queue can be populated by any process (manually, scripted, etc.). Islandora Bagger processes the queue by inspecting each entry in first-in, first-out order and for each entry, runs the `app:islandora_bagger:create_bag` command, which creates the Bag by fetching the files and other data from the Islandora instance.

The queue is a simple tab-delimited text file that contains one entry per line. The two fields in each entry are 1) the node ID, 2) the full path to the YAML configuration file, e.g.:

`100       /home/mark/Documents/hacking/islandora_bagger/var/islandora_bagger.100.yaml`

To process the queue, run the following command:

`./bin/console app:islandora_bagger:process_queue --queue var/islandora_bagger.queue`

where the value of the `--queue` option is the path to the queue file. Typically, this command would be executed from within a scheduled job managed by cron. This command iterates through the queue in first-in, first-out order. Once processed, the entry is removed from the queue.

## Customizing the Bags

Customizing the generated Bags is done via values in the configuration file and via plugins.

### Configuration file

Items in the "General Configuration" section provide some simple options for customizing Bags, e.g.:

* whether the Bag is named using the node's ID or its UUID
* whether the Bag is serialized (i.e., zipped)
* what tags are included in the `bag-info.txt` file. Tags specified in general settings' `bag-info` option are static in that they are simple strings. In order to include tags that are dynamically generated, you must use a plugin.

### Plugins

Apart from the static tags mentioned in the previous section, all file content and additional tags are added to the Bag using plugins. Plugins are registerd in the `plugins` section of the configuration file.

#### Included plugins

The following plugins are bundled with Islandora Bagger:

* AddBasicTags: Adds the `Internal-Sender-Identifier` bag-info.txt tag using the Drupal URL for the node as its value, and the `Bagging-Date` tag using the current date as its value.
* AddNodeJson: Adds the Drupal JSON representation of the node, specifically, the response to a request to `/node/1234?_format=json`.
* AddNodeJsonld: Adds the Drupal JSON-LD representation of the node, specifically, the response to a request to `/node/1234?_format=jsonld`.
* AddFedoraTurtle: Adds the Fedora Turtle RDF representation of the node.
* AddMedia: Adds media files, such as the Original File, Preservation Master, etc., to the Bag. The specific files added are identified by the relevant tags from the "Islandora Media Use" vocabulary listed in the `drupal_media`tags` configuratoin option.
* AddMediaJson: Adds the Drupal JSON representation of the node's media list, specifically, the response to a request to `/node/1234/media?_format=json`.
* AddMediaJsonld: Adds the Drupal JSON-LD representation of the node's media list, specifically, the response to a request to `/node/1234/media?_format=jsonld`.
* Sample: A example plugin for developers.

#### Writing custom plugins

Each plugin is a PHP class that extends the base `AbstractIbPlugin` class. The `Sample.php` plugin illustrates what you can (and must) do within a plugin. Plugins are located in the `islandora_bagger/src/Plugin` directory, and must implement an `execute()` method. Within that method, you have access to the Bag object, the Bag temporary directory, the node's ID, the node's JSON representation from Drupal. You also have access to all values in the configuratin file via the `$this->settings` associative array.

To use a custom plugin, simply register its class name in the `plugins` list in your configuation file.

## Post-Bag scripts

The `post_bag_scripts` option in the configuration file allows you to specify a list of scripts to run after the Bag has been successfully created. These scripts can send email messages, copy Bag files to alternate locations, and other tasks. You can include any script, in any language, with the following constraints:

* the scripts must exist on the same system where Islandora Bagger is running, and must be executable by the user running the `app:islandora_bagger:create_bag` command
* you should always include the script's interpreter (php, python, etc.) and the full path to the script
* the scripts are only executed if the Bag was successfully created
* the scripts are executed in the order they appear in the list
* all scripts are passed two arguments, 1) the current node ID and 2) the Bag's output directory (or if serialized, the path to the Bag file)
* the results of the script are logged, including their exit codes.

## To do

* Add more error and exception handling.
* Add more logging.
* Add CONTRIBUTING.md.
* Add tests.

## Current maintainer

* [Mark Jordan](https://github.com/mjordan)

## License

[MIT](https://opensource.org/licenses/MIT)
