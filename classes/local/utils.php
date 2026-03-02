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

namespace local_groupmerge\local;

use local_bycsauth\idmgroup;
use local_groupmerge\task\sync_coursegroups;
use stdClass;

/**
 * Utility class for local_groupmerge.
 *
 * @package   local_groupmerge
 * @copyright 2025 ISB Bayern
 * @author    Philipp Memmel
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class utils {

    /**
     * Handles the form data after being submitted by the {@see \local_groupmerge\form\groupmerge_config_form}.
     *
     * @param stdClass $data The form data that has been submitted
     */
    public static function handle_config_form_data(stdClass $data): void {
        global $DB;
        $courseid = intval($data->courseid);
        $clock = \core\di::get(\core\clock::class);
        // The array $data->targetgroupid will always at least contain one element.

        foreach ($data->targetgroupid as $i => $elementtargetgroupid) {
            $existingtargetgroupidmappings =
                    $DB->get_records('local_groupmerge_groupmapping', ['targetgroupid' => $elementtargetgroupid]);
            foreach ($data->sourcegroupids[$i] as $elementsourcegroupid) {
                if (!in_array($elementsourcegroupid,
                        array_map(fn($record) => $record->sourcegroupid, $existingtargetgroupidmappings))) {
                    $record = new \stdClass();
                    $record->sourcegroupid = $elementsourcegroupid;
                    $record->targetgroupid = $elementtargetgroupid;
                    $currenttime = $clock->time();
                    $record->timecreated = $currenttime;
                    $record->timemodified = $currenttime;
                    $DB->insert_record('local_groupmerge_groupmapping', $record);
                }
            }
        }

    }

    /**
     * Method to get the data to inject into the {@see \local_groupmerge\form\groupmerge_config_form} before loading.
     *
     * @param int $courseid The course id of the course
     * @return array Array of data to inject in to the form
     */
    public static function get_data_for_configform(int $courseid): array {
        $mappings = self::get_mappings_for_course($courseid);
        $data = ['sourcegroupids' => [
                0 => [3, 4],
                1 => [5, 6]
        ]];
        return $data;
        /*$courseconfig = new courseconfig($courseid);
        $data = [];
        if ($courseconfig->record_exists(false)) {
            $data['enabled'] = $courseconfig->is_enabled();
            $data['class'] = $courseconfig->get_idmgrouptype_syncmode(idmgroup::IDM_GROUP_TYPE['class']);
            $data['team'] = $courseconfig->get_idmgrouptype_syncmode(idmgroup::IDM_GROUP_TYPE['team']);
            $data['classunit'] = $courseconfig->get_idmgrouptype_syncmode(idmgroup::IDM_GROUP_TYPE['classunit']);

            $idmgroupstosync = $courseconfig->get_idmgroups_to_sync();
            $data['enable_customidmgroups'] = !empty($idmgroupstosync);
            $data['customidmgroups'] = $courseconfig->get_currently_synced_groupids();

            $data['cleanupgroups'] = $courseconfig->get_cleanup_groups();
        }
        return $data;*/
    }

    public static function get_mappings_for_course(int $courseid): array {
        global $DB;
        $sql =
                "SELECT gm.id, gm.sourcegroupid, gm.targetgroupid FROM {local_groupmerge_groupmapping} gm JOIN {groups} g ON gm.targetgroupid = g.id WHERE g.courseid = :courseid";
        $params = ['courseid' => $courseid];
        return $DB->get_records_sql($sql, $params);
    }
}
