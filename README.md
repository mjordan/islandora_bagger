# Islandora Bagger

Tool to generate [Bags](https://en.wikipedia.org/wiki/BagIt) for objects using Islandora's REST interface. Specific content is added to the Bag's `/data` directory and `bag-info.txt` file using plugins.

This utility is for Islandora CLAW. For creating Bags for Islandora 7.x, use [Islandora Fetch Bags](https://github.com/mjordan/islandora_fetch_bags).

## Requirements

* PHP 7.1.3 or higher
* [composer](https://getcomposer.org/)

## Installation

1. Clone this git repository.
1. `cd islandora_bagger`
1. `php composer.phar install` (or equivalent on your system, e.g., `./composer install`)

## Usage

### The configuration file

Islandora Bagger requires a configuration file in YAML format:

```yaml
####################
# General settings #
####################

drupal_base_url: 'http://localhost:8000'
drupal_media_auth: ['admin', 'islandora']

# How to name the Bag directory (or file if serialized). One of 'nid' or 'uuid'.
bag_name: nid

temp_dir: /tmp/islandora_bagger_temp
output_dir: /tmp

# Whether or not to zip up the Bag. One of false, 'zip', or 'tgz'.
serialize: false

# Static bag-info.txt tags. No plugin needed.
# You can use any combination of additional tag name / value here.
bag-info:
    Contact-Name: Mark Jordan
    Contact-Email: bags@sfu.ca
    Source-Organization: Simon Fraser University
    Foo: Bar

# Whether or not to log Bag creation.
log_bag_creation: true

############################
# Plugin-specific settings #
############################

# Register plugins to populate bag-info.txt and the /date directory.
plugins: ['AddBasicTags', 'AddMedia', 'AddNodeJson', 'AddNodeJsonld', 'AddMediaJson', 'AddMediaJsonld', 'AddFedoraTurtle']

# Used by the 'AddFedoraTurtle' plugin.
fedora_base_url: 'http://localhost:8080/fcrepo/rest/'

# Used by the 'AddMedia' plugin. Use an emply list (e.g., []) to include all media.
# These are the Drupal taxomony term IDs from the "Islandora Media Use" vocabulary.
drupal_media_tags: ['/taxonomy/term/15']
```

The command to generate a Bag takes two required parameters. Assuming the above configuration file is named `sample_config.yml`, and the Islandora node ID you want to generate a Bag from is 112, the command would look like this:

`./bin/console app:islandora_bagger:create_bag --settings=sample_config.yml --node=112`

The resulting Bag would look like this:

```
/tmp/112
├── bag-info.txt
├── bagit.txt
├── data
│   ├── baz.jpg
│   ├── media.json
│   ├── media.jsonld
│   └── turtle.rdf
├── manifest-sha1.txt
└── tagmanifest-sha1.txt
```

## Customizing the Bags

Customizing the generated Bags is done via values in the configuration file, via plugins, or a combination of these two methods.

## To do

* Add more error and exception handling.
* Add more logging.
* Add tests.

## Current maintainer

* [Mark Jordan](https://github.com/mjordan)

## License

[MIT](https://opensource.org/licenses/MIT)
