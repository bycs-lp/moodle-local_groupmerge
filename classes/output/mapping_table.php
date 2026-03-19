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

namespace local_groupmerge\output;

use core\output\renderable;
use core\output\renderer_base;
use core\output\templatable;
use stdClass;

/**
 * Class mapping_table
 *
 * @package   local_groupmerge
 * @copyright 2026 ISB Bayern
 * @author    Philipp Memmel
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class mapping_table implements renderable, templatable {

    public function __construct(
        private readonly int $courseid
    ) {
    }
    /**
     * Export this data so it can be used as the context for a mustache template.
     *
     * @param renderer_base $output
     * @return stdClass the template context
     */
    public function export_for_template(renderer_base $output) {
        global $DB;

        // Fetch all mappings where the target group belongs to this course.
        $sql = "SELECT gm.id AS mappingid, gm.targetgroupid, tg.name AS targetgroupname,
                       gm.sourcegroupid, sg.name AS sourcegroupname
                  FROM {local_groupmerge_groupmapping} gm
                  JOIN {groups} tg ON tg.id = gm.targetgroupid
                  JOIN {groups} sg ON sg.id = gm.sourcegroupid
                 WHERE tg.courseid = :courseid
              ORDER BY tg.name, sg.name";

        $records = $DB->get_records_sql($sql, ['courseid' => $this->courseid]);

        // Group records by target group.
        $grouped = [];
        foreach ($records as $record) {
            $targetid = $record->targetgroupid;
            if (!isset($grouped[$targetid])) {
                $grouped[$targetid] = (object) [
                    'targetgroup' => (object) [
                        'id' => $targetid,
                        'name' => $record->targetgroupname,
                    ],
                    'sourcegroups' => [],
                ];
            }
            $grouped[$targetid]->sourcegroups[] = (object) [
                'id' => $record->sourcegroupid,
                'name' => $record->sourcegroupname,
            ];
        }

        $data = new stdClass();
        $data->courseid = $this->courseid;
        $data->canaddmapping = count(groups_get_all_groups($this->courseid)) >= 2;
        $data->mappings = array_values($grouped);
        return $data;
    }
}
