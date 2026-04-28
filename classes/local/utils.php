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

use local_groupmerge\hook\restrict_target_groups;
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
     * Validate that all given groups belong to the specified course.
     *
     * @param int $courseid The course id
     * @param int[] $groupids Array of group ids to validate
     * @throws \coding_exception If any group does not exist or does not belong to the course
     */
    public static function require_groups_belong_to_course(int $courseid, array $groupids): void {
        global $CFG;
        require_once($CFG->dirroot . '/group/lib.php');

        $groupids = array_map('intval', $groupids);
        if (empty($groupids)) {
            return;
        }

        // Fetch all groups of the course once and build a lookup set.
        $coursegroups = groups_get_all_groups($courseid, 0, 0, 'g.id');
        $coursegroupids = array_keys($coursegroups);

        foreach ($groupids as $groupid) {
            if (!in_array($groupid, $coursegroupids)) {
                throw new \coding_exception(
                    'Group (id: ' . $groupid . ') does not belong to course (id: ' . $courseid . ').'
                );
            }
        }
    }

    /**
     * Validate that the given mapping type is a known type constant.
     *
     * @param int $type The mapping type to validate
     * @throws \coding_exception If the type is not one of the allowed values
     */
    public static function validate_type(int $type): void {
        $allowedtypes = [group_syncer::TYPE_SUBSET, group_syncer::TYPE_COVER];
        if (!in_array($type, $allowedtypes, true)) {
            throw new \coding_exception('Invalid mapping type: ' . $type);
        }
    }

    /**
     * Get all group mappings for a course, grouped by mapping, with resolved group names.
     *
     * Returns an array of mapping objects, each containing a 'targetgroup' object (with id and name),
     * a 'sourcegroups' array of objects (each with id and name), and the mapping id and type.
     * Results are sorted by target group name, source groups within each mapping are sorted by name.
     *
     * @param int $courseid The course id
     * @return array Array of mapping objects with targetgroup and sourcegroups
     */
    public static function get_group_mappings_with_group_name(int $courseid): array {
        global $DB;

        $sql = "SELECT m.id AS mappingid, m.name AS mappingname, m.type,
                       tg.targetgroupid, g_target.name AS targetgroupname,
                       sg.sourcegroupid, g_source.name AS sourcegroupname
                  FROM {local_groupmerge_mapping} m
                  JOIN {local_groupmerge_targetgroup} tg ON tg.mappingid = m.id
                  JOIN {groups} g_target ON g_target.id = tg.targetgroupid
                  JOIN {local_groupmerge_sourcegroup} sg ON sg.mappingid = m.id
                  JOIN {groups} g_source ON g_source.id = sg.sourcegroupid
                 WHERE m.courseid = :courseid
              ORDER BY g_target.name, g_source.name";

        $records = $DB->get_recordset_sql($sql, ['courseid' => $courseid]);

        // Group records by mapping id.
        $grouped = [];
        foreach ($records as $record) {
            $mappingid = (int) $record->mappingid;
            if (!isset($grouped[$mappingid])) {
                $grouped[$mappingid] = (object) [
                    'mappingid' => $mappingid,
                    'mappingname' => $record->mappingname,
                    'targetgroup' => (object) [
                        'id' => (int) $record->targetgroupid,
                        'name' => $record->targetgroupname,
                    ],
                    'type' => (int) $record->type,
                    'sourcegroups' => [],
                ];
            }
            // Avoid duplicate source groups (due to JOIN).
            $sourceid = (int) $record->sourcegroupid;
            $alreadyadded = false;
            foreach ($grouped[$mappingid]->sourcegroups as $existing) {
                if ($existing->id === $sourceid) {
                    $alreadyadded = true;
                    break;
                }
            }
            if (!$alreadyadded) {
                $grouped[$mappingid]->sourcegroups[] = (object) [
                    'id' => $sourceid,
                    'name' => $record->sourcegroupname,
                ];
            }
        }
        $records->close();

        return array_values($grouped);
    }

    /**
     * Get all mapping records for a given course with source and target group info.
     *
     * Returns records with mappingid, sourcegroupid, targetgroupid and type.
     *
     * @param int $courseid The course id
     * @return array Array of records with mappingid, sourcegroupid, targetgroupid, type
     */
    public static function get_mapping_records_for_course(int $courseid): array {
        global $DB;

        $sql = "SELECT sg.id, m.id AS mappingid, sg.sourcegroupid, tg.targetgroupid, m.type
                  FROM {local_groupmerge_mapping} m
                  JOIN {local_groupmerge_targetgroup} tg ON tg.mappingid = m.id
                  JOIN {local_groupmerge_sourcegroup} sg ON sg.mappingid = m.id
                 WHERE m.courseid = :courseid";
        return $DB->get_records_sql($sql, ['courseid' => $courseid]);
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

        $dfs = function (int $node) use (&$dfs, &$graph, &$visited, &$instack, &$hascycle): void {
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

    /**
     * Handle a group member being added to a source group by propagating to target groups.
     *
     * @param int $groupid The group id a member was added to
     * @param int $userid The user id that was added
     */
    public static function handle_group_member_added(int $groupid, int $userid): void {
        global $CFG;
        require_once($CFG->dirroot . '/group/lib.php');
        $sourcegroupmappingrecords = self::get_sourcegroup_mappings($groupid);
        foreach ($sourcegroupmappingrecords as $mappingrecord) {
            // A source group has a new member, add to target group.
            $targetgroups = self::get_targetgroups_for_mapping((int) $mappingrecord->mappingid);
            foreach ($targetgroups as $targetgroup) {
                groups_add_member((int) $targetgroup->targetgroupid, $userid);
            }
        }
    }

    /**
     * Handle a group member being removed from a group by enforcing mapping rules.
     *
     * Two cases are handled:
     * 1. User removed from a **source group** (cover mode only): The user is removed from the
     *    target group, because in cover mode the target must exactly match the union of all sources.
     *    In subset mode, removal is not propagated.
     * 2. User removed from a **target group**: If the user is still a member of any source group
     *    of a mapping that targets this group, the user is immediately re-added to the target group
     *    (the mapping rule takes precedence over manual removal).
     *
     * @param int $groupid The group id a member was removed from
     * @param int $userid The user id that was removed
     */
    public static function handle_group_member_removed(int $groupid, int $userid): void {
        global $CFG, $DB;
        require_once($CFG->dirroot . '/group/lib.php');

        // Case 1: The group is a source group — propagate removal to target (cover mode only).
        $sourcegroupmappingrecords = self::get_sourcegroup_mappings($groupid);
        foreach ($sourcegroupmappingrecords as $sourcegrouprecord) {
            $mappingid = (int) $sourcegrouprecord->mappingid;
            $mapping = $DB->get_record('local_groupmerge_mapping', ['id' => $mappingid], 'id, type', MUST_EXIST);

            // Only propagate removal for cover-mode mappings.
            if ((int) $mapping->type !== group_syncer::TYPE_COVER) {
                continue;
            }

            // Check if user is still in another source group of the same mapping.
            $sourcegroups = self::get_sourcegroups_for_mapping($mappingid);
            $stillinothersource = false;
            foreach ($sourcegroups as $sg) {
                if ((int) $sg->sourcegroupid !== $groupid && groups_is_member((int) $sg->sourcegroupid, $userid)) {
                    $stillinothersource = true;
                    break;
                }
            }
            if ($stillinothersource) {
                continue;
            }

            $targetgroups = self::get_targetgroups_for_mapping($mappingid);
            foreach ($targetgroups as $targetgroup) {
                groups_remove_member((int) $targetgroup->targetgroupid, $userid);
            }
        }

        // Case 2: The group is a target group — re-add the user if still in any source group.
        $targetgroupmappingrecords = self::get_targetgroup_mappings($groupid);
        foreach ($targetgroupmappingrecords as $targetgrouprecord) {
            $mappingid = (int) $targetgrouprecord->mappingid;
            $sourcegroups = self::get_sourcegroups_for_mapping($mappingid);
            foreach ($sourcegroups as $sourcegroup) {
                if (groups_is_member((int) $sourcegroup->sourcegroupid, $userid)) {
                    // User still belongs to a source group — re-add to target.
                    groups_add_member($groupid, $userid);

                    // Notify the current user that the member was re-added by the plugin.
                    $mapping = $DB->get_record('local_groupmerge_mapping', ['id' => $mappingid], 'id, courseid', MUST_EXIST);
                    $group = groups_get_group($groupid, 'id, name', MUST_EXIST);
                    $configurl = new \moodle_url(
                        '/local/groupmerge/groupmerge_config.php',
                        ['courseid' => (int) $mapping->courseid]
                    );
                    $a = new \stdClass();
                    $a->groupname = $group->name;
                    $a->configurl = $configurl->out(false);
                    \core\notification::add(
                        get_string('member_readded', 'local_groupmerge', $a),
                        \core\notification::WARNING
                    );

                    // One match is enough, no need to check further source groups for this mapping.
                    break;
                }
            }
        }
    }

    /**
     * Get all sourcegroup records where the given group is a source group.
     *
     * Returns records including the mappingid.
     *
     * @param int $groupid The source group id
     * @return array Array of sourcegroup records
     */
    public static function get_sourcegroup_mappings(int $groupid): array {
        global $DB;
        return $DB->get_records('local_groupmerge_sourcegroup', ['sourcegroupid' => $groupid]);
    }

    /**
     * Get all targetgroup records where the given group is a target group.
     *
     * Returns records including the mappingid.
     *
     * @param int $groupid The target group id
     * @return array Array of targetgroup records
     */
    public static function get_targetgroup_mappings(int $groupid): array {
        global $DB;
        return $DB->get_records('local_groupmerge_targetgroup', ['targetgroupid' => $groupid]);
    }

    /**
     * Get the target group records for a given mapping.
     *
     * @param int $mappingid The mapping id
     * @return array Array of targetgroup records
     */
    public static function get_targetgroups_for_mapping(int $mappingid): array {
        global $DB;
        return $DB->get_records('local_groupmerge_targetgroup', ['mappingid' => $mappingid]);
    }

    /**
     * Get the source group records for a given mapping.
     *
     * @param int $mappingid The mapping id
     * @return array Array of sourcegroup records
     */
    public static function get_sourcegroups_for_mapping(int $mappingid): array {
        global $DB;
        return $DB->get_records('local_groupmerge_sourcegroup', ['mappingid' => $mappingid]);
    }

    /**
     * Get all user ids from source groups for a given target group.
     *
     * @param int $groupid The target group id
     * @return array Array of arrays of user objects keyed by user id
     */
    public static function get_sourcegroup_userids_for_targetgroup(int $groupid): array {
        global $CFG;
        require_once($CFG->dirroot . '/group/lib.php');
        $userids = [];
        $targetgrouprecords = self::get_targetgroup_mappings($groupid);
        foreach ($targetgrouprecords as $targetgrouprecord) {
            $sourcegroups = self::get_sourcegroups_for_mapping((int) $targetgrouprecord->mappingid);
            foreach ($sourcegroups as $sourcegroup) {
                $userids[] = groups_get_members((int) $sourcegroup->sourcegroupid, 'u.id');
            }
        }
        return $userids;
    }

    /**
     * Create a new mapping with its target group and source group associations.
     *
     * @param int $courseid The course id
     * @param int $targetgroupid The target group id
     * @param array $sourcegroupids Array of source group ids
     * @param int $type Mapping type (group_syncer::TYPE_SUBSET or group_syncer::TYPE_COVER)
     * @param string|null $name Optional mapping name
     * @return int The id of the created mapping
     */
    public static function create_mapping(
        int $courseid,
        int $targetgroupid,
        array $sourcegroupids,
        int $type = group_syncer::TYPE_SUBSET,
        ?string $name = null
    ): int {
        global $DB;
        $sourcegroupids = array_map('intval', $sourcegroupids);

        // Validate that target group and all source groups belong to the given course.
        self::require_groups_belong_to_course($courseid, array_merge([$targetgroupid], $sourcegroupids));

        // Validate the mapping type.
        self::validate_type($type);

        $clock = \core\di::get(\core\clock::class);
        $now = $clock->time();

        $mappingrecord = new stdClass();
        $mappingrecord->courseid = $courseid;
        $mappingrecord->name = $name;
        $mappingrecord->type = $type;
        $mappingrecord->timecreated = $now;
        $mappingrecord->timemodified = $now;
        $mappingid = $DB->insert_record('local_groupmerge_mapping', $mappingrecord);

        $targetrecord = new stdClass();
        $targetrecord->mappingid = $mappingid;
        $targetrecord->targetgroupid = $targetgroupid;
        $targetrecord->timecreated = $now;
        $targetrecord->timemodified = $now;
        $DB->insert_record('local_groupmerge_targetgroup', $targetrecord);

        foreach ($sourcegroupids as $sourcegroupid) {
            $sourcerecord = new stdClass();
            $sourcerecord->mappingid = $mappingid;
            $sourcerecord->sourcegroupid = $sourcegroupid;
            $sourcerecord->timecreated = $now;
            $sourcerecord->timemodified = $now;
            $DB->insert_record('local_groupmerge_sourcegroup', $sourcerecord);
        }

        return $mappingid;
    }

    /**
     * Update an existing mapping's metadata and replace its source group associations.
     *
     * The target group cannot be changed; only name, type and source groups are updated.
     *
     * @param int $mappingid The mapping id to update
     * @param array $sourcegroupids New array of source group ids (replaces all existing ones)
     * @param int $type Mapping type (group_syncer::TYPE_SUBSET or group_syncer::TYPE_COVER)
     * @param string|null $name Optional mapping name
     */
    public static function update_mapping(
        int $mappingid,
        array $sourcegroupids,
        int $type,
        ?string $name = null
    ): void {
        global $DB;

        $sourcegroupids = array_map('intval', $sourcegroupids);

        // Load the existing mapping to determine the course.
        $mapping = $DB->get_record('local_groupmerge_mapping', ['id' => $mappingid], 'id, courseid', MUST_EXIST);
        $courseid = (int) $mapping->courseid;

        // Validate that all source groups belong to the mapping's course.
        self::require_groups_belong_to_course($courseid, $sourcegroupids);

        // Validate the mapping type.
        self::validate_type($type);

        $clock = \core\di::get(\core\clock::class);
        $now = $clock->time();

        $mappingrecord = new stdClass();
        $mappingrecord->id = $mappingid;
        $mappingrecord->name = $name;
        $mappingrecord->type = $type;
        $mappingrecord->timemodified = $now;
        $DB->update_record('local_groupmerge_mapping', $mappingrecord);

        // Remove old source groups and re-insert.
        $DB->delete_records('local_groupmerge_sourcegroup', ['mappingid' => $mappingid]);

        foreach ($sourcegroupids as $sourcegroupid) {
            $sourcerecord = new stdClass();
            $sourcerecord->mappingid = $mappingid;
            $sourcerecord->sourcegroupid = (int) $sourcegroupid;
            $sourcerecord->timecreated = $now;
            $sourcerecord->timemodified = $now;
            $DB->insert_record('local_groupmerge_sourcegroup', $sourcerecord);
        }
    }

    /**
     * Get all mappings for a course with transitively resolved source groups.
     *
     * For each target group, this method collects all source groups — including those that are
     * themselves target groups of other mappings — and recursively resolves them into their
     * ultimate leaf source groups. The result shows, for every target group, the complete set
     * of groups whose members will end up in that target.
     *
     * @param int $courseid The course id
     * @return array Array of objects with 'targetgroup' (object with id, name) and 'sourcegroups' (array of objects with id, name)
     */
    public static function get_resolved_mappings_for_course(int $courseid): array {
        $mappings = self::get_group_mappings_with_group_name($courseid);

        if (empty($mappings)) {
            return [];
        }

        // Build a lookup: targetgroupid => [sourcegroupids].
        $targettoraw = [];
        // Build a lookup: targetgroupid => mapping type.
        $targettotype = [];
        foreach ($mappings as $mapping) {
            $targetid = $mapping->targetgroup->id;
            $targettoraw[$targetid] = [];
            $targettotype[$targetid] = (int) $mapping->type;
            foreach ($mapping->sourcegroups as $sg) {
                $targettoraw[$targetid][] = $sg->id;
            }
        }

        // Build a name lookup for all groups.
        $names = [];
        foreach ($mappings as $mapping) {
            $names[$mapping->targetgroup->id] = $mapping->targetgroup->name;
            foreach ($mapping->sourcegroups as $sg) {
                $names[$sg->id] = $sg->name;
            }
        }

        /*
         * Recursively collect all effective source groups for a target.
         * Every direct source group is kept. If a source group is itself a target of another mapping,
         * its own sources are added transitively. Additionally:
         * - If the mapping to the in-between group has type "cover", the in-between group is NOT shown
         *  (its members are exactly the union of its sources, so it is redundant).
         * - If the mapping to the in-between group has type "subset", the in-between group IS shown
         * (it may have additional members beyond its source groups).
         */
        $resolve = function (int $targetid, array &$visited) use (&$resolve, &$targettoraw, &$targettotype): array {
            if (isset($visited[$targetid])) {
                // Circular reference — stop recursion.
                return [];
            }
            $visited[$targetid] = true;

            $resolved = [];
            foreach ($targettoraw[$targetid] ?? [] as $sourceid) {
                if (isset($targettoraw[$sourceid])) {
                    // This source group is itself a target of another mapping — resolve transitively.
                    $resolved = array_replace($resolved, $resolve($sourceid, $visited));
                    // Only keep the in-between group itself if its mapping type is "subset".
                    // In "cover" mode the group's members are exactly its source groups, so it is redundant.
                    if (($targettotype[$sourceid] ?? null) === group_syncer::TYPE_SUBSET) {
                        $resolved[$sourceid] = true;
                    }
                } else {
                    // Leaf source group — always keep.
                    $resolved[$sourceid] = true;
                }
            }
            return $resolved;
        };

        $result = [];
        foreach ($mappings as $mapping) {
            $targetid = $mapping->targetgroup->id;
            $visited = [];
            $resolvedids = array_keys($resolve($targetid, $visited));

            // Only include mappings whose resolved source set differs from the direct source set.
            // This covers both cases: additional transitive sources and cover-mode in-between
            // groups whose ids are replaced by their leaf sources.
            $directids = $targettoraw[$targetid];
            $directset = array_flip($directids);
            $resolvedset = array_flip($resolvedids);
            if ($directset == $resolvedset) {
                continue;
            }

            $sourcegroups = [];
            foreach ($resolvedids as $id) {
                $sourcegroups[] = (object) [
                    'id' => $id,
                    'name' => $names[$id] ?? (string) $id,
                ];
            }

            // Sort source groups alphabetically by name.
            usort($sourcegroups, fn($a, $b) => strcmp($a->name, $b->name));

            $result[] = (object) [
                'targetgroup' => (object) [
                    'id' => $targetid,
                    'name' => $mapping->targetgroup->name,
                ],
                'sourcegroups' => $sourcegroups,
            ];
        }

        // Sort by target group name.
        usort($result, fn($a, $b) => strcmp($a->targetgroup->name, $b->targetgroup->name));

        return $result;
    }

    /**
     * Update mappings after a group has been deleted.
     *
     * If the deleted group was a target group of a mapping, the complete mapping is removed
     * (a mapping without a target is meaningless).
     * If the deleted group was a source group of a mapping, only the sourcegroup entry is removed
     * from the mapping — the mapping itself is kept with its remaining source groups.
     *
     * @param int $groupid The id of the deleted group
     */
    public static function update_mappings_on_group_deletion(int $groupid): void {
        global $DB;

        // Step 1: Delete complete mappings where this group was the target.
        $targetrecords = $DB->get_records('local_groupmerge_targetgroup', ['targetgroupid' => $groupid]);
        foreach ($targetrecords as $record) {
            self::delete_mapping((int) $record->mappingid);
        }

        // Step 2: Remove sourcegroup entries referencing this group (mapping itself stays).
        $DB->delete_records('local_groupmerge_sourcegroup', ['sourcegroupid' => $groupid]);

        // Step 3: Find and delete mappings that now have no source groups left.
        $orphanedids = self::get_orphaned_mapping_ids();
        foreach ($orphanedids as $orphanedid) {
            self::delete_mapping($orphanedid);
        }
    }

    /**
     * Get ids of all mappings that have no source group entries.
     *
     * A mapping without source groups is considered orphaned and should typically be deleted.
     *
     * @return int[] Array of mapping ids that have no associated source groups
     */
    public static function get_orphaned_mapping_ids(): array {
        global $DB;

        $sql = "SELECT m.id
                FROM {local_groupmerge_mapping} m
                LEFT JOIN {local_groupmerge_sourcegroup} sg ON sg.mappingid = m.id
                WHERE sg.id IS NULL";
        $records = $DB->get_records_sql($sql);

        return array_values(array_map(fn($record) => (int) $record->id, $records));
    }

    /**
     * Find all mapping ids for a course whose target group is restricted by hook subscribers.
     *
     * Dispatches the {@see restrict_target_groups} hook to determine which groups are
     * currently disallowed as targets. Returns the mapping ids that use one of these groups
     * as their target. The caller is responsible for deleting them.
     *
     * @param int $courseid The course id
     * @return int[] Array of mapping ids whose target group is restricted.
     */
    public static function get_mapping_ids_with_restricted_target_groups(int $courseid): array {
        $hook = new restrict_target_groups($courseid);
        \core\di::get(\core\hook\manager::class)->dispatch($hook);
        $unallowedgroupids = $hook->get_unallowed_targetgroupids();

        if (empty($unallowedgroupids)) {
            return [];
        }

        $records = self::get_mapping_records_for_course($courseid);

        // Collect unique mapping ids whose target group is restricted.
        $mappingidstoremove = [];
        foreach ($records as $record) {
            $targetgroupid = (int) $record->targetgroupid;
            if (array_key_exists($targetgroupid, $unallowedgroupids)) {
                $mappingidstoremove[(int) $record->mappingid] = true;
            }
        }

        return array_keys($mappingidstoremove);
    }

    /**
     * Delete a complete mapping including all its target and source group associations.
     *
     * @param int $mappingid The mapping id to delete
     */
    public static function delete_mapping(int $mappingid): void {
        global $DB;
        $DB->delete_records('local_groupmerge_sourcegroup', ['mappingid' => $mappingid]);
        $DB->delete_records('local_groupmerge_targetgroup', ['mappingid' => $mappingid]);
        $DB->delete_records('local_groupmerge_mapping', ['id' => $mappingid]);
    }
}
