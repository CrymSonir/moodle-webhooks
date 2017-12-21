<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * This file contains the functions used by the plugin.
 *
 * @package   local_webhooks
 * @copyright 2017 "Valentin Popov" <info@valentineus.link>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined("MOODLE_INTERNAL") || die();

require_once(__DIR__ . "/locallib.php");

/**
 * Change the status of the service.
 *
 * @param  number  $serviceid
 * @return boolean
 */
function local_webhooks_change_status($serviceid) {
    global $DB;

    $result     = false;
    $conditions = array("id" => $serviceid);

    if ($DB->record_exists("local_webhooks_service", $conditions)) {
        $enabled = $DB->get_field("local_webhooks_service", "enable", $conditions, IGNORE_MISSING);
        $result  = $DB->set_field("local_webhooks_service", "enable", !boolval($enabled), $conditions);
    }

    return boolval($result);
}

/**
 * Get the record from the database.
 *
 * @param  number $serviceid
 * @return object
 */
function local_webhooks_get_record($serviceid) {
    global $DB;

    $servicerecord = $DB->get_record("local_webhooks_service", array("id" => $serviceid), "*", MUST_EXIST);

    if (!empty($servicerecord->events)) {
        $servicerecord->events = local_webhooks_deserialization_data($servicerecord->events);
    }

    return $servicerecord;
}

/**
 * Get all records from the database.
 *
 * @param  number $limitfrom
 * @param  number $limitnum
 * @return array
 */
function local_webhooks_get_list_records($limitfrom = 0, $limitnum = 0) {
    global $DB;

    $listrecords = $DB->get_records("local_webhooks_service", null, "id", "*", $limitfrom, $limitnum);

    foreach ($listrecords as $servicerecord) {
        if (!empty($servicerecord->events)) {
            $servicerecord->events = local_webhooks_deserialization_data($servicerecord->events);
        }
    }

    return $listrecords;
}

/**
 * Create an entry in the database.
 *
 * @param  object  $record
 * @return boolean
 */
function local_webhooks_create_record($record) {
    global $DB;

    if (!empty($record->events)) {
        $record->events = local_webhooks_serialization_data($record->events);
    }

    $result = $DB->insert_record("local_webhooks_service", $record, true, false);
    return boolval($result);
}

/**
 * Update the record in the database.
 *
 * @param  object  $data
 * @return boolean
 */
function local_webhooks_update_record($record) {
    global $DB;

    if (!empty($record->events)) {
        $record->events = local_webhooks_serialization_data($record->events);
    }

    $result = $DB->update_record("local_webhooks_service", $record, false);
    return boolval($result);
}

/**
 * Delete the record from the database.
 *
 * @param  number  $serviceid
 * @return boolean
 */
function local_webhooks_delete_record($serviceid) {
    global $DB;
    $result = $DB->delete_records("local_webhooks_service", array("id" => $serviceid));
    return boolval($result);
}

/**
 * Delete all records from the database.
 *
 * @return boolean
 */
function local_webhooks_delete_all_records() {
    global $DB;
    $result = $DB->delete_records("local_webhooks_service", null);
    return boolval($result);
}

/**
 * Create a backup.
 *
 * @return string
 */
function local_webhooks_create_backup() {
    $listrecords = local_webhooks_get_list_records();
    $result      = local_webhooks_serialization_data($listrecords);
    return $result;
}

/**
 * Restore from a backup.
 *
 * @param string $data
 */
function local_webhooks_restore_backup($data, $deleterecords = false) {
    $listrecords = local_webhooks_deserialization_data($data);

    if (boolval($deleterecords)) {
        local_webhooks_delete_all_records();
    }

    foreach ($listrecords as $servicerecord) {
        local_webhooks_create_record($servicerecord);
    }
}

/**
 * Data serialization.
 *
 * @param  array|object $data
 * @return string
 */
function local_webhooks_serialization_data($data) {
    $result = serialize($data);
    return $result;
}

/**
 * Data deserialization.
 *
 * @param  string       $data
 * @return array|object
 */
function local_webhooks_deserialization_data($data) {
    $result = unserialize($data);
    return $result;
}