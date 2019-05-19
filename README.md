# Islandora Bagger Integration

## Introduction

Module that allows users to create Bags using an [Islandora Bagger](https://github.com/mjordan/islandora_bagger) microservice. Provides a block that contains a form to request the creation of a Bag for the curent object.

Still in early development.

## Requirements

* [Islandora 8](https://github.com/Islandora-CLAW/islandora)

## Installation

1. Clone this Github repo into your Islandora's `drupal/web/modules/contrib` directory.
1. Enable the module either under the "Admin > Extend" menu or by running `drush en -y islandora_bagger_integration`.

## Configuration

The only admin setting provided by this module is the URL of the Islandora Bagger REST endpoint. If you are running Islandora in the CLAW Vagrant, and Islandora Bagger on the host machine (i.e., same machine that is hosing the Vagrant), use `10.0.2.2` as your endpoint IP address instead of `localhost`.

## Usage

1. Place the "BagIt Block" as you normally would any other block. You should restrict this block to the content types of your Islandora nodes, and to user roles who you want to be able to create Bags.
1. The block provides a "Create Bag" button. When clicked on, this button sends a request to the Islandora Bagger microservice that populates the queue for generation of Nags. That's all this module does. All of the action happens in the microservice.

## To do

See issue list.

## Current maintainer

* [Mark Jordan](https://github.com/mjordan)

## License

[GPLv2](http://www.gnu.org/licenses/gpl-2.0.txt)
