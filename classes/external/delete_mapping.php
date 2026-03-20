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

namespace local_groupmerge\external;

use context_course;
use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_single_structure;
use core_external\external_value;

/**
 * External function to delete all group mappings for a given target group.
 *
 * @package    local_groupmerge
 * @copyright  2026 ISB Bayern
 * @author     Philipp Memmel
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class delete_mapping extends external_api {

    /**
     * Describes the parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'targetgroupid' => new external_value(PARAM_INT, 'The target group id whose mappings should be deleted'),
        ]);
    }

    /**
     * Delete all mappings for the given target group.
     *
     * @param int $targetgroupid The target group id
     * @return array Result with success status
     */
    public static function execute(int $targetgroupid): array {
        global $CFG, $DB;
        require_once($CFG->dirroot . '/group/lib.php');

        [
            'targetgroupid' => $targetgroupid,
        ] = self::validate_parameters(
            self::execute_parameters(),
            [
                'targetgroupid' => $targetgroupid,
            ]
        );

        // Look up the course from the group.
        $group = groups_get_group($targetgroupid, 'id, courseid', MUST_EXIST);

        // Context validation.
        $context = context_course::instance($group->courseid);
        self::validate_context($context);

        // Capability check.
        require_capability('local/groupmerge:manage', $context);

        // Delete all mapping records for this target group.
        $DB->delete_records('local_groupmerge_groupmapping', ['targetgroupid' => $targetgroupid]);

        return ['success' => true];
    }

    /**
     * Describes the return value.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'success' => new external_value(PARAM_BOOL, 'Whether the deletion was successful'),
        ]);
    }
}




