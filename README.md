# Islandora Bagger Integration

## Introduction

Drupal Module that allows user to create Bags with [Islandora Bagger](https://github.com/mjordan/islandora_bagger). Can be run in "remote" or "local" mode, as explained below.

## Requirements

* An [Islandora Bagger](https://github.com/mjordan/islandora_bagger) microservice
* [Islandora 8](https://github.com/Islandora-CLAW/islandora). This module works with both Drupal 8 and Drupal 9.
* [Context](https://www.drupal.org/project/context) if you want to define which Islandora Bagger configuration files to use other than the default file. A requirement of Islandora so will already be installed.

## Installation

1. Clone this Github repo into your Islandora's `drupal/web/modules/contrib` directory.
1. Enable the module either under the "Admin > Extend" menu or by running `drush en -y islandora_bagger_integration`.

## Configuration

The admin settings form for this module requires the following:

1. A choice of whether you are running it in local or remote mode.
1. The absolute path on your Drupal server to the default Islandora Bagger configuration file. This file is used if no Contexts are configured to use an alternative configuration file.
1. If using remote mode
   1. the URL of the Islandora Bagger REST endpoint. If you are running Islandora in the CLAW Vagrant, and Islandora Bagger on the host machine (i.e., same machine that is hosing the Vagrant), use `10.0.2.2` as your endpoint IP address instead of `localhost`.
   1. an option to add to the configuration file the email address of the user who requested the Bag be created. If checked, the user's email address will be added to the configuration file using the key `recipient_email`. In addition, if this option is checked, the message displayed to the user will indicate they will receive an email when their Bag is ready for download.
1. If running in local mode, the absolute path to the directory on your Drupal server where Islandora Bagger is installed.

After you configure the admin setting, place the "Islandora Bagger Block" as you normally would any other block. You should restrict this block to the content types of your Islandora nodes, and to user roles who you want to be able to create Bags.

## Usage

This module's interaction with Islanodra Bagger can be configured in two ways:

1. Using Islandora Bagger as a remote microservice ("remote" mode)
   * In this mode, submitting the "Create Bag" form does not directly generate the Bag; rather, it sends a request to the remote Islandora Bagger's REST interface, which in turn populates its processing queue with the node ID and the configuration file to use when that node's Bag is created.
   * In remote mode, this Drupal module does not notify the user when their Bag is ready; you will need to configure Islandora Bagger to send an email to the user indicating where they can get the Bag.
1. Using Islandora Bagger as command-line utility ("local" mode)
   * In this mode, submitting the "Create Bag" form calls out to Islandora Bagger on the server's shell, which then generates the Bag.

In both cases, end users generate a Bag for the current object by submitting a simple form (with just one button) in a block. If running in remote mode, the user is told that they will get an email indicating where they can download the Bag (the microservice needs to be configured to send this email); if running in local mode, the user is presented with a link where they can download the Bag.

The advantage of local mode is that the user is presented with the download link immediately after the Bag is generated. The disadvantage of the local mode is that creating the Bag is done synchronously, and there is a risk that, for objects that have very large files, the job will time out.

The advantage of the remote mode is that generating a Bag will never time out because clicking on the "Create Bag" button sends a simple REST request to the remote Islandora Bagger microservice, which then add the request to a queue to be processed later. This is also a disadvantage, since the user doesn't get to download the Bag until later.

## Using Context

This module comes with two Context Reactions that provide control over the Islandora Bagger configuration.

### Using Context to define which configuration file to use

One of those Reactions allows you to use Islandora Bagger configuration files other than the default. To enable this, do the folowing:

1. Install Context and Context UI modules (requirements for Islandora, so will already be done).
1. Create a Context or edit an existing Context.
1. Define your Conditions.
1. Add the "Islandora Bagger Config File" Reaction.
1. Enter the absolute path on your Drupal server to the configuration file you want to use.

This module provides no mechanism for uploading configuration files via Drupal's web interface, so you will need access to the Drupal server's file system. Also, do not put configuration files in directories that are accessible via the web, since they contain credentials for accessing your Drupal's REST interface.

### Using Context to add or modify Islandora Bagger config settings

The other Reaction allows you to add options to the Islandora Bagger configuration file or override existing options. To enable this, do the folowing:

1. Install Context and Context UI modules (requirements for Islandora, so will already be done).
1. Create a Context or edit an existing Context.
1. Define your Conditions.
1. Add the "Islandora Bagger Config Options" Reaction.
1. Enter the options you want to add or modify. Add one option per line, using YAML syntax. For example:

```
serialize: tgz
bag-info: Contact-Email: admin@example.com | Custom-Tag: Some value.
plugins: AddBasicTags | AddMedia | AddFedoraTurtle
```

`bag-info`,`drupal_basic_auth`,`drupal_media_tags`, `plugins`, and `post_bag_scripts` are pipe-separated lists. For `bag-info`, each member of the list is a tag:value pair (separated by a colon). The other list options takes a pipe-separated list of values.

If the option's key exists in the configuration file, that option will be updated with the new value. If the option's key doesn't exist in the configuration file, it will be added. The only exception is `bag-info`: for this option, its values provided in the Context Reaction will be merged with any existing values from the configuration file. For example, if the Reaction contains a tag "Contact-Email: admin@example.com", and the configuration file contains a "Contact-Email" tag, the existing one will be replaced by the one from the Reaction. If the Reaction contains a tag that does not exist in the configuration file, it will be added as a new tag.

## The Bag log

Islandora Bagger can be configured to register the creation of Bags with this module. Each Bag gets an entry in a database table. To do this, each Islandora Bagger configuration file nees to contain the following setting:

```
register_bags_with_islandora: true
```

This tells Islandora Bagger to send a REST request to this module to register the creation of the Bag. To configure this module to accept these requests, you must enable the REST endpoint:

1. Go to Admin > Configure > Web services > REST.
1. Find the "Islandora Bagger Integration Bag Log" resource and click on "Enable".
1. Under "Granularity", choose "Resource".
1. Under "Methods", choose "POST".
1. Under "Accepted request formats", choose "json".
1. Under "Authentication", choose "basic_auth".
1. Save the configuration.

Then, back at the list of REST resources, for "Islandora Bagger Integration Bag Log" choose "Permissions" and in the "Islandora Bagger Integration" section, enable the "Log Bag creation" permission for the roles that can populate the log. The user identified in the Islandora Bagger configuration file (in the `drupal_basic_auth` option) must be a member of the enabled roles.

The data created by this feature is accessible via Views, but (currently) in a very basic way - you can add fields from this data, but not filters or relationships (see [issue](https://github.com/mjordan/islandora_bagger_integration/issues/22) to add these).

## Modifying the Islandora Bagger configuration from other modules

This module defines a hook that allows other modules to modify the Islandora Bagger configration, in both remote and local modes. See `islandora_bagger_integration.api.php` for details.

## To do

See issue list.

## Current maintainer

* [Mark Jordan](https://github.com/mjordan)

## License

[GPLv2](http://www.gnu.org/licenses/gpl-2.0.txt)
