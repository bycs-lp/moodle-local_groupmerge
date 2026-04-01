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

/**
 * Group syncer class for local_groupmerge.
 *
 * @package   local_groupmerge
 * @copyright 2025 ISB Bayern
 * @author    Philipp Memmel
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class group_syncer {
    /** @var int Mapping type "subset": source group members are added to the target, but extra members are kept. */
    public const TYPE_SUBSET = 1;

    /** @var int Mapping type "cover": target group members are exactly the union of all source groups. */
    public const TYPE_COVER = 2;

    /**
     * Constructor.
     *
     * @param int $courseid The course id
     */
    public function __construct(
        /** @var int The course id of the course to merge groups in. */
        private readonly int $courseid
    ) {
    }

    /**
     * Synchronizes group members based on all configured group mappings for the course.
     *
     * For each target group, all members of all mapped source groups are collected.
     * Members missing from the target group are added. In "cover" mode, members present
     * in the target group but not in any source group are removed. In "subset" mode,
     * extra members are kept.
     */
    public function sync_group_members(): void {
        global $CFG;
        require_once($CFG->dirroot . '/group/lib.php');

        $records = utils::get_mapping_records_for_course($this->courseid);

        // Build structured mapping data grouped by target group id and validate types.
        $grouped = [];
        foreach ($records as $record) {
            $targetgroupid = (int) $record->targetgroupid;
            $recordtype = (int) $record->type;

            if (!in_array($recordtype, [self::TYPE_SUBSET, self::TYPE_COVER], true)) {
                throw new \coding_exception(
                    'Mapping record (id: ' . $record->id . ') has invalid type value: ' . $record->type
                );
            }

            if (!isset($grouped[$targetgroupid])) {
                $grouped[$targetgroupid] = [
                    'type' => $recordtype,
                    'sourcegroupids' => [],
                ];
            }
            $grouped[$targetgroupid]['sourcegroupids'][] = (int) $record->sourcegroupid;
        }

        foreach ($grouped as $targetgroupid => $mapping) {
            $type = $mapping['type'];

            // Collect all user ids from all source groups.
            $sourcememberids = [];
            foreach ($mapping['sourcegroupids'] as $sourcegroupid) {
                $sourcemembers = groups_get_members($sourcegroupid, 'u.id');
                foreach ($sourcemembers as $member) {
                    $sourcememberids[(int) $member->id] = true;
                }
            }

            // Get current members of the target group.
            $targetmembers = groups_get_members($targetgroupid, 'u.id');
            $targetmemberids = [];
            foreach ($targetmembers as $member) {
                $targetmemberids[(int) $member->id] = true;
            }

            // Add members that are in source groups but not yet in the target group.
            foreach ($sourcememberids as $userid => $unused) {
                if (!isset($targetmemberids[$userid])) {
                    groups_add_member($targetgroupid, $userid);
                }
            }

            // Remove members that are in the target group but not in any source group (cover mode only).
            if ($type === self::TYPE_COVER) {
                foreach ($targetmemberids as $userid => $unused) {
                    if (!isset($sourcememberids[$userid])) {
                        groups_remove_member($targetgroupid, $userid);
                    }
                }
            }
        }
    }
}
