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

    $result = false;
    if ($record = local_webhooks_get_record($serviceid)) {
        $record->enable = !boolval($record->enable);
        $result = local_webhooks_update_record($record);
    }

    return $result;
}

/**
 * Get data from the cache by key.
 *
 * @param  string $eventname
 * @return array
 */
function local_webhooks_cache_get($eventname) {
    $cache = cache::make("local_webhooks", "webhooks_services");
    return $cache->get($eventname);
}

/**
 * Update the data in the cache by key.
 *
 * @param  string  $eventname
 * @param  array   $recordlist
 * @return boolean
 */
function local_webhooks_cache_set($eventname, $recordlist = array()) {
    $cache = cache::make("local_webhooks", "webhooks_services");
    return $cache->set($eventname, $recordlist);
}

/**
 * Delete the data in the cache by key.
 *
 * @param  string  $eventname
 * @return boolean
 */
function local_webhooks_cache_delete($eventname) {
    $cache = cache::make("local_webhooks", "webhooks_services");
    return $cache->delete($eventname);
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
 * Get a list of all system events.
 *
 * @return array
 */
function local_webhooks_get_list_events() {
    $eventlist = report_eventlist_list_generator::get_all_events_list(true);
    return $eventlist;
}

/**
 * Create an entry in the database.
 *
 * @param  object $record
 * @return number
 */
function local_webhooks_create_record($record) {
    global $DB;

    if (!empty($record->events)) {
        $record->events = local_webhooks_serialization_data($record->events);
    }

    $result = $DB->insert_record("local_webhooks_service", $record, true, false);
    local_webhooks_events::service_added($result);
    return $result;
}

/**
 * Update the record in the database.
 *
 * @param  object  $data
 * @return boolean
 */
function local_webhooks_update_record($record) {
    global $DB;

    if (empty($record->id)) {
        print_error("missingparam", "error", null, "id");
    }

    $record->events = !empty($record->events) ? local_webhooks_serialization_data($record->events) : null;
    $result = $DB->update_record("local_webhooks_service", $record, false);
    local_webhooks_events::service_updated($record->id);
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
    local_webhooks_events::service_deleted($serviceid);
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
    local_webhooks_events::service_deletedall();
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
    local_webhooks_events::backup_performed();
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

    local_webhooks_events::backup_restored();
}

/**
 * Send the event remotely to the service.
 *
 * @param  array  $event
 * @param  object $callback
 * @return array
 */
function local_webhooks_send_request($event, $callback) {
    global $CFG;

    $event["host"]  = parse_url($CFG->wwwroot)["host"];
    $event["token"] = $callback->token;
    $event["extra"] = $callback->other;

    $curl = new curl();
    $curl->setHeader(array("Content-Type: application/" . $callback->type));
    $curl->post($callback->url, json_encode($event));

    $response = $curl->getResponse();
    local_webhooks_events::response_answer($callback->id, $response);
    return $response;
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