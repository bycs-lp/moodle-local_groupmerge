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

namespace local_groupmerge\external;

use local_groupmerge\local\group_syncer;
use local_groupmerge\local\utils;

/**
 * Unit tests for the delete_mapping external function.
 *
 * @package    local_groupmerge
 * @copyright  2026 ISB Bayern
 * @author     Philipp Memmel
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_groupmerge\external\delete_mapping
 */
final class delete_mapping_test extends \advanced_testcase {
    /**
     * Tests that a valid mapping is successfully deleted via the external API.
     *
     * @covers \local_groupmerge\external\delete_mapping::execute
     */
    public function test_delete_mapping_success(): void {
        global $DB;
        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course();
        $user = $this->getDataGenerator()->create_and_enrol($course, 'editingteacher');
        $groupa = $this->getDataGenerator()->create_group(['courseid' => $course->id]);
        $groupb = $this->getDataGenerator()->create_group(['courseid' => $course->id]);

        $mappingid = utils::create_mapping(
            $course->id,
            $groupb->id,
            [$groupa->id],
            group_syncer::TYPE_COVER,
            'Test mapping'
        );

        $this->assertTrue($DB->record_exists('local_groupmerge_mapping', ['id' => $mappingid]));

        // Call the external function as the editing teacher.
        $this->setUser($user);
        $result = delete_mapping::execute($mappingid);

        $this->assertTrue($result['success']);
    }

    /**
     * Tests that deleting a mapping removes all associated records
     * (mapping, targetgroup, sourcegroup).
     *
     * @covers \local_groupmerge\external\delete_mapping::execute
     */
    public function test_delete_mapping_removes_all_records(): void {
        global $DB;
        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course();
        $user = $this->getDataGenerator()->create_and_enrol($course, 'editingteacher');
        $groupa = $this->getDataGenerator()->create_group(['courseid' => $course->id]);
        $groupb = $this->getDataGenerator()->create_group(['courseid' => $course->id]);
        $groupc = $this->getDataGenerator()->create_group(['courseid' => $course->id]);

        $mappingid = utils::create_mapping(
            $course->id,
            $groupc->id,
            [$groupa->id, $groupb->id],
            group_syncer::TYPE_SUBSET,
            'Test mapping'
        );

        // Verify all records exist before deletion.
        $this->assertTrue($DB->record_exists('local_groupmerge_mapping', ['id' => $mappingid]));
        $this->assertEquals(2, $DB->count_records('local_groupmerge_sourcegroup', ['mappingid' => $mappingid]));

        $this->setUser($user);
        delete_mapping::execute($mappingid);

        // All associated records must be gone.
        $this->assertFalse($DB->record_exists('local_groupmerge_mapping', ['id' => $mappingid]));
        $this->assertFalse($DB->record_exists('local_groupmerge_sourcegroup', ['mappingid' => $mappingid]));
    }

    /**
     * Tests that deleting a non-existent mapping throws a dml_missing_record_exception.
     *
     * @covers \local_groupmerge\external\delete_mapping::execute
     */
    public function test_delete_mapping_invalid_id_throws_exception(): void {
        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course();
        $user = $this->getDataGenerator()->create_and_enrol($course, 'editingteacher');

        $this->setUser($user);
        $this->expectException(\dml_missing_record_exception::class);
        delete_mapping::execute(999999);
    }

    /**
     * Tests that a user without the manage capability cannot delete a mapping.
     *
     * @covers \local_groupmerge\external\delete_mapping::execute
     */
    public function test_delete_mapping_without_capability_throws_exception(): void {
        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course();
        $student = $this->getDataGenerator()->create_and_enrol($course, 'student');
        $groupa = $this->getDataGenerator()->create_group(['courseid' => $course->id]);
        $groupb = $this->getDataGenerator()->create_group(['courseid' => $course->id]);

        $mappingid = utils::create_mapping(
            $course->id,
            $groupb->id,
            [$groupa->id],
            group_syncer::TYPE_COVER
        );

        // Call as student — should fail with required_capability_exception.
        $this->setUser($student);
        $this->expectException(\required_capability_exception::class);
        delete_mapping::execute($mappingid);
    }
}
