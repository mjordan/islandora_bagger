# Islandora Bagger Integration

## Introduction

Drupal 8 Module that allows users to create Bags using an [Islandora Bagger](https://github.com/mjordan/islandora_bagger) microservice. Provides a block that contains a form to request the creation of a Bag for the curent node/object. Submitting this form does not directly generate the Bag; rather, it sends a request to the Islandora Bagger's REST interface that populates the processing queue with the node ID and the configuration file to use when that node's Bag is created.

## Requirements

* An [Islandora Bagger](https://github.com/mjordan/islandora_bagger) microservice
* [Islandora 8](https://github.com/Islandora-CLAW/islandora)
* [Context](https://www.drupal.org/project/context) if you want to define which Islandora Bagger configuration files to use other than the default file.

## Installation

1. Clone this Github repo into your Islandora's `drupal/web/modules/contrib` directory.
1. Enable the module either under the "Admin > Extend" menu or by running `drush en -y islandora_bagger_integration`.

## Configuration

The only admin settings provided by this module are:

1. The URL of the Islandora Bagger REST endpoint. If you are running Islandora in the CLAW Vagrant, and Islandora Bagger on the host machine (i.e., same machine that is hosing the Vagrant), use `10.0.2.2` as your endpoint IP address instead of `localhost`.
1. The absolute path on your Drupal server to the default Islandora Bagger configuration file. This file is used if no Contexts are configured to use an alternative configuration file.

## Using Context to define which configuration file to use

This module comes with a Context reaction that allows you to use Islandora Bagger configuration files other than the default. To enable this, do the folowing:

1. Install Context and Context UI modules.
1. Create a Context or edit an existing Context.
1. Define your Conditions.
1. Add the "Islandora Bagger Config File" reaction.
1. Enter the absolute path on your Drupal server to the configuration file you want to use.

This module provides no mechanism for uploading configuration files via Drupal's web interface, so you will need access to the Drupal server's file system. Also, do not put configuration files in directories that are accessible via the web, since they contain credentials for accessing your Drupal's REST interface.

## Usage

1. Place the "BagIt Block" as you normally would any other block. You should restrict this block to the content types of your Islandora nodes, and to user roles who you want to be able to create Bags.
1. The block provides a "Create Bag" button. When clicked on, this button sends a request to the Islandora Bagger microservice that populates the queue for generation of Nags. That's all this module does. All of the action happens in the microservice.

## To do

See issue list.

## Current maintainer

* [Mark Jordan](https://github.com/mjordan)

## License

[GPLv2](http://www.gnu.org/licenses/gpl-2.0.txt)
