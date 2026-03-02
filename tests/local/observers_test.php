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
use core\event\user_enrolment_created;
use core\event\user_enrolment_deleted;
use local_bycsauth\event\idmuser_membership_updated;
use local_bycsauth\idmgroup;
use local_bycsauth\school;
use local_groupmerge\task\mark_courses_for_syncing;

/**
 * Unit tests for the observers class of local_groupmerge.
 *
 * @package   local_groupmerge
 * @copyright 2025 ISB Bayern
 * @author    Philipp Memmel
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers    \local_groupmerge\local\observers
 */
final class observers_test extends \advanced_testcase {

    /**
     * Tests the event handler for the {@see idmuser_membership_updated} event.
     *
     * @covers \local_groupmerge\local\observers::idmuser_membership_updated
     */
    public function test_idmuser_membership_updated(): void {
        $this->resetAfterTest();
        $schoolid = '1234';
        $this->getDataGenerator()->create_category(['idnumber' => $schoolid]);
        $school = new school($schoolid);
        $school->set_schooltype(school::get_schooltype_id('RS'));
        $school->link_school_category();
        $school->set_name('Test school');
        $school->store();
        $idmgroup = idmgroup::create_from_groupinfo(
                '36855b1b-52bc-4767-8d70-a9d9275948ef=Klasse 5a',
                idmgroup::IDM_GROUP_TYPE['class'],
                $schoolid
        );
        $idmgroup->store();

        $userdata = [
                'institution' => $schoolid,
                'idnumber' => '005e8c96-20fb-49b7-856f-1b19b58f0e62',
        ];
        // Proper IDM user.
        $user1 = $this->getDataGenerator()->create_user($userdata);

        $userdata = [
                'idnumber' => '9cdce1f4-81a0-4cfb-b8a1-a9f1257e3683',
        ];
        // User without institution.
        $user2 = $this->getDataGenerator()->create_user($userdata);

        $userdata = [
                'institution' => $schoolid,
        ];
        // User without ByCS id.
        $user3 = $this->getDataGenerator()->create_user($userdata);

        $this->assertEmpty(\core\task\manager::get_adhoc_tasks(mark_courses_for_syncing::class));

        $idmgroup->assign_user($user1->id);
        $adhoctasks = \core\task\manager::get_adhoc_tasks(mark_courses_for_syncing::class);
        $this->assertCount(1, $adhoctasks);

        /** @var \core\task\adhoc_task $adhoctask */
        $adhoctask = reset($adhoctasks);
        $adhoctaskdata = $adhoctask->get_custom_data();
        $this->assertEquals($idmgroup->get_externalid(), $adhoctaskdata->idmgroupid);
        $this->assertEquals($user1->id, $adhoctaskdata->userid);

        $idmgroup->unassign_user($user1->id);
        $adhoctasks = \core\task\manager::get_adhoc_tasks(mark_courses_for_syncing::class);
        $this->assertCount(2, $adhoctasks);

        /** @var \core\task\adhoc_task $adhoctask */
        $adhoctask = reset($adhoctasks);
        $adhoctaskdata = $adhoctask->get_custom_data();
        $this->assertEquals($idmgroup->get_externalid(), $adhoctaskdata->idmgroupid);
        $this->assertEquals($user1->id, $adhoctaskdata->userid);

        // Now test the users that should not trigger an adhoc task.
        $idmgroup->assign_user($user2->id);
        $adhoctasks = \core\task\manager::get_adhoc_tasks(mark_courses_for_syncing::class);
        // We still have the two adhoc tasks for user1.
        $this->assertCount(2, $adhoctasks);
        $idmgroup->assign_user($user3->id);
        $adhoctasks = \core\task\manager::get_adhoc_tasks(mark_courses_for_syncing::class);
        // We still have the two adhoc tasks for user1.
        $this->assertCount(2, $adhoctasks);

        // We do not test what the task actually does, because the task function is being covered in a separate test.
    }

    /**
     * Tests the event handler when an IDM group is being deleted.
     */
    public function test_idmgroup_deleted(): void {
        $this->resetAfterTest();
        $schoolid = '1234';
        $this->getDataGenerator()->create_category(['idnumber' => $schoolid]);
        $school = new school($schoolid);
        $school->set_schooltype(school::get_schooltype_id('RS'));
        $school->link_school_category();
        $school->set_name('Test school');
        $school->store();
        $idmgroup = idmgroup::create_from_groupinfo(
                '36855b1b-52bc-4767-8d70-a9d9275948ef=Klasse 5a',
                idmgroup::IDM_GROUP_TYPE['class'],
                $schoolid
        );
        $idmgroup->store();

        $userdata = [
                'institution' => $schoolid,
                'idnumber' => '005e8c96-20fb-49b7-856f-1b19b58f0e62',
        ];
        $user = $this->getDataGenerator()->create_user($userdata);

        $idmgroup->assign_user($user->id);

        $course = $this->getDataGenerator()->create_course();
        enrol_try_internal_enrol($course->id, $user->id);

        $courseconfig = new courseconfig($course->id);
        $courseconfig->set_enabled(true);
        $courseconfig->set_idmgrouptype_syncmode(idmgroup::IDM_GROUP_TYPE['class'], true);
        $courseconfig->store();
        $groupsyncer = new group_syncer($course->id);
        $groupsyncer->sync_course();
        $this->assertNotFalse($courseconfig->get_mapped_coursegroup($idmgroup));
        $this->assertFalse($courseconfig->is_sync_needed());

        $idmgroup->delete();
        $this->assertTrue($courseconfig->is_sync_needed());
        $groupsyncer->sync_course();
        $this->assertFalse($courseconfig->get_mapped_coursegroup($idmgroup));
    }

    /**
     * Tests the event handler when an IDM group is being updated.
     */
    public function test_idmgroup_updated(): void {
        $this->resetAfterTest();
        $schoolid = '1234';
        $this->getDataGenerator()->create_category(['idnumber' => $schoolid]);
        $school = new school($schoolid);
        $school->set_schooltype(school::get_schooltype_id('RS'));
        $school->link_school_category();
        $school->set_name('Test school');
        $school->store();
        $idmgroup = idmgroup::create_from_groupinfo(
                '36855b1b-52bc-4767-8d70-a9d9275948ef=Klasse 5a',
                idmgroup::IDM_GROUP_TYPE['class'],
                $schoolid
        );
        $idmgroup->store();

        $userdata = [
                'institution' => $schoolid,
                'idnumber' => '005e8c96-20fb-49b7-856f-1b19b58f0e62',
        ];
        $user = $this->getDataGenerator()->create_user($userdata);

        $idmgroup->assign_user($user->id);

        $course = $this->getDataGenerator()->create_course();
        enrol_try_internal_enrol($course->id, $user->id);

        $courseconfig = new courseconfig($course->id);
        $courseconfig->set_enabled(true);
        $courseconfig->set_idmgrouptype_syncmode(idmgroup::IDM_GROUP_TYPE['class'], true);
        $courseconfig->store();
        $groupsyncer = new group_syncer($course->id);
        $groupsyncer->sync_course();
        $this->assertNotFalse($courseconfig->get_mapped_coursegroup($idmgroup));
        $this->assertFalse($courseconfig->is_sync_needed());

        // We initially synced a new course group, so it will have the default name.
        // When now changing the name of the IDM group we expect that the name will be updated.
        $idmgroup->set_name('another name than before');
        $idmgroup->store();
        $coursegroup = $courseconfig->get_mapped_coursegroup($idmgroup);
        $this->assertEquals(utils::get_coursegroupname_for_idmgroup($idmgroup), $coursegroup->name);

        // We now change the name of the course group manually. The name should NOT be updated in this case
        // to not overwrite the customized name.
        $coursegroup->name = 'a custom name chosen by the user';
        groups_update_group($coursegroup);
        $coursegroup = $courseconfig->get_mapped_coursegroup($idmgroup);
        $this->assertEquals('a custom name chosen by the user', $coursegroup->name);
        $idmgroup->set_name('a new IDM group name');
        $idmgroup->store();
        $coursegroup = $courseconfig->get_mapped_coursegroup($idmgroup);
        // The coursegroup still has the old name.
        $this->assertEquals('a custom name chosen by the user', $coursegroup->name);
    }

    /**
     * Tests the event handler for the events {@see user_enrolment_created} and {@see user_enrolment_deleted} event.
     *
     * @covers \local_groupmerge\local\observers::user_enrolment_created_or_deleted
     */
    public function test_user_enrolment_created_or_deleted(): void {
        global $CFG;
        require_once($CFG->libdir . '/enrollib.php');
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $user = $this->getDataGenerator()->create_user();
        $courseconfig = new courseconfig($course->id);
        $courseconfig->set_enabled(true);
        $courseconfig->store();

        $this->assertFalse($courseconfig->is_sync_needed());
        enrol_try_internal_enrol($course->id, $user->id);
        $this->assertTrue($courseconfig->is_sync_needed());

        // Reset sync state.
        $courseconfig->update_sync_state(courseconfig::NO_SYNC_NEEDED);
        $this->assertFalse($courseconfig->is_sync_needed());

        // Now check if unenrolling the user also triggers the "sync needed" flag.
        $enrolinstances = enrol_get_instances($course->id, true);
        $enrolinstance = array_pop($enrolinstances);
        $manualenrolplugin = enrol_get_plugin('manual');
        $manualenrolplugin->unenrol_user($enrolinstance, $user->id);
        $this->assertTrue($courseconfig->is_sync_needed());

        // Now check if a non existing course config is being ignored.
        $course2 = $this->getDataGenerator()->create_course();
        $courseconfig2 = new courseconfig($course2->id);
        $this->assertFalse($courseconfig2->record_exists());

        // Now check if a course with not enabled courseconfig is being ignored.
        $course3 = $this->getDataGenerator()->create_course();
        $courseconfig3 = new courseconfig($course3->id);
        $courseconfig3->set_enabled(false);
        $courseconfig3->store();
        enrol_try_internal_enrol($course3->id, $user->id);
        $this->assertFalse($courseconfig3->is_sync_needed());

        // Now check if a course with not enabled courseconfig is being ignored.
        $course4 = $this->getDataGenerator()->create_course();
        $courseconfig4 = new courseconfig($course4->id);
        $courseconfig4->set_enabled(false);
        $courseconfig4->store();
        enrol_try_internal_enrol($course4->id, $user->id);
        $this->assertFalse($courseconfig4->is_sync_needed());
    }

    /**
     * Tests the event handler for the {@see group_deleted} event.
     *
     * @covers \local_groupmerge\local\observers::group_deleted
     */
    public function test_group_deleted(): void {
        global $CFG;
        require_once($CFG->dirroot . '/group/lib.php');
        $this->resetAfterTest();

        $schoolid = '1234';
        $this->getDataGenerator()->create_category(['idnumber' => $schoolid]);
        $school = new school($schoolid);
        $school->set_schooltype(school::get_schooltype_id('RS'));
        $school->link_school_category();
        $school->set_name('Test school');
        $school->store();
        $idmgroup = idmgroup::create_from_groupinfo(
                '36855b1b-52bc-4767-8d70-a9d9275948ef=Klasse 5a',
                idmgroup::IDM_GROUP_TYPE['class'],
                $schoolid
        );
        $idmgroup->store();

        $course = $this->getDataGenerator()->create_course();
        $courseconfig = new courseconfig($course->id);
        $courseconfig->set_enabled(true);
        $courseconfig->store();

        $groupsyncer = new group_syncer($course->id);
        $coursegroup = $groupsyncer->create_course_group_for_idmgroup($idmgroup);
        $this->assertTrue(in_array($coursegroup->id, $courseconfig->get_managed_groupids()));

        groups_delete_group($coursegroup->id);
        $this->assertFalse(in_array($coursegroup->id, $courseconfig->get_managed_groupids()));
    }

    /**
     * Tests the event handler for the {@see course_deleted} event.
     *
     * @covers \local_groupmerge\local\observers::course_deleted
     * @covers \local_groupmerge\local\courseconfig::delete
     */
    public function test_course_deleted(): void {
        $this->resetAfterTest();

        $schoolid = '1234';
        $this->getDataGenerator()->create_category(['idnumber' => $schoolid]);
        $school = new school($schoolid);
        $school->set_schooltype(school::get_schooltype_id('RS'));
        $school->link_school_category();
        $school->set_name('Test school');
        $school->store();
        $idmgroup = idmgroup::create_from_groupinfo(
                '36855b1b-52bc-4767-8d70-a9d9275948ef=Klasse 5a',
                idmgroup::IDM_GROUP_TYPE['class'],
                $schoolid
        );
        $idmgroup->store();

        $course = $this->getDataGenerator()->create_course();
        $userdata = [
                'institution' => $schoolid,
                'idnumber' => '005e8c96-20fb-49b7-856f-1b19b58f0e62',
        ];
        $user = $this->getDataGenerator()->create_user($userdata);
        enrol_try_internal_enrol($course->id, $user->id);
        $idmgroup->assign_user($user->id);

        $courseconfig = new courseconfig($course->id);
        $courseconfig->set_enabled(true);
        $courseconfig->set_idmgrouptype_syncmode(idmgroup::IDM_GROUP_TYPE['class'], true);
        $courseconfig->store();

        $groupsyncer = new group_syncer($course->id);
        $groupsyncer->sync_course();

        $this->assertTrue($courseconfig->record_exists());
        $this->assertCount(1, utils::get_group_mappings($course->id));
        delete_course($course->id, false);
        $this->assertFalse($courseconfig->record_exists());
        $this->assertCount(0, utils::get_group_mappings($course->id));
    }
}
