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

use core\event\course_deleted;
use core\event\group_deleted;
use core\event\user_enrolment_created;
use core\event\user_enrolment_deleted;
use core\task\manager;
use local_bycsauth\event\idmgroup_deleted;
use local_bycsauth\event\idmgroup_updated;
use local_bycsauth\event\idmuser_membership_updated;
use local_bycsauth\idmgroup;
use local_groupmerge\task\mark_courses_for_syncing;
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
     * Updates the sync state of course config objects if a user is enroled into or unenroled from a course.
     *
     * @param user_enrolment_created|user_enrolment_deleted $event the enrolment event
     */
    public static function user_enrolment_created_or_deleted(user_enrolment_created|user_enrolment_deleted $event): void {
        $courseid = $event->courseid;

    }

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

    }

    public static function group_member_added(\core\event\group_member_added $event): void {
        $data = $event->get_data();
        $groupid = $data['objectid'];

        // TODO Handle this
    }

    public static function group_member_removed(\core\event\group_member_removed $event): void {
        $data = $event->get_data();
        $groupid = $data['objectid'];

        // TODO Handle this
    }

    /**
     * Observer for course_deleted event.
     *
     * @param course_deleted $event The course deleted event
     */
    public static function course_deleted(course_deleted $event): void {
        $courseid = $event->objectid;

    }
}
