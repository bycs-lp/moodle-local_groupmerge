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
     * Get all group mappings for a course, grouped by target group, with resolved group names.
     *
     * Returns an array of mapping objects, each containing a 'targetgroup' object (with id and name)
     * and a 'sourcegroups' array of objects (each with id and name). Results are sorted by target
     * group name, source groups within each mapping are sorted by name.
     *
     * @param int $courseid The course id
     * @return array Array of mapping objects with targetgroup and sourcegroups
     */
    public static function get_group_mappings_with_group_name(int $courseid): array {
        global $DB;

        $sql = "SELECT gm.id AS mappingid, gm.targetgroupid, tg.name AS targetgroupname,
                       gm.sourcegroupid, sg.name AS sourcegroupname
                  FROM {local_groupmerge_groupmapping} gm
                  JOIN {groups} tg ON tg.id = gm.targetgroupid
                  JOIN {groups} sg ON sg.id = gm.sourcegroupid
                 WHERE tg.courseid = :courseid
              ORDER BY tg.name, sg.name";

        $records = $DB->get_records_sql($sql, ['courseid' => $courseid]);

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

        return array_values($grouped);
    }

    /**
     * Get all mapping records (sourcegroupid, targetgroupid) for a given course.
     *
     * Returns raw mapping records joined via the groups table to filter by course.
     *
     * @param int $courseid The course id
     * @return array Array of records with sourcegroupid and targetgroupid
     */
    public static function get_mapping_records_for_course(int $courseid): array {
        global $DB;

        $sql = "SELECT gm.id, gm.sourcegroupid, gm.targetgroupid
                  FROM {local_groupmerge_groupmapping} gm
                  JOIN {groups} g ON g.id = gm.targetgroupid
                 WHERE g.courseid = :courseid";
        return $DB->get_records_sql($sql, ['courseid' => $courseid]);
    }

    public static function get_mappings_for_course(int $courseid): array {
        global $DB;
        $sql =
                "SELECT gm.id, gm.sourcegroupid, gm.targetgroupid FROM {local_groupmerge_groupmapping} gm JOIN {groups} g ON gm.targetgroupid = g.id WHERE g.courseid = :courseid";
        $params = ['courseid' => $courseid];
        return $DB->get_records_sql($sql, $params);
    }

    /**
     * Checks whether the given set of mappings contains a circular dependency.
     *
     * Each mapping is an associative array with integer keys 'sourcegroupid' and 'targetgroupid'.
     * A cycle means that some group would transitively become its own source group.
     *
     * @param array $mappings Mappings to check as [['sourcegroupid' => int, 'targetgroupid' => int], ...]
     * @return bool true if a circular dependency exists, false otherwise
     */
    public static function has_circular_mapping(array $mappings): bool {
        // Build directed graph: source → [targets].
        $graph = [];
        foreach ($mappings as $mapping) {
            $src = (int) $mapping['sourcegroupid'];
            $tgt = (int) $mapping['targetgroupid'];
            $graph[$src][] = $tgt;
        }

        // Detect cycles via DFS with an explicit recursion stack.
        $visited = [];
        $instack = [];
        $hascycle = false;

        $dfs = function(int $node) use (&$dfs, &$graph, &$visited, &$instack, &$hascycle): void {
            $visited[$node] = true;
            $instack[$node] = true;
            foreach ($graph[$node] ?? [] as $neighbor) {
                if ($hascycle) {
                    return;
                }
                if (!isset($visited[$neighbor])) {
                    $dfs($neighbor);
                } else if (!empty($instack[$neighbor])) {
                    $hascycle = true;
                }
            }
            $instack[$node] = false;
        };

        foreach (array_keys($graph) as $node) {
            if (!isset($visited[$node])) {
                $dfs($node);
            }
            if ($hascycle) {
                break;
            }
        }

        return $hascycle;
    }
}
