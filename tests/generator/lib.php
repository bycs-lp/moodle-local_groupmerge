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
 * Data generator for local_groupmerge.
 *
 * @package    local_groupmerge
 * @copyright  2026 ISB Bayern
 * @author     Philipp Memmel
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class local_groupmerge_generator extends component_generator_base {
    /**
     * Create a course with the given number of groups.
     *
     * Groups are named "Group 1", "Group 2", etc. starting from 1 for each call.
     *
     * @param int $groupcount Number of groups to create.
     * @param stdClass|null $course Existing course to use. If null, a new course is created.
     * @return array{course: stdClass, groups: stdClass[]} Course object and array of group objects keyed by name.
     */
    public function create_course_with_groups(int $groupcount, ?stdClass $course = null): array {
        global $CFG;
        require_once($CFG->dirroot . '/group/lib.php');

        if ($course === null) {
            $course = $this->datagenerator->create_course();
        }

        $groups = [];
        for ($i = 1; $i <= $groupcount; $i++) {
            $groupdata = new stdClass();
            $groupdata->name = 'Group ' . $i;
            $groupdata->courseid = $course->id;
            $groupid = groups_create_group($groupdata);
            $groupdata->id = $groupid;
            $groups[$groupdata->name] = $groupdata;
        }
        return ['course' => $course, 'groups' => $groups];
    }
}
