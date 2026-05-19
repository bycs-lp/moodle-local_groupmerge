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

use core\event\group_deleted;
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * Unit tests for the observers class of local_groupmerge.
 *
 * @package   local_groupmerge
 * @copyright 2025 ISB Bayern
 * @author    Philipp Memmel
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(\local_groupmerge\local\observers::class)]
final class observers_test extends \advanced_testcase {
    /**
     * Tests the event handler for the {@see group_deleted} event when a source group is deleted.
     *
     * When a source group is deleted, only the sourcegroup entry is removed from the mapping.
     * The mapping itself and the remaining source groups are preserved.
     */
    public function test_group_deleted_source(): void {
        global $CFG, $DB;
        require_once($CFG->dirroot . '/group/lib.php');
        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course();
        $groupa = $this->getDataGenerator()->create_group(['courseid' => $course->id, 'name' => 'Alpha']);
        $groupb = $this->getDataGenerator()->create_group(['courseid' => $course->id, 'name' => 'Beta']);
        $groupc = $this->getDataGenerator()->create_group(['courseid' => $course->id, 'name' => 'Charlie']);

        // Create a mapping: C <- A, B.
        $mappingid = utils::create_mapping(
            $course->id,
            $groupc->id,
            [$groupa->id, $groupb->id],
            group_syncer::TYPE_COVER,
            'Test mapping'
        );

        // Verify mapping and both source group entries exist.
        $this->assertTrue($DB->record_exists('local_groupmerge_mapping', ['id' => $mappingid]));
        $this->assertEquals(2, $DB->count_records('local_groupmerge_sourcegroup', ['mappingid' => $mappingid]));

        // Delete source group A — this triggers the group_deleted event via the core API.
        groups_delete_group($groupa->id);

        // The mapping must still exist.
        $this->assertTrue($DB->record_exists('local_groupmerge_mapping', ['id' => $mappingid]));

        // Only source group B should remain.
        $sourcerecords = $DB->get_records('local_groupmerge_sourcegroup', ['mappingid' => $mappingid]);
        $this->assertCount(1, $sourcerecords);
        $remaining = reset($sourcerecords);
        $this->assertEquals($groupb->id, (int) $remaining->sourcegroupid);
    }

    /**
     * Tests the event handler for the {@see group_deleted} event when a target group is deleted.
     */
    public function test_group_deleted_target(): void {
        global $CFG, $DB;
        require_once($CFG->dirroot . '/group/lib.php');
        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course();
        $groupa = $this->getDataGenerator()->create_group(['courseid' => $course->id, 'name' => 'Alpha']);
        $groupb = $this->getDataGenerator()->create_group(['courseid' => $course->id, 'name' => 'Beta']);

        // Create a mapping: B <- A.
        $mappingid = utils::create_mapping(
            $course->id,
            $groupb->id,
            [$groupa->id],
            group_syncer::TYPE_COVER,
            'Test mapping'
        );

        $this->assertTrue($DB->record_exists('local_groupmerge_mapping', ['id' => $mappingid]));

        // Delete target group B.
        groups_delete_group($groupb->id);

        // The mapping must be fully removed.
        $this->assertFalse($DB->record_exists('local_groupmerge_mapping', ['id' => $mappingid]));
        $this->assertFalse($DB->record_exists('local_groupmerge_sourcegroup', ['mappingid' => $mappingid]));
    }

    /**
     * Tests that deleting a source group does not affect unrelated mappings.
     *
     * When the deleted source group is the only source of a mapping, that mapping is also
     * fully removed (a mapping without source groups is meaningless).
     */
    public function test_group_deleted_unrelated_mapping_preserved(): void {
        global $CFG, $DB;
        require_once($CFG->dirroot . '/group/lib.php');
        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course();
        $groupa = $this->getDataGenerator()->create_group(['courseid' => $course->id, 'name' => 'Alpha']);
        $groupb = $this->getDataGenerator()->create_group(['courseid' => $course->id, 'name' => 'Beta']);
        $groupc = $this->getDataGenerator()->create_group(['courseid' => $course->id, 'name' => 'Charlie']);
        $groupd = $this->getDataGenerator()->create_group(['courseid' => $course->id, 'name' => 'Delta']);

        // Mapping 1: B <- A (A is the only source).
        $mappingid1 = utils::create_mapping(
            $course->id,
            $groupb->id,
            [$groupa->id],
            group_syncer::TYPE_COVER
        );
        // Mapping 2: D <- C.
        $mappingid2 = utils::create_mapping(
            $course->id,
            $groupd->id,
            [$groupc->id],
            group_syncer::TYPE_COVER
        );

        // Delete source group A — mapping 1 loses its only source and must be fully removed.
        groups_delete_group($groupa->id);

        $this->assertFalse($DB->record_exists('local_groupmerge_mapping', ['id' => $mappingid1]));
        $this->assertFalse($DB->record_exists('local_groupmerge_sourcegroup', ['mappingid' => $mappingid1]));
        // Mapping 2 must be untouched.
        $this->assertTrue($DB->record_exists('local_groupmerge_mapping', ['id' => $mappingid2]));
        $this->assertCount(1, $DB->get_records('local_groupmerge_sourcegroup', ['mappingid' => $mappingid2]));
    }

    /**
     * Tests that deleting the last source group of a mapping removes the entire mapping.
     */
    public function test_group_deleted_last_source_removes_mapping(): void {
        global $CFG, $DB;
        require_once($CFG->dirroot . '/group/lib.php');
        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course();
        $groupa = $this->getDataGenerator()->create_group(['courseid' => $course->id, 'name' => 'Alpha']);
        $groupb = $this->getDataGenerator()->create_group(['courseid' => $course->id, 'name' => 'Beta']);
        $groupc = $this->getDataGenerator()->create_group(['courseid' => $course->id, 'name' => 'Charlie']);

        // Create a mapping: C <- A, B.
        $mappingid = utils::create_mapping(
            $course->id,
            $groupc->id,
            [$groupa->id, $groupb->id],
            group_syncer::TYPE_COVER,
            'Test'
        );

        // Delete source group A — mapping still has source B.
        groups_delete_group($groupa->id);
        $this->assertTrue($DB->record_exists('local_groupmerge_mapping', ['id' => $mappingid]));
        $this->assertCount(1, $DB->get_records('local_groupmerge_sourcegroup', ['mappingid' => $mappingid]));

        // Delete source group B — mapping now has no sources and must be fully removed.
        groups_delete_group($groupb->id);
        $this->assertFalse($DB->record_exists('local_groupmerge_mapping', ['id' => $mappingid]));
        $this->assertFalse($DB->record_exists('local_groupmerge_sourcegroup', ['mappingid' => $mappingid]));
    }

    /**
     * Tests the event handler for the {@see \core\event\course_deleted} event.
     *
     * When a course is deleted, all mappings for that course must be removed.
     */
    public function test_course_deleted(): void {
        global $DB;
        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course();
        $groupa = $this->getDataGenerator()->create_group(['courseid' => $course->id, 'name' => 'Alpha']);
        $groupb = $this->getDataGenerator()->create_group(['courseid' => $course->id, 'name' => 'Beta']);
        $groupc = $this->getDataGenerator()->create_group(['courseid' => $course->id, 'name' => 'Charlie']);

        // Create two mappings in the course.
        $mappingid1 = utils::create_mapping(
            $course->id,
            $groupb->id,
            [$groupa->id],
            group_syncer::TYPE_COVER,
            'Mapping 1'
        );
        $mappingid2 = utils::create_mapping(
            $course->id,
            $groupc->id,
            [$groupa->id],
            group_syncer::TYPE_SUBSET,
            'Mapping 2'
        );

        $this->assertTrue($DB->record_exists('local_groupmerge_mapping', ['id' => $mappingid1]));
        $this->assertTrue($DB->record_exists('local_groupmerge_mapping', ['id' => $mappingid2]));

        // Delete the course — this triggers the course_deleted event via the core API.
        delete_course($course->id, false);

        // All mappings for the course must be removed.
        $this->assertFalse($DB->record_exists('local_groupmerge_mapping', ['id' => $mappingid1]));
        $this->assertFalse($DB->record_exists('local_groupmerge_sourcegroup', ['mappingid' => $mappingid1]));
        $this->assertFalse($DB->record_exists('local_groupmerge_mapping', ['id' => $mappingid2]));
        $this->assertFalse($DB->record_exists('local_groupmerge_sourcegroup', ['mappingid' => $mappingid2]));
    }

    /**
     * Tests that deleting a course does not affect mappings in other courses.
     */
    public function test_course_deleted_other_course_preserved(): void {
        global $DB;
        $this->resetAfterTest();

        $course1 = $this->getDataGenerator()->create_course();
        $course2 = $this->getDataGenerator()->create_course();
        $group1a = $this->getDataGenerator()->create_group(['courseid' => $course1->id]);
        $group1b = $this->getDataGenerator()->create_group(['courseid' => $course1->id]);
        $group2a = $this->getDataGenerator()->create_group(['courseid' => $course2->id]);
        $group2b = $this->getDataGenerator()->create_group(['courseid' => $course2->id]);

        $mappingid1 = utils::create_mapping($course1->id, $group1b->id, [$group1a->id], group_syncer::TYPE_COVER);
        $mappingid2 = utils::create_mapping($course2->id, $group2b->id, [$group2a->id], group_syncer::TYPE_COVER);

        delete_course($course1->id, false);

        $this->assertFalse($DB->record_exists('local_groupmerge_mapping', ['id' => $mappingid1]));
        $this->assertTrue($DB->record_exists('local_groupmerge_mapping', ['id' => $mappingid2]));
    }

    /**
     * Tests the event handler for the {@see \core\event\group_member_added} event.
     *
     * When a user is added to a source group, the user must be propagated to the target group.
     */
    public function test_group_member_added(): void {
        global $CFG;
        require_once($CFG->dirroot . '/group/lib.php');
        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course();
        $sourcegroup = $this->getDataGenerator()->create_group(['courseid' => $course->id, 'name' => 'Source']);
        $targetgroup = $this->getDataGenerator()->create_group(['courseid' => $course->id, 'name' => 'Target']);
        $user = $this->getDataGenerator()->create_and_enrol($course, 'student');

        utils::create_mapping(
            $course->id,
            $targetgroup->id,
            [$sourcegroup->id],
            group_syncer::TYPE_COVER,
            'Test'
        );

        // User is not yet in the target group.
        $this->assertFalse(groups_is_member($targetgroup->id, $user->id));

        // Adding the user to the source group triggers group_member_added.
        groups_add_member($sourcegroup->id, $user->id);

        // User must now be in the target group.
        $this->assertTrue(groups_is_member($targetgroup->id, $user->id));
    }

    /**
     * Tests that adding a user to a source group propagates to target in subset mode as well.
     */
    public function test_group_member_added_subset(): void {
        global $CFG;
        require_once($CFG->dirroot . '/group/lib.php');
        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course();
        $sourcegroup = $this->getDataGenerator()->create_group(['courseid' => $course->id, 'name' => 'Source']);
        $targetgroup = $this->getDataGenerator()->create_group(['courseid' => $course->id, 'name' => 'Target']);
        $user = $this->getDataGenerator()->create_and_enrol($course, 'student');

        utils::create_mapping(
            $course->id,
            $targetgroup->id,
            [$sourcegroup->id],
            group_syncer::TYPE_SUBSET,
            'Test'
        );

        groups_add_member($sourcegroup->id, $user->id);

        $this->assertTrue(groups_is_member($targetgroup->id, $user->id));
    }

    /**
     * Tests that adding a user to a group without any mapping does not cause errors.
     */
    public function test_group_member_added_no_mapping(): void {
        global $CFG;
        require_once($CFG->dirroot . '/group/lib.php');
        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course();
        $group = $this->getDataGenerator()->create_group(['courseid' => $course->id, 'name' => 'Standalone']);
        $user = $this->getDataGenerator()->create_and_enrol($course, 'student');

        // Should not throw — no mappings exist.
        groups_add_member($group->id, $user->id);

        $this->assertTrue(groups_is_member($group->id, $user->id));
    }

    /**
     * Tests the event handler for the {@see \core\event\group_member_removed} event in cover mode.
     *
     * In cover mode, removing a user from a source group must also remove the user from the target group.
     */
    public function test_group_member_removed_cover(): void {
        global $CFG;
        require_once($CFG->dirroot . '/group/lib.php');
        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course();
        $sourcegroup = $this->getDataGenerator()->create_group(['courseid' => $course->id, 'name' => 'Source']);
        $targetgroup = $this->getDataGenerator()->create_group(['courseid' => $course->id, 'name' => 'Target']);
        $user = $this->getDataGenerator()->create_and_enrol($course, 'student');

        utils::create_mapping(
            $course->id,
            $targetgroup->id,
            [$sourcegroup->id],
            group_syncer::TYPE_COVER,
            'Cover mapping'
        );

        // Add user to source group — observer propagates to target.
        groups_add_member($sourcegroup->id, $user->id);
        $this->assertTrue(groups_is_member($targetgroup->id, $user->id));

        // Remove user from source group — observer must propagate removal to target.
        groups_remove_member($sourcegroup->id, $user->id);
        $this->assertFalse(groups_is_member($targetgroup->id, $user->id));
    }

    /**
     * Tests the event handler for the {@see \core\event\group_member_removed} event in subset mode.
     *
     * In subset mode, removing a user from a source group must NOT remove the user from the target group.
     */
    public function test_group_member_removed_subset(): void {
        global $CFG;
        require_once($CFG->dirroot . '/group/lib.php');
        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course();
        $sourcegroup = $this->getDataGenerator()->create_group(['courseid' => $course->id, 'name' => 'Source']);
        $targetgroup = $this->getDataGenerator()->create_group(['courseid' => $course->id, 'name' => 'Target']);
        $user = $this->getDataGenerator()->create_and_enrol($course, 'student');

        utils::create_mapping(
            $course->id,
            $targetgroup->id,
            [$sourcegroup->id],
            group_syncer::TYPE_SUBSET,
            'Subset mapping'
        );

        // Add user to source group — observer propagates to target.
        groups_add_member($sourcegroup->id, $user->id);
        $this->assertTrue(groups_is_member($targetgroup->id, $user->id));

        // Remove user from source group — in subset mode, user must STAY in target group.
        groups_remove_member($sourcegroup->id, $user->id);
        $this->assertTrue(groups_is_member($targetgroup->id, $user->id));
    }

    /**
     * Tests that removing a user from a group without any mapping does not cause errors.
     */
    public function test_group_member_removed_no_mapping(): void {
        global $CFG;
        require_once($CFG->dirroot . '/group/lib.php');
        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course();
        $group = $this->getDataGenerator()->create_group(['courseid' => $course->id, 'name' => 'Standalone']);
        $user = $this->getDataGenerator()->create_and_enrol($course, 'student');

        groups_add_member($group->id, $user->id);
        $this->assertTrue(groups_is_member($group->id, $user->id));

        // Should not throw — no mappings exist.
        groups_remove_member($group->id, $user->id);
        $this->assertFalse(groups_is_member($group->id, $user->id));
    }

    /**
     * Tests that manually removing a user from a target group re-adds the user
     * if they are still a member of a source group (cover mode).
     *
     * The mapping rule takes precedence over manual removal. A notification must be shown.
     */
    public function test_group_member_removed_from_target_readded_cover(): void {
        global $CFG, $SESSION;
        require_once($CFG->dirroot . '/group/lib.php');
        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course();
        $sourcegroup = $this->getDataGenerator()->create_group(['courseid' => $course->id, 'name' => 'Source group name']);
        $targetgroup = $this->getDataGenerator()->create_group(['courseid' => $course->id, 'name' => 'Target group name']);
        $user = $this->getDataGenerator()->create_and_enrol($course, 'student');

        utils::create_mapping(
            $course->id,
            $targetgroup->id,
            [$sourcegroup->id],
            group_syncer::TYPE_COVER,
            'Cover mapping'
        );

        // Add user to source group — observer propagates to target.
        groups_add_member($sourcegroup->id, $user->id);
        $this->assertTrue(groups_is_member($targetgroup->id, $user->id));

        // Clear any existing notifications.
        $SESSION->notifications = [];

        // Manually remove user from the target group — user is still in source, so must be re-added.
        groups_remove_member($targetgroup->id, $user->id);
        $this->assertTrue(groups_is_member($targetgroup->id, $user->id));

        // A notification must have been shown to the user.
        $this->assertNotEmpty($SESSION->notifications);
        $notification = reset($SESSION->notifications);
        $this->assertStringContainsString('Target group name', $notification->message);
        $this->assertStringContainsString('groupmerge_config.php', $notification->message);
    }

    /**
     * Tests that manually removing a user from a target group re-adds the user
     * if they are still a member of a source group (subset mode).
     */
    public function test_group_member_removed_from_target_readded_subset(): void {
        global $CFG, $SESSION;
        require_once($CFG->dirroot . '/group/lib.php');
        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course();
        $sourcegroup = $this->getDataGenerator()->create_group(['courseid' => $course->id, 'name' => 'Source']);
        $targetgroup = $this->getDataGenerator()->create_group(['courseid' => $course->id, 'name' => 'Target']);
        $user = $this->getDataGenerator()->create_and_enrol($course, 'student');

        utils::create_mapping(
            $course->id,
            $targetgroup->id,
            [$sourcegroup->id],
            group_syncer::TYPE_SUBSET,
            'Subset mapping'
        );

        // Add user to source group — observer propagates to target.
        groups_add_member($sourcegroup->id, $user->id);
        $this->assertTrue(groups_is_member($targetgroup->id, $user->id));

        // Clear any existing notifications.
        $SESSION->notifications = [];

        // Manually remove user from the target group — user is still in source, so must be re-added.
        groups_remove_member($targetgroup->id, $user->id);
        $this->assertTrue(groups_is_member($targetgroup->id, $user->id));

        // A notification must have been shown to the user.
        $this->assertNotEmpty($SESSION->notifications);
        $notification = reset($SESSION->notifications);
        $this->assertStringContainsString('Target', $notification->message);
        $this->assertStringContainsString('groupmerge_config.php', $notification->message);
    }

    /**
     * Tests that removing a user from a target group does NOT re-add the user
     * if they are not a member of any source group.
     */
    public function test_group_member_removed_from_target_not_in_source(): void {
        global $CFG;
        require_once($CFG->dirroot . '/group/lib.php');
        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course();
        $sourcegroup = $this->getDataGenerator()->create_group(['courseid' => $course->id, 'name' => 'Source']);
        $targetgroup = $this->getDataGenerator()->create_group(['courseid' => $course->id, 'name' => 'Target']);
        $user = $this->getDataGenerator()->create_and_enrol($course, 'student');

        utils::create_mapping(
            $course->id,
            $targetgroup->id,
            [$sourcegroup->id],
            group_syncer::TYPE_COVER,
            'Cover mapping'
        );

        // Manually add user directly to the target group (not via source).
        groups_add_member($targetgroup->id, $user->id);
        $this->assertTrue(groups_is_member($targetgroup->id, $user->id));

        // Remove user from target — user is NOT in source, so must stay removed.
        groups_remove_member($targetgroup->id, $user->id);
        $this->assertFalse(groups_is_member($targetgroup->id, $user->id));
    }

    /**
     * Tests that removing a user from one source group does NOT remove the user from the target group
     * when the user is still a member of another source group of the same cover-mode mapping.
     *
     * Regression test for Bug 1.1: Cover-mode removal was too aggressive — it did not check
     * whether the user was still in another source group of the same mapping.
     */
    public function test_group_member_removed_cover_still_in_other_source(): void {
        global $CFG;
        require_once($CFG->dirroot . '/group/lib.php');
        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course();
        $sourcegroup1 = $this->getDataGenerator()->create_group(['courseid' => $course->id, 'name' => 'Source 1']);
        $sourcegroup2 = $this->getDataGenerator()->create_group(['courseid' => $course->id, 'name' => 'Source 2']);
        $targetgroup = $this->getDataGenerator()->create_group(['courseid' => $course->id, 'name' => 'Target']);
        $user = $this->getDataGenerator()->create_and_enrol($course, 'student');

        utils::create_mapping(
            $course->id,
            $targetgroup->id,
            [$sourcegroup1->id, $sourcegroup2->id],
            group_syncer::TYPE_COVER,
            'Cover mapping with two sources'
        );

        // Add user to both source groups — observer propagates to target.
        groups_add_member($sourcegroup1->id, $user->id);
        groups_add_member($sourcegroup2->id, $user->id);
        $this->assertTrue(groups_is_member($targetgroup->id, $user->id));

        // Remove user from source group 1 — user is still in source group 2, so must STAY in target.
        groups_remove_member($sourcegroup1->id, $user->id);
        $this->assertTrue(
            groups_is_member($targetgroup->id, $user->id),
            'User should remain in target group because they are still in another source group of the same mapping.'
        );

        // Now remove user from source group 2 as well — user is in no source group, so must be removed from target.
        groups_remove_member($sourcegroup2->id, $user->id);
        $this->assertFalse(
            groups_is_member($targetgroup->id, $user->id),
            'User should be removed from target group after being removed from all source groups.'
        );
    }
}
