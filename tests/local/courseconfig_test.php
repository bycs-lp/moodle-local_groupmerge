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

use local_bycsauth\idmgroup;
use local_bycsauth\school;

/**
 * Unit tests for the courseconfig wrapper of local_groupmerge.
 *
 * @package   local_groupmerge
 * @copyright 2025 ISB Bayern
 * @author    Philipp Memmel
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class courseconfig_test extends \advanced_testcase {

    /**
     * Setup function, will run before each test.
     */
    public function setUp(): void {
        $this->resetAfterTest();
        parent::setUp();
    }

    /**
     * Tests the function to mark a courseconfig record as "sync is needed".
     *
     * @covers \local_groupmerge\local\courseconfig::update_sync_state
     */
    public function test_update_sync_state(): void {
        global $DB;
        $course = $this->getDataGenerator()->create_course();
        $courseconfig = new courseconfig($course->id);
        $courseconfig->store();
        $record = $DB->get_record('local_groupmerge_config', ['courseid' => $course->id]);
        $this->assertEquals(0, $record->syncstate);
        $courseconfig->update_sync_state(courseconfig::SYNC_NEEDED);
        $record = $DB->get_record('local_groupmerge_config', ['courseid' => $course->id]);
        $this->assertEquals(1, $record->syncstate);
        $courseconfig->update_sync_state(courseconfig::SYNC_NEEDED);
        $record = $DB->get_record('local_groupmerge_config', ['courseid' => $course->id]);
        $this->assertEquals(2, $record->syncstate);
        $courseconfig->update_sync_state(courseconfig::NO_SYNC_NEEDED);
        $record = $DB->get_record('local_groupmerge_config', ['courseid' => $course->id]);
        $this->assertEquals(0, $record->syncstate);
    }

    /**
     * Tests the function to check if a course needs to be synced.
     *
     * @covers \local_groupmerge\local\courseconfig::is_sync_needed
     */
    public function test_is_sync_needed(): void {
        global $DB;
        $course = $this->getDataGenerator()->create_course();
        $courseconfig = new courseconfig($course->id);
        $courseconfig->store();
        $record = $DB->get_record('local_groupmerge_config', ['courseid' => $course->id]);
        $this->assertEquals(0, $record->syncstate);

        // Now test the sync_needed function.
        $this->assertFalse($courseconfig->is_sync_needed());

        $courseconfig->update_sync_state(courseconfig::SYNC_NEEDED);
        $record = $DB->get_record('local_groupmerge_config', ['courseid' => $course->id]);
        $this->assertEquals(1, $record->syncstate);

        // Now test the sync_needed function.
        $this->assertTrue($courseconfig->is_sync_needed());

        $courseconfig->update_sync_state(courseconfig::SYNC_NEEDED);
        $record = $DB->get_record('local_groupmerge_config', ['courseid' => $course->id]);
        $this->assertEquals(2, $record->syncstate);
        $this->assertTrue($courseconfig->is_sync_needed());

        $courseconfig->update_sync_state(courseconfig::NO_SYNC_NEEDED);
        $this->assertFalse($courseconfig->is_sync_needed());
    }

    /**
     * Tests the management of the managed course groups inside the courseconfig records.
     *
     * @covers \local_groupmerge\local\courseconfig::add_managed_groupid
     * @covers \local_groupmerge\local\courseconfig::remove_managed_groupid
     * @covers \local_groupmerge\local\courseconfig::get_mapped_coursegroup
     * @covers \local_groupmerge\local\courseconfig::get_managed_groupids
     */
    public function test_management_of_managed_groupids(): void {
        global $CFG;
        require_once($CFG->dirroot . '/group/lib.php');

        $course = $this->getDataGenerator()->create_course();
        $courseconfig = new courseconfig($course->id);
        $courseconfig->store();

        $schoolid = '1234';
        $this->getDataGenerator()->create_category(['idnumber' => $schoolid]);
        $school = new school($schoolid);
        $school->set_schooltype(school::get_schooltype_id('RS'));
        $school->link_school_category();
        $school->set_name('Test school');
        $school->store();

        $idmgroup1 = idmgroup::create_from_groupinfo(
                '36855b1b-52bc-4767-8d70-a9d9275948ef=Klasse 5a',
                idmgroup::IDM_GROUP_TYPE['class'],
                $schoolid
        );
        $idmgroup2 = idmgroup::create_from_groupinfo(
                'c5c7f8a3-a88a-433c-ac38-69b6234fe2ec=Video-AG',
                idmgroup::IDM_GROUP_TYPE['team'],
                $schoolid
        );
        $idmgroup3 = idmgroup::create_from_groupinfo(
                'b08c23a6-73e8-4f5f-a02e-5a3f313cc944=8ab_Rk',
                idmgroup::IDM_GROUP_TYPE['classunit'],
                $schoolid
        );
        $idmgroup1->store();
        $idmgroup2->store();
        $idmgroup3->store();

        $newgroupdata = new \stdClass();
        $newgroupdata->name = $idmgroup1->get_name();
        $newgroupdata->courseid = $course->id;
        $newgroupid = groups_create_group($newgroupdata);
        $coursegroupidmgroup1 = groups_get_group($newgroupid);

        $newgroupdata = new \stdClass();
        $newgroupdata->name = $idmgroup2->get_name();
        $newgroupdata->courseid = $course->id;
        $newgroupid = groups_create_group($newgroupdata);
        $coursegroupidmgroup2 = groups_get_group($newgroupid);

        $newgroupdata = new \stdClass();
        $newgroupdata->name = $idmgroup3->get_name();
        $newgroupdata->courseid = $course->id;
        $newgroupid = groups_create_group($newgroupdata);
        $coursegroupidmgroup3 = groups_get_group($newgroupid);

        $courseconfig->add_managed_groupid($idmgroup1->get_id(), $coursegroupidmgroup1->id);
        $courseconfig->add_managed_groupid($idmgroup2->get_id(), $coursegroupidmgroup2->id);
        $courseconfig->add_managed_groupid($idmgroup3->get_id(), $coursegroupidmgroup3->id);

        $this->assertCount(3, $courseconfig->get_managed_groupids());
        $this->assertTrue(in_array($coursegroupidmgroup1->id, $courseconfig->get_managed_groupids()));
        $this->assertTrue(in_array($coursegroupidmgroup2->id, $courseconfig->get_managed_groupids()));
        $this->assertTrue(in_array($coursegroupidmgroup3->id, $courseconfig->get_managed_groupids()));

        $this->assertEquals($coursegroupidmgroup1, $courseconfig->get_mapped_coursegroup($idmgroup1));
        $this->assertEquals($coursegroupidmgroup2, $courseconfig->get_mapped_coursegroup($idmgroup2));
        $this->assertEquals($coursegroupidmgroup3, $courseconfig->get_mapped_coursegroup($idmgroup3));

        $courseconfig->remove_managed_groupid($coursegroupidmgroup1->id);
        $courseconfig->remove_managed_groupid($coursegroupidmgroup2->id);
        $courseconfig->remove_managed_groupid($coursegroupidmgroup3->id);
        $this->assertCount(0, $courseconfig->get_managed_groupids());
    }
}
