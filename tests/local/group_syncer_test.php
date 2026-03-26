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

use stdClass;

/**
 * Unit tests for the group_syncer class of local_groupmerge.
 *
 * @package   local_groupmerge
 * @copyright 2025 ISB Bayern
 * @author    Philipp Memmel
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class group_syncer_test extends \advanced_testcase {

    /**
     * Data provider for {@see test_sync_group_members}.
     *
     * Each dataset uses human-readable group and user names.
     * - sourcegroups: associative array mapping source group name to an array of user names.
     * - mappings: array of arrays, each mapping a target group name to source group names and a type.
     * - initialtargetmembers: associative array mapping target group name to user names already present before sync.
     * - expectedtargetmembers: associative array mapping target group name to user names expected after sync.
     *
     * @return array
     */
    public static function sync_group_members_provider(): array {
        return [
            'cover_single_source_to_empty_target' => [
                'sourcegroups' => [
                    'Group A' => ['User 1', 'User 2', 'User 3'],
                ],
                'mappings' => [
                    'Group B' => ['type' => group_syncer::TYPE_COVER, 'sources' => ['Group A']],
                ],
                'initialtargetmembers' => [
                    'Group B' => [],
                ],
                'expectedtargetmembers' => [
                    'Group B' => ['User 1', 'User 2', 'User 3'],
                ],
            ],
            'cover_multiple_sources_merged_into_single_target' => [
                'sourcegroups' => [
                    'Group A' => ['User 1', 'User 2'],
                    'Group B' => ['User 3', 'User 4'],
                ],
                'mappings' => [
                    'Group C' => ['type' => group_syncer::TYPE_COVER, 'sources' => ['Group A', 'Group B']],
                ],
                'initialtargetmembers' => [
                    'Group C' => [],
                ],
                'expectedtargetmembers' => [
                    'Group C' => ['User 1', 'User 2', 'User 3', 'User 4'],
                ],
            ],
            'cover_overlapping_sources_deduplicated_in_target' => [
                'sourcegroups' => [
                    'Group A' => ['User 1', 'User 2', 'User 3'],
                    'Group B' => ['User 2', 'User 3', 'User 4'],
                ],
                'mappings' => [
                    'Group C' => ['type' => group_syncer::TYPE_COVER, 'sources' => ['Group A', 'Group B']],
                ],
                'initialtargetmembers' => [
                    'Group C' => [],
                ],
                'expectedtargetmembers' => [
                    'Group C' => ['User 1', 'User 2', 'User 3', 'User 4'],
                ],
            ],
            'cover_extra_members_removed_from_target' => [
                'sourcegroups' => [
                    'Group A' => ['User 1', 'User 2'],
                ],
                'mappings' => [
                    'Group B' => ['type' => group_syncer::TYPE_COVER, 'sources' => ['Group A']],
                ],
                'initialtargetmembers' => [
                    'Group B' => ['User 1', 'User 2', 'User 3', 'User 4'],
                ],
                'expectedtargetmembers' => [
                    'Group B' => ['User 1', 'User 2'],
                ],
            ],
            'cover_empty_source_clears_target' => [
                'sourcegroups' => [
                    'Group A' => [],
                ],
                'mappings' => [
                    'Group B' => ['type' => group_syncer::TYPE_COVER, 'sources' => ['Group A']],
                ],
                'initialtargetmembers' => [
                    'Group B' => ['User 1', 'User 2'],
                ],
                'expectedtargetmembers' => [
                    'Group B' => [],
                ],
            ],
            'cover_multiple_independent_target_groups' => [
                'sourcegroups' => [
                    'Group A' => ['User 1', 'User 2'],
                    'Group B' => ['User 3', 'User 4'],
                ],
                'mappings' => [
                    'Group C' => ['type' => group_syncer::TYPE_COVER, 'sources' => ['Group A']],
                    'Group D' => ['type' => group_syncer::TYPE_COVER, 'sources' => ['Group B']],
                ],
                'initialtargetmembers' => [
                    'Group C' => [],
                    'Group D' => [],
                ],
                'expectedtargetmembers' => [
                    'Group C' => ['User 1', 'User 2'],
                    'Group D' => ['User 3', 'User 4'],
                ],
            ],
            'cover_mixed_add_and_remove' => [
                'sourcegroups' => [
                    'Group A' => ['User 1', 'User 2', 'User 3'],
                ],
                'mappings' => [
                    'Group B' => ['type' => group_syncer::TYPE_COVER, 'sources' => ['Group A']],
                ],
                'initialtargetmembers' => [
                    'Group B' => ['User 2', 'User 4'],
                ],
                'expectedtargetmembers' => [
                    'Group B' => ['User 1', 'User 2', 'User 3'],
                ],
            ],
            'cover_no_mappings_leaves_groups_untouched' => [
                'sourcegroups' => [],
                'mappings' => [],
                'initialtargetmembers' => [
                    'Group A' => ['User 1', 'User 2'],
                ],
                'expectedtargetmembers' => [
                    'Group A' => ['User 1', 'User 2'],
                ],
            ],
            'subset_single_source_to_empty_target' => [
                'sourcegroups' => [
                    'Group A' => ['User 1', 'User 2'],
                ],
                'mappings' => [
                    'Group B' => ['type' => group_syncer::TYPE_SUBSET, 'sources' => ['Group A']],
                ],
                'initialtargetmembers' => [
                    'Group B' => [],
                ],
                'expectedtargetmembers' => [
                    'Group B' => ['User 1', 'User 2'],
                ],
            ],
            'subset_extra_members_kept_in_target' => [
                'sourcegroups' => [
                    'Group A' => ['User 1', 'User 2'],
                ],
                'mappings' => [
                    'Group B' => ['type' => group_syncer::TYPE_SUBSET, 'sources' => ['Group A']],
                ],
                'initialtargetmembers' => [
                    'Group B' => ['User 1', 'User 3', 'User 4'],
                ],
                'expectedtargetmembers' => [
                    'Group B' => ['User 1', 'User 2', 'User 3', 'User 4'],
                ],
            ],
            'subset_multiple_sources_merged_extra_kept' => [
                'sourcegroups' => [
                    'Group A' => ['User 1', 'User 2'],
                    'Group B' => ['User 3'],
                ],
                'mappings' => [
                    'Group C' => ['type' => group_syncer::TYPE_SUBSET, 'sources' => ['Group A', 'Group B']],
                ],
                'initialtargetmembers' => [
                    'Group C' => ['User 4', 'User 5'],
                ],
                'expectedtargetmembers' => [
                    'Group C' => ['User 1', 'User 2', 'User 3', 'User 4', 'User 5'],
                ],
            ],
            'subset_empty_source_keeps_target_members' => [
                'sourcegroups' => [
                    'Group A' => [],
                ],
                'mappings' => [
                    'Group B' => ['type' => group_syncer::TYPE_SUBSET, 'sources' => ['Group A']],
                ],
                'initialtargetmembers' => [
                    'Group B' => ['User 1', 'User 2'],
                ],
                'expectedtargetmembers' => [
                    'Group B' => ['User 1', 'User 2'],
                ],
            ],
            'mixed_cover_and_subset_targets' => [
                'sourcegroups' => [
                    'Group A' => ['User 1', 'User 2'],
                    'Group B' => ['User 3'],
                ],
                'mappings' => [
                    'Group C' => ['type' => group_syncer::TYPE_COVER, 'sources' => ['Group A']],
                    'Group D' => ['type' => group_syncer::TYPE_SUBSET, 'sources' => ['Group B']],
                ],
                'initialtargetmembers' => [
                    'Group C' => ['User 1', 'User 4'],
                    'Group D' => ['User 4', 'User 5'],
                ],
                'expectedtargetmembers' => [
                    'Group C' => ['User 1', 'User 2'],
                    'Group D' => ['User 3', 'User 4', 'User 5'],
                ],
            ],
        ];
    }

    /**
     * Tests the synchronization of group members based on various mapping constellations.
     *
     * @dataProvider sync_group_members_provider
     * @covers \local_groupmerge\local\group_syncer::sync_group_members
     *
     * @param array $sourcegroups Associative array of source group name => array of user names
     * @param array $mappings Associative array of target group name => ['type' => int, 'sources' => string[]]
     * @param array $initialtargetmembers Associative array of target group name => array of user names pre-populated
     * @param array $expectedtargetmembers Associative array of target group name => array of user names expected after sync
     */
    public function test_sync_group_members(
        array $sourcegroups,
        array $mappings,
        array $initialtargetmembers,
        array $expectedtargetmembers
    ): void {
        global $DB, $CFG;
        require_once($CFG->dirroot . '/group/lib.php');

        $this->resetAfterTest();

        // Create course.
        $course = $this->getDataGenerator()->create_course();

        // Collect all unique user names from all datasets.
        $allusernames = [];
        foreach ($sourcegroups as $membernames) {
            foreach ($membernames as $name) {
                $allusernames[$name] = true;
            }
        }
        foreach ($initialtargetmembers as $membernames) {
            foreach ($membernames as $name) {
                $allusernames[$name] = true;
            }
        }

        // Create users and enrol them into the course.
        $users = [];
        foreach (array_keys($allusernames) as $name) {
            $user = $this->getDataGenerator()->create_user(['firstname' => $name]);
            enrol_try_internal_enrol($course->id, $user->id);
            $users[$name] = $user;
        }

        // Create source groups and assign members.
        $groupids = [];
        foreach ($sourcegroups as $groupname => $membernames) {
            $groupdata = new stdClass();
            $groupdata->name = $groupname;
            $groupdata->courseid = $course->id;
            $groupids[$groupname] = groups_create_group($groupdata);

            foreach ($membernames as $name) {
                groups_add_member($groupids[$groupname], $users[$name]->id);
            }
        }

        // Create target groups and pre-populate with initial members.
        foreach ($initialtargetmembers as $groupname => $membernames) {
            if (!isset($groupids[$groupname])) {
                $groupdata = new stdClass();
                $groupdata->name = $groupname;
                $groupdata->courseid = $course->id;
                $groupids[$groupname] = groups_create_group($groupdata);
            }

            foreach ($membernames as $name) {
                groups_add_member($groupids[$groupname], $users[$name]->id);
            }
        }

        // Create mapping records in the database.
        $now = time();
        foreach ($mappings as $targetname => $mappingdata) {
            $type = $mappingdata['type'];
            foreach ($mappingdata['sources'] as $sourcename) {
                $record = new stdClass();
                $record->sourcegroupid = $groupids[$sourcename];
                $record->targetgroupid = $groupids[$targetname];
                $record->type = $type;
                $record->timecreated = $now;
                $record->timemodified = $now;
                $DB->insert_record('local_groupmerge_groupmapping', $record);
            }
        }

        // Execute the sync.
        $groupsyncer = new group_syncer($course->id);
        $groupsyncer->sync_group_members();

        // Assert expected members for each target group.
        foreach ($expectedtargetmembers as $groupname => $expectednames) {
            $actualmembers = groups_get_members($groupids[$groupname], 'u.id');
            $actualmemberids = array_map(fn($member) => (int) $member->id, $actualmembers);
            sort($actualmemberids);

            $expecteduserids = array_map(fn($name) => (int) $users[$name]->id, $expectednames);
            sort($expecteduserids);

            $this->assertEquals(
                $expecteduserids,
                $actualmemberids,
                "Target group '{$groupname}' has unexpected members after sync."
            );
        }
    }

    /**
     * Tests that sync_group_members throws a coding_exception when a mapping record has a null type.
     *
     * @covers \local_groupmerge\local\group_syncer::sync_group_members
     */
    public function test_sync_group_members_throws_on_null_type(): void {
        global $DB, $CFG;
        require_once($CFG->dirroot . '/group/lib.php');

        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course();
        $groupa = $this->getDataGenerator()->create_group(['courseid' => $course->id, 'name' => 'Group A']);
        $groupb = $this->getDataGenerator()->create_group(['courseid' => $course->id, 'name' => 'Group B']);

        $now = time();
        $DB->insert_record('local_groupmerge_groupmapping', (object) [
            'sourcegroupid' => $groupa->id,
            'targetgroupid' => $groupb->id,
            'type' => null,
            'timecreated' => $now,
            'timemodified' => $now,
        ]);

        $groupsyncer = new group_syncer($course->id);

        $this->expectException(\coding_exception::class);
        $this->expectExceptionMessage('has null type');
        $groupsyncer->sync_group_members();
    }

    /**
     * Tests that sync_group_members throws a coding_exception when mapping records for the same
     * target group have inconsistent types.
     *
     * @covers \local_groupmerge\local\group_syncer::sync_group_members
     */
    public function test_sync_group_members_throws_on_inconsistent_types(): void {
        global $DB, $CFG;
        require_once($CFG->dirroot . '/group/lib.php');

        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course();
        $groupa = $this->getDataGenerator()->create_group(['courseid' => $course->id, 'name' => 'Group A']);
        $groupb = $this->getDataGenerator()->create_group(['courseid' => $course->id, 'name' => 'Group B']);
        $groupc = $this->getDataGenerator()->create_group(['courseid' => $course->id, 'name' => 'Group C']);

        $now = time();
        $DB->insert_record('local_groupmerge_groupmapping', (object) [
            'sourcegroupid' => $groupa->id,
            'targetgroupid' => $groupc->id,
            'type' => group_syncer::TYPE_SUBSET,
            'timecreated' => $now,
            'timemodified' => $now,
        ]);
        $DB->insert_record('local_groupmerge_groupmapping', (object) [
            'sourcegroupid' => $groupb->id,
            'targetgroupid' => $groupc->id,
            'type' => group_syncer::TYPE_COVER,
            'timecreated' => $now,
            'timemodified' => $now,
        ]);

        $groupsyncer = new group_syncer($course->id);

        $this->expectException(\coding_exception::class);
        $this->expectExceptionMessage('Inconsistent mapping types');
        $groupsyncer->sync_group_members();
    }
}
