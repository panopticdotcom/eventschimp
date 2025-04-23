<?php
/**
Plugin Name: Event Tracking for Mailchimp®
Plugin URI: https://panopticdotcom.github.io/eventschimp?utm_source=eventschimp&utm_medium=plugin&utm_campaign=v1_0_0
Description: Track and analyze page view events when subscribers click links in your Mailchimp email campaigns. Leverage the Mailchimp API to collect website visitor behavior and trigger targeted automations based on user engagement. Perfect for improving conversion rates and creating personalized follow-up campaigns. Mailchimp® is a registered trademark of The Rocket Science Group.
Version: 0.5.0
Author: Panoptic.com
Author URI: https://panoptic.com/?utm_source=eventschimp&utm_medium=plugin&utm_campaign=v1_0_0
Text Domain: eventschimp
Domain Path: /i18n/languages/
Requires at least: 6.7
Requires PHP: 7.4
License: GPLv2

Event Tracking for Mailchimp®
Copyright (C) 2025 Dossy Shiobara <dossy@panoptic.com>

This program is free software; you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation; either version 2 of the License, or
(at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License along
with this program; if not, write to the Free Software Foundation, Inc.,
51 Franklin Street, Fifth Floor, Boston, MA 02110-1301 USA.

Mailchimp® is a registered trademark of The Rocket Science Group.

@package EventsChimp
 */

defined( 'ABSPATH' ) || exit;

define( 'EVENTSCHIMP_PLUGIN_FILE', __FILE__ );
define( 'EVENTSCHIMP_PLUGIN_DIR', __DIR__ );

require_once 'includes/class-eventschimp.php';

EventsChimp\EventsChimp::get_instance();
