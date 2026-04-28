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

namespace local_groupmerge\form;

use local_groupmerge\hook\restrict_target_groups;
use local_groupmerge\local\utils;

/**
 * Unit tests for the mapping_form validation logic.
 *
 * @package    local_groupmerge
 * @copyright  2026 ISB Bayern
 * @author     Philipp Memmel
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_groupmerge\form\mapping_form::validation
 */
final class mapping_form_test extends \advanced_testcase {
    /**
     * Helper: create a mapping_form instance for the given course and call validation().
     *
     * Uses the $ajaxformdata constructor parameter to pass form data cleanly,
     * avoiding direct $_POST manipulation.
     *
     * @param array $data The form data to validate.
     * @return array The validation errors.
     */
    private function run_validation(array $data): array {
        $form = new mapping_form(null, null, 'post', '', [], true, $data);
        return $form->validation($data, []);
    }

    /**
     * Test that a valid new mapping passes validation without errors.
     */
    public function test_valid_mapping_accepted(): void {
        $this->resetAfterTest();

        $data = $this->getDataGenerator()->get_plugin_generator('local_groupmerge')->create_course_with_groups(3);
        $course = $data['course'];
        $groups = $data['groups'];

        $user = $this->getDataGenerator()->create_and_enrol($course, 'editingteacher');
        $this->setUser($user);

        $errors = $this->run_validation([
            'courseid' => $course->id,
            'mappingid' => 0,
            'targetgroupid' => $groups['Group 3']->id,
            'sourcegroupids' => [$groups['Group 1']->id, $groups['Group 2']->id],
        ]);

        $this->assertEmpty($errors);
    }

    /**
     * Test that the target group must not appear in the source groups.
     */
    public function test_validation_target_in_source_rejected(): void {
        $this->resetAfterTest();

        $data = $this->getDataGenerator()->get_plugin_generator('local_groupmerge')->create_course_with_groups(3);
        $course = $data['course'];
        $groups = $data['groups'];

        $user = $this->getDataGenerator()->create_and_enrol($course, 'editingteacher');
        $this->setUser($user);

        $errors = $this->run_validation([
            'courseid' => $course->id,
            'mappingid' => 0,
            'targetgroupid' => $groups['Group 1']->id,
            'sourcegroupids' => [$groups['Group 1']->id, $groups['Group 2']->id],
        ]);

        $this->assertArrayHasKey('sourcegroupids', $errors);
        $this->assertStringContainsString(
            get_string('error_targetinsource', 'local_groupmerge'),
            $errors['sourcegroupids']
        );
    }

    /**
     * Test that a target group that already has a mapping is rejected in add mode.
     */
    public function test_validation_duplicate_target_rejected(): void {
        $this->resetAfterTest();

        $data = $this->getDataGenerator()->get_plugin_generator('local_groupmerge')->create_course_with_groups(4);
        $course = $data['course'];
        $groups = $data['groups'];

        // Create an existing mapping: Group 1 -> Group 3.
        utils::create_mapping(
            $course->id,
            $groups['Group 3']->id,
            [$groups['Group 1']->id]
        );

        $user = $this->getDataGenerator()->create_and_enrol($course, 'editingteacher');
        $this->setUser($user);

        // Try to create a new mapping with Group 3 as target again.
        $errors = $this->run_validation([
            'courseid' => $course->id,
            'mappingid' => 0,
            'targetgroupid' => $groups['Group 3']->id,
            'sourcegroupids' => [$groups['Group 2']->id],
        ]);

        $this->assertArrayHasKey('targetgroupid', $errors);
        $this->assertStringContainsString(
            get_string('error_targetalreadylinked', 'local_groupmerge'),
            $errors['targetgroupid']
        );
    }

    /**
     * Test that a circular dependency is detected and rejected.
     */
    public function test_validation_circular_dependency_rejected(): void {
        $this->resetAfterTest();

        $data = $this->getDataGenerator()->get_plugin_generator('local_groupmerge')->create_course_with_groups(3);
        $course = $data['course'];
        $groups = $data['groups'];

        // Create mapping: Group 1 -> Group 2.
        utils::create_mapping(
            $course->id,
            $groups['Group 2']->id,
            [$groups['Group 1']->id]
        );

        $user = $this->getDataGenerator()->create_and_enrol($course, 'editingteacher');
        $this->setUser($user);

        // Try to create mapping: Group 2 -> Group 1 (would create a cycle: 1->2->1).
        $errors = $this->run_validation([
            'courseid' => $course->id,
            'mappingid' => 0,
            'targetgroupid' => $groups['Group 1']->id,
            'sourcegroupids' => [$groups['Group 2']->id],
        ]);

        $this->assertArrayHasKey('sourcegroupids', $errors);
        $this->assertStringContainsString(
            get_string('error_circular_link', 'local_groupmerge'),
            $errors['sourcegroupids']
        );
    }

    /**
     * Test that a hook-restricted target group is rejected in add mode.
     */
    public function test_validation_restricted_target_rejected(): void {
        $this->resetAfterTest();

        $data = $this->getDataGenerator()->get_plugin_generator('local_groupmerge')->create_course_with_groups(3);
        $course = $data['course'];
        $groups = $data['groups'];

        $user = $this->getDataGenerator()->create_and_enrol($course, 'editingteacher');
        $this->setUser($user);

        // Hook restricts Group 3.
        $restrictedid = $groups['Group 3']->id;
        $this->redirectHook(restrict_target_groups::class, function (restrict_target_groups $hook) use ($restrictedid) {
            $hook->add_unallowed_groupid($restrictedid, 'reserved by test');
        });

        $errors = $this->run_validation([
            'courseid' => $course->id,
            'mappingid' => 0,
            'targetgroupid' => $groups['Group 3']->id,
            'sourcegroupids' => [$groups['Group 1']->id],
        ]);

        $this->assertArrayHasKey('targetgroupid', $errors);
        $this->assertStringContainsString('reserved by test', $errors['targetgroupid']);
    }

    /**
     * Test that empty source groups are rejected.
     */
    public function test_validation_empty_sourcegroupids_rejected(): void {
        $this->resetAfterTest();

        $data = $this->getDataGenerator()->get_plugin_generator('local_groupmerge')->create_course_with_groups(2);
        $course = $data['course'];
        $groups = $data['groups'];

        $user = $this->getDataGenerator()->create_and_enrol($course, 'editingteacher');
        $this->setUser($user);

        $errors = $this->run_validation([
            'courseid' => $course->id,
            'mappingid' => 0,
            'targetgroupid' => $groups['Group 2']->id,
            'sourcegroupids' => [],
        ]);

        $this->assertArrayHasKey('sourcegroupids', $errors);
    }

    /**
     * Test that editing an existing mapping with a valid change passes validation.
     */
    public function test_validation_edit_mode_valid_change_accepted(): void {
        $this->resetAfterTest();

        $data = $this->getDataGenerator()->get_plugin_generator('local_groupmerge')->create_course_with_groups(4);
        $course = $data['course'];
        $groups = $data['groups'];

        // Create mapping: Group 1 -> Group 3.
        $mappingid = utils::create_mapping(
            $course->id,
            $groups['Group 3']->id,
            [$groups['Group 1']->id]
        );

        $user = $this->getDataGenerator()->create_and_enrol($course, 'editingteacher');
        $this->setUser($user);

        // Edit the mapping: change source to Group 2 (target stays Group 3).
        $errors = $this->run_validation([
            'courseid' => $course->id,
            'mappingid' => $mappingid,
            'targetgroupid' => $groups['Group 3']->id,
            'sourcegroupids' => [$groups['Group 2']->id],
        ]);

        $this->assertEmpty($errors);
    }
}
