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
 * Unit tests for the utils class of local_groupmerge.
 *
 * @package   local_groupmerge
 * @copyright 2025 ISB Bayern
 * @author    Philipp Memmel
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class utils_test extends \advanced_testcase {
    /**
     * Data provider for {@see test_has_circular_mapping}.
     *
     * @return array
     */
    public static function has_circular_mapping_provider(): array {
        return [
            'empty_graph' => [
                'mappings' => [],
                'expected' => false,
            ],
            'multiple_sources_to_one_target' => [
                'mappings' => [
                    ['sourcegroupid' => 1, 'targetgroupid' => 3],
                    ['sourcegroupid' => 2, 'targetgroupid' => 3],
                ],
                'expected' => false,
            ],
            'simple_chain_A->B->C' => [
                'mappings' => [
                    ['sourcegroupid' => 1, 'targetgroupid' => 2],
                    ['sourcegroupid' => 2, 'targetgroupid' => 3],
                ],
                'expected' => false,
            ],
            'direct_cycle_A->B_B->A' => [
                'mappings' => [
                    ['sourcegroupid' => 1, 'targetgroupid' => 2],
                    ['sourcegroupid' => 2, 'targetgroupid' => 1],
                ],
                'expected' => true,
            ],
            'three_transition_cycle_A->B->C->A' => [
                'mappings' => [
                    ['sourcegroupid' => 1, 'targetgroupid' => 2],
                    ['sourcegroupid' => 2, 'targetgroupid' => 3],
                    ['sourcegroupid' => 3, 'targetgroupid' => 1],
                ],
                'expected' => true,
            ],
            'chain_extended_without_closing_cycle' => [
                'mappings' => [
                    ['sourcegroupid' => 1, 'targetgroupid' => 2],
                    ['sourcegroupid' => 2, 'targetgroupid' => 3],
                    ['sourcegroupid' => 4, 'targetgroupid' => 3],
                ],
                'expected' => false,
            ],
            'independent_chains_no_cycle' => [
                'mappings' => [
                    ['sourcegroupid' => 1, 'targetgroupid' => 2],
                    ['sourcegroupid' => 3, 'targetgroupid' => 4],
                    ['sourcegroupid' => 5, 'targetgroupid' => 6],
                ],
                'expected' => false,
            ],
        ];
    }

    /**
     * Tests {@see utils::has_circular_mapping} with various graph configurations.
     *
     * @dataProvider has_circular_mapping_provider
     * @covers \local_groupmerge\local\utils::has_circular_mapping
     * @param array $mappings Full set of mappings as [['sourcegroupid' => int, 'targetgroupid' => int], ...]
     * @param bool $expected Expected return value
     */
    public function test_has_circular_mapping(array $mappings, bool $expected): void {
        $this->assertEquals($expected, utils::has_circular_mapping($mappings));
    }

    /**
     * Tests {@see utils::get_mapping_records_for_course} returns no records for a course without mappings.
     *
     * @covers \local_groupmerge\local\utils::get_mapping_records_for_course
     */
    public function test_get_mapping_records_for_course_empty(): void {
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();

        $records = utils::get_mapping_records_for_course($course->id);

        $this->assertEmpty($records);
    }

    /**
     * Tests {@see utils::get_mapping_records_for_course} returns correct records for a course with mappings.
     *
     * @covers \local_groupmerge\local\utils::get_mapping_records_for_course
     */
    public function test_get_mapping_records_for_course_with_mappings(): void {
        global $DB;
        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course();
        $groupa = $this->getDataGenerator()->create_group(['courseid' => $course->id, 'name' => 'Group A']);
        $groupb = $this->getDataGenerator()->create_group(['courseid' => $course->id, 'name' => 'Group B']);
        $groupc = $this->getDataGenerator()->create_group(['courseid' => $course->id, 'name' => 'Group C']);

        $clock = \core\di::get(\core\clock::class);
        $now = $clock->time();
        $DB->insert_record('local_groupmerge_groupmapping', (object) [
            'sourcegroupid' => $groupa->id,
            'targetgroupid' => $groupc->id,
            'type' => group_syncer::TYPE_COVER,
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

        $records = utils::get_mapping_records_for_course($course->id);

        $this->assertCount(2, $records);
        $sourcegroupids = array_column($records, 'sourcegroupid');
        $targetgroupids = array_unique(array_column($records, 'targetgroupid'));
        $this->assertContains((string) $groupa->id, $sourcegroupids);
        $this->assertContains((string) $groupb->id, $sourcegroupids);
        $this->assertCount(1, $targetgroupids);
        $this->assertEquals((string) $groupc->id, reset($targetgroupids));
    }

    /**
     * Tests {@see utils::get_mapping_records_for_course} only returns records belonging to the requested course.
     *
     * @covers \local_groupmerge\local\utils::get_mapping_records_for_course
     */
    public function test_get_mapping_records_for_course_isolates_courses(): void {
        global $DB;
        $this->resetAfterTest();

        $course1 = $this->getDataGenerator()->create_course();
        $course2 = $this->getDataGenerator()->create_course();
        $group1a = $this->getDataGenerator()->create_group(['courseid' => $course1->id]);
        $group1b = $this->getDataGenerator()->create_group(['courseid' => $course1->id]);
        $group2a = $this->getDataGenerator()->create_group(['courseid' => $course2->id]);
        $group2b = $this->getDataGenerator()->create_group(['courseid' => $course2->id]);

        $clock = \core\di::get(\core\clock::class);
        $now = $clock->time();
        $DB->insert_record('local_groupmerge_groupmapping', (object) [
            'sourcegroupid' => $group1a->id,
            'targetgroupid' => $group1b->id,
            'type' => group_syncer::TYPE_COVER,
            'timecreated' => $now,
            'timemodified' => $now,
        ]);
        $DB->insert_record('local_groupmerge_groupmapping', (object) [
            'sourcegroupid' => $group2a->id,
            'targetgroupid' => $group2b->id,
            'type' => group_syncer::TYPE_COVER,
            'timecreated' => $now,
            'timemodified' => $now,
        ]);

        $records1 = utils::get_mapping_records_for_course($course1->id);
        $records2 = utils::get_mapping_records_for_course($course2->id);

        $this->assertCount(1, $records1);
        $this->assertCount(1, $records2);

        $record1 = reset($records1);
        $this->assertEquals($group1a->id, $record1->sourcegroupid);
        $this->assertEquals($group1b->id, $record1->targetgroupid);

        $record2 = reset($records2);
        $this->assertEquals($group2a->id, $record2->sourcegroupid);
        $this->assertEquals($group2b->id, $record2->targetgroupid);
    }

    /**
     * Tests {@see utils::get_group_mappings_with_group_name} returns empty array for a course without mappings.
     *
     * @covers \local_groupmerge\local\utils::get_group_mappings_with_group_name
     */
    public function test_get_group_mappings_with_group_name_empty(): void {
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();

        $result = utils::get_group_mappings_with_group_name($course->id);

        $this->assertIsArray($result);
        $this->assertEmpty($result);
    }

    /**
     * Tests {@see utils::get_group_mappings_with_group_name} returns correctly structured and sorted mappings.
     *
     * @covers \local_groupmerge\local\utils::get_group_mappings_with_group_name
     */
    public function test_get_group_mappings_with_group_name_structure_and_sorting(): void {
        global $DB;
        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course();
        // Create groups with names that test sorting (Z before A alphabetically).
        $groupa = $this->getDataGenerator()->create_group(['courseid' => $course->id, 'name' => 'Alpha']);
        $groupb = $this->getDataGenerator()->create_group(['courseid' => $course->id, 'name' => 'Beta']);
        $groupz = $this->getDataGenerator()->create_group(['courseid' => $course->id, 'name' => 'Zeta']);
        $groupg = $this->getDataGenerator()->create_group(['courseid' => $course->id, 'name' => 'Gamma']);

        $clock = \core\di::get(\core\clock::class);
        $now = $clock->time();
        // Mapping 1: Zeta <- Alpha, Gamma (target Zeta should come after Beta in sorted output).
        $DB->insert_record('local_groupmerge_groupmapping', (object) [
            'sourcegroupid' => $groupa->id,
            'targetgroupid' => $groupz->id,
            'type' => group_syncer::TYPE_COVER,
            'timecreated' => $now,
            'timemodified' => $now,
        ]);
        $DB->insert_record('local_groupmerge_groupmapping', (object) [
            'sourcegroupid' => $groupg->id,
            'targetgroupid' => $groupz->id,
            'type' => group_syncer::TYPE_COVER,
            'timecreated' => $now,
            'timemodified' => $now,
        ]);
        // Mapping 2: Beta <- Alpha.
        $DB->insert_record('local_groupmerge_groupmapping', (object) [
            'sourcegroupid' => $groupa->id,
            'targetgroupid' => $groupb->id,
            'type' => group_syncer::TYPE_COVER,
            'timecreated' => $now,
            'timemodified' => $now,
        ]);

        $result = utils::get_group_mappings_with_group_name($course->id);

        // Expect 2 mappings, sorted by target group name: Beta, then Zeta.
        $this->assertCount(2, $result);

        // First mapping: target Beta.
        $this->assertEquals($groupb->id, $result[0]->targetgroup->id);
        $this->assertEquals('Beta', $result[0]->targetgroup->name);
        $this->assertCount(1, $result[0]->sourcegroups);
        $this->assertEquals($groupa->id, $result[0]->sourcegroups[0]->id);
        $this->assertEquals('Alpha', $result[0]->sourcegroups[0]->name);

        // Second mapping: target Zeta with source groups sorted: Alpha, Gamma.
        $this->assertEquals($groupz->id, $result[1]->targetgroup->id);
        $this->assertEquals('Zeta', $result[1]->targetgroup->name);
        $this->assertCount(2, $result[1]->sourcegroups);
        $this->assertEquals('Alpha', $result[1]->sourcegroups[0]->name);
        $this->assertEquals('Gamma', $result[1]->sourcegroups[1]->name);
    }

    /**
     * Tests {@see utils::get_sourcegroup_userids_for_targetgroup} returns empty array when no mappings exist.
     *
     * @covers \local_groupmerge\local\utils::get_sourcegroup_userids_for_targetgroup
     */
    public function test_get_sourcegroup_userids_for_targetgroup_no_mappings(): void {
        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course();
        $grouptarget = $this->getDataGenerator()->create_group(['courseid' => $course->id, 'name' => 'Target']);

        $result = utils::get_sourcegroup_userids_for_targetgroup($grouptarget->id);

        $this->assertIsArray($result);
        $this->assertEmpty($result);
    }

    /**
     * Tests {@see utils::get_sourcegroup_userids_for_targetgroup} returns users from a single source group.
     *
     * @covers \local_groupmerge\local\utils::get_sourcegroup_userids_for_targetgroup
     */
    public function test_get_sourcegroup_userids_for_targetgroup_single_source(): void {
        global $DB, $CFG;
        require_once($CFG->dirroot . '/group/lib.php');
        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course();
        $grouptarget = $this->getDataGenerator()->create_group(['courseid' => $course->id, 'name' => 'Target']);
        $groupsource = $this->getDataGenerator()->create_group(['courseid' => $course->id, 'name' => 'Source']);

        $user1 = $this->getDataGenerator()->create_and_enrol($course, 'student');
        $user2 = $this->getDataGenerator()->create_and_enrol($course, 'student');
        groups_add_member($groupsource->id, $user1->id);
        groups_add_member($groupsource->id, $user2->id);

        $clock = \core\di::get(\core\clock::class);
        $now = $clock->time();
        $DB->insert_record('local_groupmerge_groupmapping', (object) [
            'sourcegroupid' => $groupsource->id,
            'targetgroupid' => $grouptarget->id,
            'type' => group_syncer::TYPE_COVER,
            'timecreated' => $now,
            'timemodified' => $now,
        ]);

        $result = utils::get_sourcegroup_userids_for_targetgroup($grouptarget->id);

        $this->assertCount(1, $result);
        $sourcemembers = reset($result);
        $this->assertArrayHasKey($user1->id, $sourcemembers);
        $this->assertArrayHasKey($user2->id, $sourcemembers);
    }

    /**
     * Tests {@see utils::get_sourcegroup_userids_for_targetgroup} returns users from multiple source groups.
     *
     * @covers \local_groupmerge\local\utils::get_sourcegroup_userids_for_targetgroup
     */
    public function test_get_sourcegroup_userids_for_targetgroup_multiple_sources(): void {
        global $DB, $CFG;
        require_once($CFG->dirroot . '/group/lib.php');
        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course();
        $grouptarget = $this->getDataGenerator()->create_group(['courseid' => $course->id, 'name' => 'Target']);
        $groupsourcea = $this->getDataGenerator()->create_group(['courseid' => $course->id, 'name' => 'Source A']);
        $groupsourceb = $this->getDataGenerator()->create_group(['courseid' => $course->id, 'name' => 'Source B']);

        $user1 = $this->getDataGenerator()->create_and_enrol($course, 'student');
        $user2 = $this->getDataGenerator()->create_and_enrol($course, 'student');
        $user3 = $this->getDataGenerator()->create_and_enrol($course, 'student');
        groups_add_member($groupsourcea->id, $user1->id);
        groups_add_member($groupsourceb->id, $user2->id);
        groups_add_member($groupsourceb->id, $user3->id);

        $clock = \core\di::get(\core\clock::class);
        $now = $clock->time();
        $DB->insert_record('local_groupmerge_groupmapping', (object) [
            'sourcegroupid' => $groupsourcea->id,
            'targetgroupid' => $grouptarget->id,
            'type' => group_syncer::TYPE_COVER,
            'timecreated' => $now,
            'timemodified' => $now,
        ]);
        $DB->insert_record('local_groupmerge_groupmapping', (object) [
            'sourcegroupid' => $groupsourceb->id,
            'targetgroupid' => $grouptarget->id,
            'type' => group_syncer::TYPE_COVER,
            'timecreated' => $now,
            'timemodified' => $now,
        ]);

        $result = utils::get_sourcegroup_userids_for_targetgroup($grouptarget->id);

        // The function returns one entry per source group.
        $this->assertCount(2, $result);

        // Collect all user IDs across all source group results.
        $alluserids = [];
        foreach ($result as $members) {
            $alluserids = array_merge($alluserids, array_keys($members));
        }
        $this->assertContains($user1->id, $alluserids);
        $this->assertContains($user2->id, $alluserids);
        $this->assertContains($user3->id, $alluserids);
    }

    /**
     * Tests {@see utils::get_sourcegroup_userids_for_targetgroup} includes a user present in multiple source groups.
     *
     * @covers \local_groupmerge\local\utils::get_sourcegroup_userids_for_targetgroup
     */
    public function test_get_sourcegroup_userids_for_targetgroup_user_in_multiple_sources(): void {
        global $DB, $CFG;
        require_once($CFG->dirroot . '/group/lib.php');
        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course();
        $grouptarget = $this->getDataGenerator()->create_group(['courseid' => $course->id, 'name' => 'Target']);
        $groupsourcea = $this->getDataGenerator()->create_group(['courseid' => $course->id, 'name' => 'Source A']);
        $groupsourceb = $this->getDataGenerator()->create_group(['courseid' => $course->id, 'name' => 'Source B']);

        // User is member of both source groups.
        $user1 = $this->getDataGenerator()->create_and_enrol($course, 'student');
        groups_add_member($groupsourcea->id, $user1->id);
        groups_add_member($groupsourceb->id, $user1->id);

        $clock = \core\di::get(\core\clock::class);
        $now = $clock->time();
        $DB->insert_record('local_groupmerge_groupmapping', (object) [
            'sourcegroupid' => $groupsourcea->id,
            'targetgroupid' => $grouptarget->id,
            'type' => group_syncer::TYPE_COVER,
            'timecreated' => $now,
            'timemodified' => $now,
        ]);
        $DB->insert_record('local_groupmerge_groupmapping', (object) [
            'sourcegroupid' => $groupsourceb->id,
            'targetgroupid' => $grouptarget->id,
            'type' => group_syncer::TYPE_COVER,
            'timecreated' => $now,
            'timemodified' => $now,
        ]);

        $result = utils::get_sourcegroup_userids_for_targetgroup($grouptarget->id);

        $this->assertCount(2, $result);
        // User appears in both source group results.
        foreach ($result as $members) {
            $this->assertArrayHasKey($user1->id, $members);
        }
    }

    /**
     * Tests {@see utils::get_sourcegroup_userids_for_targetgroup} with an empty source group.
     *
     * @covers \local_groupmerge\local\utils::get_sourcegroup_userids_for_targetgroup
     */
    public function test_get_sourcegroup_userids_for_targetgroup_empty_source_group(): void {
        global $DB, $CFG;
        require_once($CFG->dirroot . '/group/lib.php');
        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course();
        $grouptarget = $this->getDataGenerator()->create_group(['courseid' => $course->id, 'name' => 'Target']);
        $groupsource = $this->getDataGenerator()->create_group(['courseid' => $course->id, 'name' => 'Source']);

        $clock = \core\di::get(\core\clock::class);
        $now = $clock->time();
        $DB->insert_record('local_groupmerge_groupmapping', (object) [
            'sourcegroupid' => $groupsource->id,
            'targetgroupid' => $grouptarget->id,
            'type' => group_syncer::TYPE_COVER,
            'timecreated' => $now,
            'timemodified' => $now,
        ]);

        $result = utils::get_sourcegroup_userids_for_targetgroup($grouptarget->id);

        // One entry for the mapping, but no members.
        $this->assertCount(1, $result);
        $sourcemembers = reset($result);
        $this->assertEmpty($sourcemembers);
    }
}
