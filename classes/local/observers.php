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

use core\event\group_deleted;
use core\event\course_deleted;

/**
 * Observer functions for local_groupmerge.
 *
 * @package   local_groupmerge
 * @copyright 2025 ISB Bayern
 * @author    Philipp Memmel
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class observers {
    /**
     * Handles the event that a course group has been deleted.
     *
     * This removes all mappings referencing the deleted group.
     *
     * @param group_deleted $event the group_deleted event
     */
    public static function group_deleted(group_deleted $event): void {
        $data = $event->get_data();
        $groupid = $data['objectid'];
        utils::update_mappings_on_group_deletion($groupid);
    }

    /**
     * Handles the event that a course has been deleted.
     *
     * This removes all mappings for the deleted course.
     *
     * @param course_deleted $event the course_deleted event
     */
    public static function course_deleted(course_deleted $event): void {
        global $DB;
        $data = $event->get_data();
        $courseid = $data['objectid'];
        // Get all mappings for this course and delete them.
        $mappings = $DB->get_records('local_groupmerge_mapping', ['courseid' => $courseid]);
        foreach ($mappings as $mapping) {
            utils::delete_mapping((int) $mapping->id);
        }
    }

    /**
     * Handles the event that a member has been added to a group.
     *
     * @param \core\event\group_member_added $event the group_member_added event
     */
    public static function group_member_added(\core\event\group_member_added $event): void {
        $groupid = $event->objectid;
        utils::handle_group_member_added($groupid, $event->relateduserid);
    }

    /**
     * Handles the event that a member has been removed from a group.
     *
     * @param \core\event\group_member_removed $event the group_member_removed event
     */
    public static function group_member_removed(\core\event\group_member_removed $event): void {
        $groupid = $event->objectid;
        utils::handle_group_member_removed($groupid, $event->relateduserid);
    }
}
