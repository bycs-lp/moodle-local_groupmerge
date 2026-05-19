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
        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course();
        $groupa = $this->getDataGenerator()->create_group(['courseid' => $course->id, 'name' => 'Group A']);
        $groupb = $this->getDataGenerator()->create_group(['courseid' => $course->id, 'name' => 'Group B']);
        $groupc = $this->getDataGenerator()->create_group(['courseid' => $course->id, 'name' => 'Group C']);

        utils::create_mapping($course->id, $groupc->id, [$groupa->id, $groupb->id], group_syncer::TYPE_COVER, 'Test mapping');

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
        $this->resetAfterTest();

        $course1 = $this->getDataGenerator()->create_course();
        $course2 = $this->getDataGenerator()->create_course();
        $group1a = $this->getDataGenerator()->create_group(['courseid' => $course1->id]);
        $group1b = $this->getDataGenerator()->create_group(['courseid' => $course1->id]);
        $group2a = $this->getDataGenerator()->create_group(['courseid' => $course2->id]);
        $group2b = $this->getDataGenerator()->create_group(['courseid' => $course2->id]);

        utils::create_mapping($course1->id, $group1b->id, [$group1a->id], group_syncer::TYPE_COVER, 'Course 1 mapping');
        utils::create_mapping($course2->id, $group2b->id, [$group2a->id], group_syncer::TYPE_COVER, 'Course 2 mapping');

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
        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course();
        // Create groups with names that test sorting (Z before A alphabetically).
        $groupa = $this->getDataGenerator()->create_group(['courseid' => $course->id, 'name' => 'Alpha']);
        $groupb = $this->getDataGenerator()->create_group(['courseid' => $course->id, 'name' => 'Beta']);
        $groupz = $this->getDataGenerator()->create_group(['courseid' => $course->id, 'name' => 'Zeta']);
        $groupg = $this->getDataGenerator()->create_group(['courseid' => $course->id, 'name' => 'Gamma']);

        // Mapping 1: Zeta <- Alpha, Gamma (target Zeta should come after Beta in sorted output).
        utils::create_mapping($course->id, $groupz->id, [$groupa->id, $groupg->id], group_syncer::TYPE_COVER, 'Zeta mapping');
        // Mapping 2: Beta <- Alpha.
        utils::create_mapping($course->id, $groupb->id, [$groupa->id], group_syncer::TYPE_COVER, 'Beta mapping');

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
        global $CFG;
        require_once($CFG->dirroot . '/group/lib.php');
        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course();
        $grouptarget = $this->getDataGenerator()->create_group(['courseid' => $course->id, 'name' => 'Target']);
        $groupsource = $this->getDataGenerator()->create_group(['courseid' => $course->id, 'name' => 'Source']);

        $user1 = $this->getDataGenerator()->create_and_enrol($course, 'student');
        $user2 = $this->getDataGenerator()->create_and_enrol($course, 'student');
        groups_add_member($groupsource->id, $user1->id);
        groups_add_member($groupsource->id, $user2->id);

        utils::create_mapping($course->id, $grouptarget->id, [$groupsource->id], group_syncer::TYPE_COVER, 'Test mapping');

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
        global $CFG;
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

        utils::create_mapping(
            $course->id,
            $grouptarget->id,
            [$groupsourcea->id, $groupsourceb->id],
            group_syncer::TYPE_COVER,
            'Test mapping'
        );

        $result = utils::get_sourcegroup_userids_for_targetgroup($grouptarget->id);

        // The function returns one entry per source group.
        $this->assertCount(2, $result);

        // Collect all user IDs across all source group results.
        $alluserids = [];
        foreach ($result as $members) {
            $alluserids = array_merge($alluserids, array_keys($members));
        }
        $this->assertContains((int) $user1->id, $alluserids);
        $this->assertContains((int) $user2->id, $alluserids);
        $this->assertContains((int) $user3->id, $alluserids);
    }

    /**
     * Tests {@see utils::get_sourcegroup_userids_for_targetgroup} includes a user present in multiple source groups.
     *
     * @covers \local_groupmerge\local\utils::get_sourcegroup_userids_for_targetgroup
     */
    public function test_get_sourcegroup_userids_for_targetgroup_user_in_multiple_sources(): void {
        global $CFG;
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

        utils::create_mapping(
            $course->id,
            $grouptarget->id,
            [$groupsourcea->id, $groupsourceb->id],
            group_syncer::TYPE_COVER,
            'Test mapping'
        );

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
        global $CFG;
        require_once($CFG->dirroot . '/group/lib.php');
        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course();
        $grouptarget = $this->getDataGenerator()->create_group(['courseid' => $course->id, 'name' => 'Target']);
        $groupsource = $this->getDataGenerator()->create_group(['courseid' => $course->id, 'name' => 'Source']);

        utils::create_mapping($course->id, $grouptarget->id, [$groupsource->id], group_syncer::TYPE_COVER, 'Test mapping');

        $result = utils::get_sourcegroup_userids_for_targetgroup($grouptarget->id);

        // One entry for the mapping, but no members.
        $this->assertCount(1, $result);
        $sourcemembers = reset($result);
        $this->assertEmpty($sourcemembers);
    }

    /**
     * Tests {@see utils::get_resolved_mappings_for_course} returns empty array for a course without mappings.
     *
     * @covers \local_groupmerge\local\utils::get_resolved_mappings_for_course
     */
    public function test_get_resolved_mappings_empty(): void {
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();

        $result = utils::get_resolved_mappings_for_course($course->id);

        $this->assertIsArray($result);
        $this->assertEmpty($result);
    }

    /**
     * Tests {@see utils::get_resolved_mappings_for_course} omits purely direct mappings (no transitivity).
     *
     * @covers \local_groupmerge\local\utils::get_resolved_mappings_for_course
     */
    public function test_get_resolved_mappings_no_transitivity(): void {
        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course();
        $groupa = $this->getDataGenerator()->create_group(['courseid' => $course->id, 'name' => 'Alpha']);
        $groupb = $this->getDataGenerator()->create_group(['courseid' => $course->id, 'name' => 'Beta']);
        $groupc = $this->getDataGenerator()->create_group(['courseid' => $course->id, 'name' => 'Charlie']);

        // Charlie <- Alpha, Beta (no transitivity — should not appear in resolved table).
        utils::create_mapping($course->id, $groupc->id, [$groupa->id, $groupb->id], group_syncer::TYPE_COVER);

        $result = utils::get_resolved_mappings_for_course($course->id);

        $this->assertEmpty($result);
    }

    /**
     * Tests {@see utils::get_resolved_mappings_for_course} resolves transitive mappings.
     *
     * Setup: C <- A, B (cover) and D <- C (cover).
     * Expected: Only D appears (transitive). Since C is a cover-mode target, its members are exactly
     * the union of A and B, so C itself is not shown. D's effective sources are A, B.
     * C itself is purely direct, so it is not listed either.
     *
     * @covers \local_groupmerge\local\utils::get_resolved_mappings_for_course
     */
    public function test_get_resolved_mappings_transitive(): void {
        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course();
        $groupa = $this->getDataGenerator()->create_group(['courseid' => $course->id, 'name' => 'Alpha']);
        $groupb = $this->getDataGenerator()->create_group(['courseid' => $course->id, 'name' => 'Beta']);
        $groupc = $this->getDataGenerator()->create_group(['courseid' => $course->id, 'name' => 'Charlie']);
        $groupd = $this->getDataGenerator()->create_group(['courseid' => $course->id, 'name' => 'Delta']);

        // C <- A, B.
        utils::create_mapping($course->id, $groupc->id, [$groupa->id, $groupb->id], group_syncer::TYPE_COVER);
        // D <- C (transitive: D should effectively get A, B — C is hidden because it is cover-mode).
        // This makes sense, because in cover mode there are no additional participants in the transitive group.
        utils::create_mapping($course->id, $groupd->id, [$groupc->id], group_syncer::TYPE_COVER);

        $result = utils::get_resolved_mappings_for_course($course->id);

        // Only Delta has transitive sources; Charlie is purely direct.
        $this->assertCount(1, $result);

        $this->assertEquals('Delta', $result[0]->targetgroup->name);
        $names = array_column($result[0]->sourcegroups, 'name');
        $this->assertEquals(['Alpha', 'Beta'], $names);
    }

    /**
     * Tests {@see utils::get_resolved_mappings_for_course} resolves a three-level chain with cover mode.
     *
     * Setup: B <- A (cover), C <- B (cover), D <- C (cover).
     * In cover mode, in-between groups are hidden (their members are exactly their sources).
     * B is purely direct (omitted).
     * C has direct source B, but B is cover-mode -> hidden -> C gets [Alpha].
     * D has direct source C, but C is cover-mode -> hidden -> D gets [Alpha].
     *
     * @covers \local_groupmerge\local\utils::get_resolved_mappings_for_course
     */
    public function test_get_resolved_mappings_deep_chain(): void {
        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course();
        $groupa = $this->getDataGenerator()->create_group(['courseid' => $course->id, 'name' => 'Alpha']);
        $groupb = $this->getDataGenerator()->create_group(['courseid' => $course->id, 'name' => 'Beta']);
        $groupc = $this->getDataGenerator()->create_group(['courseid' => $course->id, 'name' => 'Charlie']);
        $groupd = $this->getDataGenerator()->create_group(['courseid' => $course->id, 'name' => 'Delta']);

        // B <- A.
        utils::create_mapping($course->id, $groupb->id, [$groupa->id], group_syncer::TYPE_COVER);
        // C <- B.
        utils::create_mapping($course->id, $groupc->id, [$groupb->id], group_syncer::TYPE_COVER);
        // D <- C.
        utils::create_mapping($course->id, $groupd->id, [$groupc->id], group_syncer::TYPE_COVER);

        $result = utils::get_resolved_mappings_for_course($course->id);

        // B is purely direct -> omitted. C and D have transitive sources.
        $this->assertCount(2, $result);

        // Charlie: B is cover-mode -> hidden -> resolved sources: [Alpha].
        $this->assertEquals('Charlie', $result[0]->targetgroup->name);
        $names0 = array_column($result[0]->sourcegroups, 'name');
        $this->assertEquals(['Alpha'], $names0);

        // Delta: C is cover-mode -> hidden -> resolved sources: [Alpha].
        $this->assertEquals('Delta', $result[1]->targetgroup->name);
        $names1 = array_column($result[1]->sourcegroups, 'name');
        $this->assertEquals(['Alpha'], $names1);
    }

    /**
     * Tests {@see utils::get_resolved_mappings_for_course} keeps subset-mode in-between groups visible.
     *
     * Setup: C <- A, B (subset) and D <- C (cover).
     * C is a subset-mode target, so it may have extra members and IS shown.
     * D's effective sources: A, B, C (C is kept because its mapping type is subset).
     *
     * @covers \local_groupmerge\local\utils::get_resolved_mappings_for_course
     */
    public function test_get_resolved_mappings_transitive_subset_inbetween_shown(): void {
        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course();
        $groupa = $this->getDataGenerator()->create_group(['courseid' => $course->id, 'name' => 'Alpha']);
        $groupb = $this->getDataGenerator()->create_group(['courseid' => $course->id, 'name' => 'Beta']);
        $groupc = $this->getDataGenerator()->create_group(['courseid' => $course->id, 'name' => 'Charlie']);
        $groupd = $this->getDataGenerator()->create_group(['courseid' => $course->id, 'name' => 'Delta']);

        // C <- A, B (subset mode: C may have additional members).
        utils::create_mapping($course->id, $groupc->id, [$groupa->id, $groupb->id], group_syncer::TYPE_SUBSET);
        // D <- C.
        utils::create_mapping($course->id, $groupd->id, [$groupc->id], group_syncer::TYPE_COVER);

        $result = utils::get_resolved_mappings_for_course($course->id);

        // Only Delta has transitive sources; Charlie is purely direct.
        $this->assertCount(1, $result);

        $this->assertEquals('Delta', $result[0]->targetgroup->name);
        $names = array_column($result[0]->sourcegroups, 'name');
        // C is subset-mode, so it IS shown alongside its resolved sources.
        $this->assertEquals(['Alpha', 'Beta', 'Charlie'], $names);
    }

    /**
     * Tests {@see utils::get_resolved_mappings_for_course} with mixed cover/subset in-between groups.
     *
     * Setup: B <- A (cover), C <- A (subset), D <- B, C (cover).
     * B is cover-mode -> hidden in D's resolved sources.
     * C is subset-mode -> shown in D's resolved sources.
     * D's effective sources: A (from B, which is hidden), A + C (C is shown, its source A is also resolved).
     * Deduplicated: [Alpha, Charlie].
     *
     * @covers \local_groupmerge\local\utils::get_resolved_mappings_for_course
     */
    public function test_get_resolved_mappings_mixed_cover_subset(): void {
        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course();
        $groupa = $this->getDataGenerator()->create_group(['courseid' => $course->id, 'name' => 'Alpha']);
        $groupb = $this->getDataGenerator()->create_group(['courseid' => $course->id, 'name' => 'Beta']);
        $groupc = $this->getDataGenerator()->create_group(['courseid' => $course->id, 'name' => 'Charlie']);
        $groupd = $this->getDataGenerator()->create_group(['courseid' => $course->id, 'name' => 'Delta']);

        // B <- A (cover: B's members are exactly A's members).
        utils::create_mapping($course->id, $groupb->id, [$groupa->id], group_syncer::TYPE_COVER);
        // C <- A (subset: C may have extra members beyond A).
        utils::create_mapping($course->id, $groupc->id, [$groupa->id], group_syncer::TYPE_SUBSET);
        // D <- B, C.
        utils::create_mapping($course->id, $groupd->id, [$groupb->id, $groupc->id], group_syncer::TYPE_COVER);

        $result = utils::get_resolved_mappings_for_course($course->id);

        // Find Delta in the results.
        $deltamapping = null;
        foreach ($result as $mapping) {
            if ($mapping->targetgroup->name === 'Delta') {
                $deltamapping = $mapping;
                break;
            }
        }
        $this->assertNotNull($deltamapping, 'Delta should appear in resolved mappings');
        $names = array_column($deltamapping->sourcegroups, 'name');
        // B (cover) is hidden, C (subset) is shown, A is a leaf source from both branches.
        $this->assertEquals(['Alpha', 'Charlie'], $names);
    }

    /**
     * Tests {@see utils::create_mapping} creates all records correctly.
     *
     * @covers \local_groupmerge\local\utils::create_mapping
     */
    public function test_create_mapping(): void {
        global $DB;
        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course();
        $groupa = $this->getDataGenerator()->create_group(['courseid' => $course->id]);
        $groupb = $this->getDataGenerator()->create_group(['courseid' => $course->id]);
        $groupc = $this->getDataGenerator()->create_group(['courseid' => $course->id]);

        $mappingid = utils::create_mapping(
            $course->id,
            $groupc->id,
            [$groupa->id, $groupb->id],
            group_syncer::TYPE_COVER,
            'My mapping'
        );

        // Verify mapping record.
        $mapping = $DB->get_record('local_groupmerge_mapping', ['id' => $mappingid], '*', MUST_EXIST);
        $this->assertEquals($course->id, $mapping->courseid);
        $this->assertEquals('My mapping', $mapping->name);
        $this->assertEquals(group_syncer::TYPE_COVER, (int) $mapping->type);
        $this->assertGreaterThan(0, (int) $mapping->timecreated);
        $this->assertGreaterThan(0, (int) $mapping->timemodified);

        // Verify target group is stored in mapping record.
        $this->assertEquals($groupc->id, (int) $mapping->targetgroupid);

        // Verify source group records.
        $sourcerecords = $DB->get_records('local_groupmerge_sourcegroup', ['mappingid' => $mappingid]);
        $this->assertCount(2, $sourcerecords);
        $sourcegroupids = array_column($sourcerecords, 'sourcegroupid');
        $this->assertContains((string) $groupa->id, $sourcegroupids);
        $this->assertContains((string) $groupb->id, $sourcegroupids);
    }

    /**
     * Tests {@see utils::create_mapping} uses default type when not specified.
     *
     * @covers \local_groupmerge\local\utils::create_mapping
     */
    public function test_create_mapping_default_type(): void {
        global $DB;
        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course();
        $groupa = $this->getDataGenerator()->create_group(['courseid' => $course->id]);
        $groupb = $this->getDataGenerator()->create_group(['courseid' => $course->id]);

        $mappingid = utils::create_mapping($course->id, $groupb->id, [$groupa->id]);

        $mapping = $DB->get_record('local_groupmerge_mapping', ['id' => $mappingid], '*', MUST_EXIST);
        $this->assertEquals(group_syncer::TYPE_SUBSET, (int) $mapping->type);
        $this->assertNull($mapping->name);
    }

    /**
     * Tests {@see utils::update_mapping} updates metadata and replaces source groups.
     *
     * @covers \local_groupmerge\local\utils::update_mapping
     */
    public function test_update_mapping(): void {
        global $DB;
        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course();
        $groupa = $this->getDataGenerator()->create_group(['courseid' => $course->id]);
        $groupb = $this->getDataGenerator()->create_group(['courseid' => $course->id]);
        $groupc = $this->getDataGenerator()->create_group(['courseid' => $course->id]);
        $groupd = $this->getDataGenerator()->create_group(['courseid' => $course->id]);

        // Create initial mapping: C <- A, B.
        $mappingid = utils::create_mapping(
            $course->id,
            $groupc->id,
            [$groupa->id, $groupb->id],
            group_syncer::TYPE_SUBSET,
            'Original'
        );

        // Update mapping: change type, name, and replace source groups with D only.
        utils::update_mapping($mappingid, [$groupd->id], group_syncer::TYPE_COVER, 'Updated');

        // Verify mapping record was updated.
        $mapping = $DB->get_record('local_groupmerge_mapping', ['id' => $mappingid], '*', MUST_EXIST);
        $this->assertEquals('Updated', $mapping->name);
        $this->assertEquals(group_syncer::TYPE_COVER, (int) $mapping->type);

        // Verify target group is unchanged.
        $mapping = $DB->get_record('local_groupmerge_mapping', ['id' => $mappingid], '*', MUST_EXIST);
        $this->assertEquals($groupc->id, (int) $mapping->targetgroupid);

        // Verify source groups were replaced.
        $sourcerecords = $DB->get_records('local_groupmerge_sourcegroup', ['mappingid' => $mappingid]);
        $this->assertCount(1, $sourcerecords);
        $sourcerecord = reset($sourcerecords);
        $this->assertEquals($groupd->id, $sourcerecord->sourcegroupid);
    }

    /**
     * Tests {@see utils::update_mapping} can set name to null.
     *
     * @covers \local_groupmerge\local\utils::update_mapping
     */
    public function test_update_mapping_null_name(): void {
        global $DB;
        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course();
        $groupa = $this->getDataGenerator()->create_group(['courseid' => $course->id]);
        $groupb = $this->getDataGenerator()->create_group(['courseid' => $course->id]);

        $mappingid = utils::create_mapping($course->id, $groupb->id, [$groupa->id], group_syncer::TYPE_COVER, 'Named');

        utils::update_mapping($mappingid, [$groupa->id], group_syncer::TYPE_COVER);

        $mapping = $DB->get_record('local_groupmerge_mapping', ['id' => $mappingid], '*', MUST_EXIST);
        $this->assertNull($mapping->name);
    }

    /**
     * Tests {@see utils::create_mapping} throws exception when target group belongs to a different course.
     *
     * @covers \local_groupmerge\local\utils::create_mapping
     */
    public function test_create_mapping_target_wrong_course(): void {
        $this->resetAfterTest();

        $course1 = $this->getDataGenerator()->create_course();
        $course2 = $this->getDataGenerator()->create_course();
        $source = $this->getDataGenerator()->create_group(['courseid' => $course1->id]);
        $target = $this->getDataGenerator()->create_group(['courseid' => $course2->id]);

        $this->expectException(\coding_exception::class);
        utils::create_mapping($course1->id, $target->id, [$source->id]);
    }

    /**
     * Tests {@see utils::create_mapping} throws exception when a source group belongs to a different course.
     *
     * @covers \local_groupmerge\local\utils::create_mapping
     */
    public function test_create_mapping_source_wrong_course(): void {
        $this->resetAfterTest();

        $course1 = $this->getDataGenerator()->create_course();
        $course2 = $this->getDataGenerator()->create_course();
        $target = $this->getDataGenerator()->create_group(['courseid' => $course1->id]);
        $goodsource = $this->getDataGenerator()->create_group(['courseid' => $course1->id]);
        $badsource = $this->getDataGenerator()->create_group(['courseid' => $course2->id]);

        $this->expectException(\coding_exception::class);
        utils::create_mapping($course1->id, $target->id, [$goodsource->id, $badsource->id]);
    }

    /**
     * Tests {@see utils::update_mapping} throws exception when a source group belongs to a different course.
     *
     * @covers \local_groupmerge\local\utils::update_mapping
     */
    public function test_update_mapping_source_wrong_course(): void {
        $this->resetAfterTest();

        $course1 = $this->getDataGenerator()->create_course();
        $course2 = $this->getDataGenerator()->create_course();
        $groupa = $this->getDataGenerator()->create_group(['courseid' => $course1->id]);
        $groupb = $this->getDataGenerator()->create_group(['courseid' => $course1->id]);
        $foreigngroup = $this->getDataGenerator()->create_group(['courseid' => $course2->id]);

        $mappingid = utils::create_mapping($course1->id, $groupb->id, [$groupa->id]);

        $this->expectException(\coding_exception::class);
        utils::update_mapping($mappingid, [$foreigngroup->id], group_syncer::TYPE_COVER);
    }

    /**
     * Tests {@see utils::delete_mapping} removes all related records.
     *
     * @covers \local_groupmerge\local\utils::delete_mapping
     */
    public function test_delete_mapping(): void {
        global $DB;
        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course();
        $groupa = $this->getDataGenerator()->create_group(['courseid' => $course->id]);
        $groupb = $this->getDataGenerator()->create_group(['courseid' => $course->id]);
        $groupc = $this->getDataGenerator()->create_group(['courseid' => $course->id]);

        $mappingid = utils::create_mapping(
            $course->id,
            $groupc->id,
            [$groupa->id, $groupb->id],
            group_syncer::TYPE_COVER,
            'Test'
        );

        $this->assertTrue($DB->record_exists('local_groupmerge_mapping', ['id' => $mappingid]));

        utils::delete_mapping($mappingid);

        $this->assertFalse($DB->record_exists('local_groupmerge_mapping', ['id' => $mappingid]));
        $this->assertFalse($DB->record_exists('local_groupmerge_sourcegroup', ['mappingid' => $mappingid]));
    }


    /**
     * Tests {@see utils::get_orphaned_mapping_ids} returns empty array when all mappings have source groups.
     *
     * @covers \local_groupmerge\local\utils::get_orphaned_mapping_ids
     */
    public function test_get_orphaned_mapping_ids_none(): void {
        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course();
        $groupa = $this->getDataGenerator()->create_group(['courseid' => $course->id]);
        $groupb = $this->getDataGenerator()->create_group(['courseid' => $course->id]);

        utils::create_mapping($course->id, $groupb->id, [$groupa->id], group_syncer::TYPE_COVER);

        $this->assertEmpty(utils::get_orphaned_mapping_ids());
    }

    /**
     * Tests {@see utils::get_orphaned_mapping_ids} detects a mapping whose source groups have been removed.
     *
     * @covers \local_groupmerge\local\utils::get_orphaned_mapping_ids
     */
    public function test_get_orphaned_mapping_ids_with_orphan(): void {
        global $DB;
        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course();
        $groupa = $this->getDataGenerator()->create_group(['courseid' => $course->id]);
        $groupb = $this->getDataGenerator()->create_group(['courseid' => $course->id]);
        $groupc = $this->getDataGenerator()->create_group(['courseid' => $course->id]);
        $groupd = $this->getDataGenerator()->create_group(['courseid' => $course->id]);

        // Mapping 1: B <- A (will become orphaned).
        $mappingid1 = utils::create_mapping($course->id, $groupb->id, [$groupa->id], group_syncer::TYPE_COVER);
        // Mapping 2: D <- C (stays intact).
        $mappingid2 = utils::create_mapping($course->id, $groupd->id, [$groupc->id], group_syncer::TYPE_COVER);

        // Manually remove all source groups of mapping 1 to simulate an orphan.
        $DB->delete_records('local_groupmerge_sourcegroup', ['mappingid' => $mappingid1]);

        $orphanedids = utils::get_orphaned_mapping_ids();

        $this->assertCount(1, $orphanedids);
        $this->assertEquals($mappingid1, $orphanedids[0]);
    }

    /**
     * Tests {@see utils::get_orphaned_mapping_ids} returns empty array when no mappings exist at all.
     *
     * @covers \local_groupmerge\local\utils::get_orphaned_mapping_ids
     */
    public function test_get_orphaned_mapping_ids_no_mappings(): void {
        $this->resetAfterTest();

        $this->assertEmpty(utils::get_orphaned_mapping_ids());
    }
}
