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
use stdClass;

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
     * This removes the course group from our managed groups.
     *
     * @param group_deleted $event the group_deleted event
     */
    public static function group_deleted(group_deleted $event): void {
        $data = $event->get_data();
        $groupid = $data['objectid'];
        $courseid = $data['courseid'];
        utils::delete_mappings_for_groupid($groupid);
    }

    public static function group_member_added(\core\event\group_member_added $event): void {
        $groupid = $event->objectid;
        utils::handle_group_member_added($groupid, $event->relateduserid);
        // TODO Handle this
    }

    public static function group_member_removed(\core\event\group_member_removed $event): void {
        $data = $event->get_data();
        $groupid = $data['objectid'];

        // TODO Handle this
    }
}
