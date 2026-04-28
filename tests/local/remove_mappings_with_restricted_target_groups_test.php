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

/**
 * Unit tests for utils::get_mapping_ids_with_restricted_target_groups.
 *
 * @package    local_groupmerge
 * @copyright  2026 ISB Bayern
 * @author     Philipp Memmel
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_groupmerge\local\utils::get_mapping_ids_with_restricted_target_groups
 */
final class remove_mappings_with_restricted_target_groups_test extends \advanced_testcase {
    /**
     * Test that no mappings are removed when the hook returns no restricted groups.
     */
    public function test_no_restricted_groups_keeps_all_mappings(): void {
        $this->resetAfterTest();

        $data = $this->getDataGenerator()->get_plugin_generator('local_groupmerge')->create_course_with_groups(3);
        $course = $data['course'];
        $groups = $data['groups'];

        // Create a mapping: Group 1, Group 2 -> Group 3.
        utils::create_mapping(
            $course->id,
            $groups['Group 3']->id,
            [$groups['Group 1']->id, $groups['Group 2']->id]
        );

        // Hook returns nothing restricted.
        $this->redirectHook(restrict_target_groups::class, function (restrict_target_groups $hook) {
            // No groups restricted.
        });

        $removed = utils::get_mapping_ids_with_restricted_target_groups($course->id);

        $this->assertEmpty($removed);
        // Mapping should still exist.
        $records = utils::get_mapping_records_for_course($course->id);
        $this->assertNotEmpty($records);
    }

    /**
     * Test that a mapping is removed when its target group is restricted.
     */
    public function test_restricted_target_group_removes_mapping(): void {
        global $DB;
        $this->resetAfterTest();

        $data = $this->getDataGenerator()->get_plugin_generator('local_groupmerge')->create_course_with_groups(3);
        $course = $data['course'];
        $groups = $data['groups'];

        // Create a mapping: Group 1, Group 2 -> Group 3.
        $mappingid = utils::create_mapping(
            $course->id,
            $groups['Group 3']->id,
            [$groups['Group 1']->id, $groups['Group 2']->id]
        );

        // Hook restricts Group 3.
        $restrictedgroupid = $groups['Group 3']->id;
        $this->redirectHook(restrict_target_groups::class, function (restrict_target_groups $hook) use ($restrictedgroupid) {
            $hook->add_unallowed_groupid($restrictedgroupid, 'blocked by test');
        });

        $removed = utils::get_mapping_ids_with_restricted_target_groups($course->id);

        $this->assertCount(1, $removed);
        $this->assertContains($mappingid, $removed);

        // Now delete the restricted mappings.
        foreach ($removed as $mid) {
            utils::delete_mapping($mid);
        }

        // Mapping and all associated records should be gone.
        $this->assertFalse($DB->record_exists('local_groupmerge_mapping', ['id' => $mappingid]));
        $this->assertFalse($DB->record_exists('local_groupmerge_targetgroup', ['mappingid' => $mappingid]));
        $this->assertFalse($DB->record_exists('local_groupmerge_sourcegroup', ['mappingid' => $mappingid]));
    }

    /**
     * Test that only the affected mapping is removed and other mappings stay.
     */
    public function test_only_affected_mapping_is_removed(): void {
        global $DB;
        $this->resetAfterTest();

        $data = $this->getDataGenerator()->get_plugin_generator('local_groupmerge')->create_course_with_groups(5);
        $course = $data['course'];
        $groups = $data['groups'];

        // Mapping 1: Group 1 -> Group 3 (will be restricted).
        $mappingid1 = utils::create_mapping(
            $course->id,
            $groups['Group 3']->id,
            [$groups['Group 1']->id]
        );

        // Mapping 2: Group 2 -> Group 4 (should remain).
        $mappingid2 = utils::create_mapping(
            $course->id,
            $groups['Group 4']->id,
            [$groups['Group 2']->id]
        );

        // Hook restricts only Group 3.
        $restrictedgroupid = $groups['Group 3']->id;
        $this->redirectHook(restrict_target_groups::class, function (restrict_target_groups $hook) use ($restrictedgroupid) {
            $hook->add_unallowed_groupid($restrictedgroupid, 'blocked');
        });

        $removed = utils::get_mapping_ids_with_restricted_target_groups($course->id);

        $this->assertCount(1, $removed);
        $this->assertContains($mappingid1, $removed);

        // Now delete the restricted mappings.
        foreach ($removed as $mid) {
            utils::delete_mapping($mid);
        }

        // Mapping 1 should be gone.
        $this->assertFalse($DB->record_exists('local_groupmerge_mapping', ['id' => $mappingid1]));

        // Mapping 2 should still exist.
        $this->assertTrue($DB->record_exists('local_groupmerge_mapping', ['id' => $mappingid2]));
        $this->assertTrue($DB->record_exists('local_groupmerge_targetgroup', ['mappingid' => $mappingid2]));
        $this->assertTrue($DB->record_exists('local_groupmerge_sourcegroup', ['mappingid' => $mappingid2]));
    }

    /**
     * Test that multiple restricted target groups remove all affected mappings.
     */
    public function test_multiple_restricted_targets_removes_all_affected(): void {
        global $DB;
        $this->resetAfterTest();

        $data = $this->getDataGenerator()->get_plugin_generator('local_groupmerge')->create_course_with_groups(5);
        $course = $data['course'];
        $groups = $data['groups'];

        // Mapping 1: Group 1 -> Group 3.
        $mappingid1 = utils::create_mapping(
            $course->id,
            $groups['Group 3']->id,
            [$groups['Group 1']->id]
        );

        // Mapping 2: Group 2 -> Group 4.
        $mappingid2 = utils::create_mapping(
            $course->id,
            $groups['Group 4']->id,
            [$groups['Group 2']->id]
        );

        // Mapping 3: Group 1 -> Group 5 (should remain).
        $mappingid3 = utils::create_mapping(
            $course->id,
            $groups['Group 5']->id,
            [$groups['Group 1']->id]
        );

        // Hook restricts Group 3 and Group 4.
        $restrictedid3 = $groups['Group 3']->id;
        $restrictedid4 = $groups['Group 4']->id;
        $this->redirectHook(
            restrict_target_groups::class,
            function (restrict_target_groups $hook) use ($restrictedid3, $restrictedid4) {
                $hook->add_unallowed_groupid($restrictedid3, 'reason A');
                $hook->add_unallowed_groupid($restrictedid4, 'reason B');
            }
        );

        $removed = utils::get_mapping_ids_with_restricted_target_groups($course->id);

        $this->assertCount(2, $removed);
        $this->assertContains($mappingid1, $removed);
        $this->assertContains($mappingid2, $removed);

        // Now delete the restricted mappings.
        foreach ($removed as $mid) {
            utils::delete_mapping($mid);
        }

        $this->assertFalse($DB->record_exists('local_groupmerge_mapping', ['id' => $mappingid1]));
        $this->assertFalse($DB->record_exists('local_groupmerge_mapping', ['id' => $mappingid2]));

        // Mapping 3 should still exist.
        $this->assertTrue($DB->record_exists('local_groupmerge_mapping', ['id' => $mappingid3]));
    }

    /**
     * Test that calling the method on a course with no mappings returns an empty array.
     */
    public function test_no_mappings_returns_empty(): void {
        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course();

        // Hook restricts something, but there are no mappings.
        $this->redirectHook(restrict_target_groups::class, function (restrict_target_groups $hook) {
            $hook->add_unallowed_groupid(999, 'does not matter');
        });

        $removed = utils::get_mapping_ids_with_restricted_target_groups($course->id);

        $this->assertEmpty($removed);
    }

    /**
     * Test that restricting a group that is only a source group does not remove any mapping.
     */
    public function test_restricting_source_group_does_not_remove_mapping(): void {
        global $DB;
        $this->resetAfterTest();

        $data = $this->getDataGenerator()->get_plugin_generator('local_groupmerge')->create_course_with_groups(3);
        $course = $data['course'];
        $groups = $data['groups'];

        // Mapping: Group 1 → Group 3 (Group 1 is source, Group 3 is target).
        $mappingid = utils::create_mapping(
            $course->id,
            $groups['Group 3']->id,
            [$groups['Group 1']->id]
        );

        // Hook restricts Group 1 (which is a source, not a target).
        $sourcegroupid = $groups['Group 1']->id;
        $this->redirectHook(restrict_target_groups::class, function (restrict_target_groups $hook) use ($sourcegroupid) {
            $hook->add_unallowed_groupid($sourcegroupid, 'restricted source');
        });

        $removed = utils::get_mapping_ids_with_restricted_target_groups($course->id);

        $this->assertEmpty($removed);
        $this->assertTrue($DB->record_exists('local_groupmerge_mapping', ['id' => $mappingid]));
    }
}
