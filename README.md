<p><img src="./src/icon.svg" width="100" height="100" alt="Webshipper for Craft Commerce icon"></p>

# Shipmondo for Craft Commerce

This plugin provides an [Shipmondo](https://shipmondo.com/) integration for [Craft Commerce](https://craftcms.com/commerce).

## Requirements

This plugin requires Craft CMS 4.0.0 and Craft Commerce 4.0.0 or later.

## Installation

You can install this plugin from the Plugin Store or with Composer.

#### From the Plugin Store

Go to the Plugin Store in your project’s Control Panel and search for “Shipmondo”. Then click on the “Install” button in its modal window.

#### With Composer

Open your terminal and run the following commands:

```bash
# go to the project directory
cd /path/to/my-project.test

# tell Composer to load the plugin
composer require quantity-digital/commerce-shipmondo

# tell Craft to install the plugin
./craft install/plugin commerce-shipmondo
```

## Setup

This plugin will, after you have filled in the required settings on the settings page, add a new field on Craft's default shipping methods editor screen, where you can connect a shipping method in Craft to the shipping method in Webshipper.

All syncronizations between Craft and Shipmondo is handled automaticly using Craft Queue.

## Usage

To get droppoints on the front, you can call the endpoint `/shipmondo/services/list-service-point`, after the shipping address has been added, to receive available droppoints based on the selected shipping address.

## Webhook support

This plugin currently supports the following webhooks:

* Update status
