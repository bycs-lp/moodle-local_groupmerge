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
use local_groupmerge\local\utils;

/**
 * External function to delete a group mapping.
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
            'mappingid' => new external_value(PARAM_INT, 'The mapping id to delete'),
        ]);
    }

    /**
     * Delete a mapping and all its associated target and source group records.
     *
     * @param int $mappingid The mapping id
     * @return array Result with success status
     */
    public static function execute(int $mappingid): array {
        global $DB;

        [
            'mappingid' => $mappingid,
        ] = self::validate_parameters(
            self::execute_parameters(),
            [
                'mappingid' => $mappingid,
            ]
        );

        // Look up the mapping to determine the course.
        $mapping = $DB->get_record('local_groupmerge_mapping', ['id' => $mappingid], '*', MUST_EXIST);

        // Context validation.
        $context = context_course::instance($mapping->courseid);
        self::validate_context($context);

        // Capability check.
        require_capability('local/groupmerge:manage', $context);

        // Delete the complete mapping.
        utils::delete_mapping($mappingid);

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
