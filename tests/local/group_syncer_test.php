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

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use stdClass;

/**
 * Unit tests for the group_syncer class of local_groupmerge.
 *
 * @package   local_groupmerge
 * @copyright 2025 ISB Bayern
 * @author    Philipp Memmel
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(\local_groupmerge\local\group_syncer::class)]
final class group_syncer_test extends \advanced_testcase {
    /**
     * Data provider for {@see test_sync_group_members}.
     *
     * Each dataset uses human-readable group and user names.
     * - sourcegroups: associative array mapping source group name to an array of user names.
     * - mappings: associative array mapping target group name to an array of source group names.
     * - initialtargetmembers: associative array mapping target group name to user names already present before sync.
     * - expectedtargetmembers: associative array mapping target group name to user names expected after sync.
     *
     * @return array
     */
    public static function sync_group_members_provider(): array {
        return [
            'single_source_to_empty_target_cover' => [
                'sourcegroups' => [
                    'Group A' => ['User 1', 'User 2', 'User 3'],
                ],
                'mappings' => [
                    'Group B' => ['Group A'],
                ],
                'type' => group_syncer::TYPE_COVER,
                'initialtargetmembers' => [
                    'Group B' => [],
                ],
                'expectedtargetmembers' => [
                    'Group B' => ['User 1', 'User 2', 'User 3'],
                ],
            ],
            'multiple_sources_merged_into_single_target_cover' => [
                'sourcegroups' => [
                    'Group A' => ['User 1', 'User 2'],
                    'Group B' => ['User 3', 'User 4'],
                ],
                'mappings' => [
                    'Group C' => ['Group A', 'Group B'],
                ],
                'type' => group_syncer::TYPE_COVER,
                'initialtargetmembers' => [
                    'Group C' => [],
                ],
                'expectedtargetmembers' => [
                    'Group C' => ['User 1', 'User 2', 'User 3', 'User 4'],
                ],
            ],
            'overlapping_sources_deduplicated_in_target_cover' => [
                'sourcegroups' => [
                    'Group A' => ['User 1', 'User 2', 'User 3'],
                    'Group B' => ['User 2', 'User 3', 'User 4'],
                ],
                'mappings' => [
                    'Group C' => ['Group A', 'Group B'],
                ],
                'type' => group_syncer::TYPE_COVER,
                'initialtargetmembers' => [
                    'Group C' => [],
                ],
                'expectedtargetmembers' => [
                    'Group C' => ['User 1', 'User 2', 'User 3', 'User 4'],
                ],
            ],
            'extra_members_removed_from_target_cover' => [
                'sourcegroups' => [
                    'Group A' => ['User 1', 'User 2'],
                ],
                'mappings' => [
                    'Group B' => ['Group A'],
                ],
                'type' => group_syncer::TYPE_COVER,
                'initialtargetmembers' => [
                    'Group B' => ['User 1', 'User 2', 'User 3', 'User 4'],
                ],
                'expectedtargetmembers' => [
                    'Group B' => ['User 1', 'User 2'],
                ],
            ],
            'empty_source_clears_target_cover' => [
                'sourcegroups' => [
                    'Group A' => [],
                ],
                'mappings' => [
                    'Group B' => ['Group A'],
                ],
                'type' => group_syncer::TYPE_COVER,
                'initialtargetmembers' => [
                    'Group B' => ['User 1', 'User 2'],
                ],
                'expectedtargetmembers' => [
                    'Group B' => [],
                ],
            ],
            'multiple_independent_target_groups_cover' => [
                'sourcegroups' => [
                    'Group A' => ['User 1', 'User 2'],
                    'Group B' => ['User 3', 'User 4'],
                ],
                'mappings' => [
                    'Group C' => ['Group A'],
                    'Group D' => ['Group B'],
                ],
                'type' => group_syncer::TYPE_COVER,
                'initialtargetmembers' => [
                    'Group C' => [],
                    'Group D' => [],
                ],
                'expectedtargetmembers' => [
                    'Group C' => ['User 1', 'User 2'],
                    'Group D' => ['User 3', 'User 4'],
                ],
            ],
            'mixed_add_and_remove_cover' => [
                'sourcegroups' => [
                    'Group A' => ['User 1', 'User 2', 'User 3'],
                ],
                'mappings' => [
                    'Group B' => ['Group A'],
                ],
                'type' => group_syncer::TYPE_COVER,
                'initialtargetmembers' => [
                    'Group B' => ['User 2', 'User 4'],
                ],
                'expectedtargetmembers' => [
                    'Group B' => ['User 1', 'User 2', 'User 3'],
                ],
            ],
            'no_mappings_leaves_groups_untouched' => [
                'sourcegroups' => [],
                'mappings' => [],
                'type' => group_syncer::TYPE_COVER,
                'initialtargetmembers' => [
                    'Group A' => ['User 1', 'User 2'],
                ],
                'expectedtargetmembers' => [
                    'Group A' => ['User 1', 'User 2'],
                ],
            ],
            'subset_adds_members_but_keeps_extra' => [
                'sourcegroups' => [
                    'Group A' => ['User 1', 'User 2'],
                ],
                'mappings' => [
                    'Group B' => ['Group A'],
                ],
                'type' => group_syncer::TYPE_SUBSET,
                'initialtargetmembers' => [
                    'Group B' => ['User 3', 'User 4'],
                ],
                'expectedtargetmembers' => [
                    'Group B' => ['User 1', 'User 2', 'User 3', 'User 4'],
                ],
            ],
            'subset_empty_source_keeps_target_members' => [
                'sourcegroups' => [
                    'Group A' => [],
                ],
                'mappings' => [
                    'Group B' => ['Group A'],
                ],
                'type' => group_syncer::TYPE_SUBSET,
                'initialtargetmembers' => [
                    'Group B' => ['User 1', 'User 2'],
                ],
                'expectedtargetmembers' => [
                    'Group B' => ['User 1', 'User 2'],
                ],
            ],
            'subset_overlapping_sources_adds_missing_keeps_extra' => [
                'sourcegroups' => [
                    'Group A' => ['User 1', 'User 2'],
                    'Group B' => ['User 2', 'User 3'],
                ],
                'mappings' => [
                    'Group C' => ['Group A', 'Group B'],
                ],
                'type' => group_syncer::TYPE_SUBSET,
                'initialtargetmembers' => [
                    'Group C' => ['User 4'],
                ],
                'expectedtargetmembers' => [
                    'Group C' => ['User 1', 'User 2', 'User 3', 'User 4'],
                ],
            ],
        ];
    }

    /**
     * Tests the synchronization of group members based on various mapping constellations.
     *
     * @param array $sourcegroups Associative array of source group name => array of user names
     * @param array $mappings Associative array of target group name => array of source group names
     * @param int $type Mapping type (TYPE_SUBSET or TYPE_COVER)
     * @param array $initialtargetmembers Associative array of target group name => array of user names pre-populated
     * @param array $expectedtargetmembers Associative array of target group name => array of user names expected after sync
     */
    #[DataProvider('sync_group_members_provider')]
    public function test_sync_group_members(
        array $sourcegroups,
        array $mappings,
        int $type,
        array $initialtargetmembers,
        array $expectedtargetmembers
    ): void {
        global $CFG;
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

        // Create mapping records using the utils API.
        foreach ($mappings as $targetname => $sourcenames) {
            $sourcegroupids = array_map(fn($sourcename) => $groupids[$sourcename], $sourcenames);
            utils::create_mapping($course->id, $groupids[$targetname], $sourcegroupids, $type, 'Mapping for ' . $targetname);
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
     * Tests that changing a mapping from SUBSET to COVER removes extra members from the target group.
     *
     * Scenario:
     * 1. Create mapping target1 <- group2, group3 with SUBSET
     * 2. Sync (SUBSET keeps extra members)
     * 3. Manually add an extra user to target1
     * 4. Change mapping to COVER via update_mapping
     * 5. Sync again
     * 6. Extra user should be removed
     */
    public function test_sync_removes_extra_members_after_type_change_to_cover(): void {
        global $CFG;
        require_once($CFG->dirroot . '/group/lib.php');

        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course();

        $user1 = $this->getDataGenerator()->create_and_enrol($course, 'student');
        $user2 = $this->getDataGenerator()->create_and_enrol($course, 'student');
        $user3 = $this->getDataGenerator()->create_and_enrol($course, 'student');
        $userextra = $this->getDataGenerator()->create_and_enrol($course, 'student');

        $group2 = $this->getDataGenerator()->create_group(['courseid' => $course->id, 'name' => 'group2']);
        $group3 = $this->getDataGenerator()->create_group(['courseid' => $course->id, 'name' => 'group3']);
        $target1 = $this->getDataGenerator()->create_group(['courseid' => $course->id, 'name' => 'target1']);

        groups_add_member($group2->id, $user1->id);
        groups_add_member($group2->id, $user2->id);
        groups_add_member($group3->id, $user3->id);

        // Step 1: Create mapping with SUBSET mode.
        $mappingid = utils::create_mapping(
            $course->id,
            $target1->id,
            [$group2->id, $group3->id],
            group_syncer::TYPE_SUBSET
        );

        // Step 2: Sync with SUBSET mode — source members are added to target.
        $groupsyncer = new group_syncer($course->id);
        $groupsyncer->sync_group_members();

        // Verify: target1 now has user1, user2, user3.
        $members = groups_get_members($target1->id, 'u.id');
        $this->assertCount(3, $members);

        // Step 3: Manually add extra user to target1.
        groups_add_member($target1->id, $userextra->id);
        $members = groups_get_members($target1->id, 'u.id');
        $this->assertCount(4, $members);

        // Step 4: Change mapping to COVER mode.
        utils::update_mapping($mappingid, [$group2->id, $group3->id], group_syncer::TYPE_COVER);

        // Step 5: Sync again — COVER mode should remove the extra user.
        $groupsyncer->sync_group_members();

        // Step 6: Assert extra user was removed.
        $members = groups_get_members($target1->id, 'u.id');
        $memberids = array_map(fn($m) => (int) $m->id, $members);
        sort($memberids);

        $expectedids = [(int) $user1->id, (int) $user2->id, (int) $user3->id];
        sort($expectedids);

        $this->assertEquals(
            $expectedids,
            $memberids,
            'Extra user should have been removed after switching from SUBSET to COVER mode.'
        );
    }
}
