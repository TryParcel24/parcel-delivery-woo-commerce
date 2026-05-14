# Parcel Delivery Plugin

An easy-use Parcel Delivery plugin that fits all WordPress (WooCommerce) websites.

## Requirements

- **WordPress**: 6.0 or higher
- **PHP**: 7.4 or higher
- **WooCommerce**: 7.0 or higher (tested up to 9.4)

The WooCommerce plugin needs to be installed and activated in order for Parcel Delivery Plugin to work properly.

## Download

Download the latest release from the [GitHub Releases page](https://github.com/TryParcel24/parcel-delivery-woo-commerce/releases/latest).

## Installation

1. Download the latest `parcel-delivery-X.X.X.zip` from [GitHub Releases](https://github.com/TryParcel24/parcel-delivery-woo-commerce/releases/latest)
2. Go to **Plugins** and click on **Add New** button
3. Click on **Upload Plugin** button and then choose the downloaded zip file
4. Click on **Install Now** and activate it

*(Screenshot-1.png and Screenshot-2.png are included in `assets/images`)*

## Features

### 1. Selling Location Setup

This plugin automatically sets WooCommerce selling location as "sell to specific countries" and also sets selling specific countries as "Bahrain".

**Steps to check:** WooCommerce > Settings > General

*(Screenshot-3.png is included in `assets/images`)*

### 2. Shipping Zone Setup

This plugin automatically sets shipping zone for Bahrain country with Flat rate and Free shipping method if no shipping zone exists for Bahrain country.

*(Screenshot-4.png is included in `assets/images`)*

> **Note:** Both shipping methods need to be available so that this plugin works properly.

### 3. Parcel Delivery Settings Tab

This plugin adds a **Parcel Delivery** tab in WooCommerce settings.

*(Screenshot-5.png is included in `assets/images`)*

There are two sections: **API Keys** and **Shipping Calculator**.

#### (a) API Keys Section

Admin has to enter client key and client secret key of Try Parcel API. If admin does not enter these keys, admin will not be able to set further delivery rates for particular blocks, and blocks are also not available at checkout.

#### (b) Shipping Calculator Section

After saving client key and client secret key, all blocks are listed from API.

- Admin can set method for checkout like "manual" or "API"
- Admin can search for a particular block
- Admin can set minimum order amount and delivery rates and also enable/disable particular blocks to hide on checkout page
- Admin can also set pickup location from here
- Admin can also set vehicle for delivery

### 4. Block Selection at Checkout

After client keys setting and shipping calculator settings, the user is able to select block in checkout billing fields on checkout page. Depending on the selection of blocks, shipping rates will be applied.

#### (a) Manual Method

Delivery charge set up by admin on shipping calculator page will be applied. If delivery charge is 0 then free shipping will be applied.

#### (b) API Method

Delivery charge will be calculated as:

```
Additional delivery charge from shipping calculator page on admin side + rate returned by Try Parcel API for that particular block
```

#### (c) Minimum Order Amount

This is the required amount of order to be checked out for a particular block.

### 5. Delivery Requests

- Admin can request delivery for one order and also request delivery for multiple orders
- Admin can also cancel delivery which was requested

### 6. Order Tracking

After admin requested delivery for a particular order of a customer, the customer will be able to track their order on the order detail page.

## Changelog

### 1.1.0

- Compatibility with WooCommerce High-Performance Order Storage (HPOS / Custom Order Tables)
- Compatibility with WooCommerce Cart & Checkout Blocks (Additional Checkout Fields API for the "Block" select)
- Block Checkout: minimum-order amount per block is now enforced via Store API validation
- New setting: Google Maps API Key (under Parcel Delivery → API Keys) used for the customer order tracking map
- Replaced direct post-meta calls on orders with WC order CRUD (`wc_get_order`, `$order->update_meta_data`)
- HPOS-compatible admin: order list column, meta box and bulk "Request Delivery" button on the new Orders screen
- Added nonce + capability checks to admin AJAX endpoints
- Fixed plugin text domain (now `parcel-delivery`) and asset versioning (uses plugin version instead of random)
- Bumped minimum WP to 6.0 and PHP to 7.4

### 1.0.0

- Initial release

## License

This program is free software; you can redistribute it and/or modify it under the terms of the GNU General Public License as published by the Free Software Foundation; either version 2 of the License, or (at your option) any later version.

This program is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU General Public License for more details.

You should have received a copy of the GNU General Public License along with this program; if not, write to the Free Software Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA 02110-1301, USA.
