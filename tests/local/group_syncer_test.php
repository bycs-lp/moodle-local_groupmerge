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

    /** @var string The school id for the test school. */
    private string $schoolid = '1234';

    /** @var idmgroup an test IDM group of type "class". */
    private idmgroup $idmgroup1;
    /** @var idmgroup an test IDM group of type "team". */
    private idmgroup $idmgroup2;
    /** @var idmgroup an test IDM group of type "classunit". */
    private idmgroup $idmgroup3;
    /** @var stdClass An example student user. */
    private stdClass $user1;
    /** @var stdClass An example student user. */
    private stdClass $user2;
    /** @var stdClass An example student user. */
    private stdClass $user3;
    /** @var stdClass An example student user. */
    private stdClass $user4;

    #[\Override]
    public function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();

        $userdata = [
                'institution' => $this->schoolid,
        ];
        $userdata['idnumber'] = '005e8c96-20fb-49b7-856f-1b19b58f0e62';
        $this->user1 = $this->getDataGenerator()->create_user($userdata);
        $userdata['idnumber'] = '9cdce1f4-81a0-4cfb-b8a1-a9f1257e3683';
        $this->user2 = $this->getDataGenerator()->create_user($userdata);
        $userdata['idnumber'] = '54ad8503-3d54-4ab5-bf57-e925fa9e74fd';
        $this->user3 = $this->getDataGenerator()->create_user($userdata);
        $userdata['idnumber'] = '9e19a27d-d15b-49f4-9cef-1aad5846e04c';
        $this->user4 = $this->getDataGenerator()->create_user($userdata);

        $this->getDataGenerator()->create_category(['idnumber' => $this->schoolid]);
        $school = new school($this->schoolid);
        $school->set_schooltype(school::get_schooltype_id('RS'));
        $school->link_school_category();
        $school->set_name('Test school');
        $school->store();
        $this->idmgroup1 = idmgroup::create_from_groupinfo(
                '36855b1b-52bc-4767-8d70-a9d9275948ef=Klasse 5a',
                idmgroup::IDM_GROUP_TYPE['class'],
                $this->schoolid
        );
        $this->idmgroup2 = idmgroup::create_from_groupinfo(
                'c5c7f8a3-a88a-433c-ac38-69b6234fe2ec=Video-AG',
                idmgroup::IDM_GROUP_TYPE['team'],
                $this->schoolid
        );
        $this->idmgroup3 = idmgroup::create_from_groupinfo(
                'b08c23a6-73e8-4f5f-a02e-5a3f313cc944=8ab_Rk',
                idmgroup::IDM_GROUP_TYPE['classunit'],
                $this->schoolid
        );
        $this->idmgroup1->store();
        $this->idmgroup2->store();
        $this->idmgroup3->store();
    }

    /**
     * Tests the synchronization of members from between an IDM group and a course group.
     *
     * @covers \local_groupmerge\local\group_syncer::sync_group_members
     */
    public function test_sync_group_members(): void {
        global $CFG;
        require_once($CFG->dirroot . '/group/lib.php');

        $this->idmgroup1->assign_user($this->user1->id);
        $this->idmgroup1->assign_user($this->user2->id);
        $this->idmgroup1->assign_user($this->user3->id);

        $course = $this->getDataGenerator()->create_course();
        enrol_try_internal_enrol($course->id, $this->user1->id);
        enrol_try_internal_enrol($course->id, $this->user2->id);
        enrol_try_internal_enrol($course->id, $this->user3->id);
        enrol_try_internal_enrol($course->id, $this->user4->id);
        $courseconfig = new courseconfig($course->id);
        $courseconfig->set_idmgrouptype_syncmode(idmgroup::IDM_GROUP_TYPE['class'], courseconfig::SYNCMODE_SYNC_ALL);
        $courseconfig->set_idmgrouptype_syncmode(idmgroup::IDM_GROUP_TYPE['team'], courseconfig::SYNCMODE_SYNC_ALL);
        $courseconfig->set_idmgrouptype_syncmode(idmgroup::IDM_GROUP_TYPE['classunit'], courseconfig::SYNCMODE_SYNC_ALL);
        $courseconfig->set_enabled(true);
        $courseconfig->store();

        $newgroupdata = new \stdClass();
        $newgroupdata->name = $this->idmgroup1->get_name();
        $newgroupdata->courseid = $course->id;
        $newgroupid = groups_create_group($newgroupdata);
        $coursegroupidmgroup1 = groups_get_group($newgroupid);

        $groupsyncer = new group_syncer($course->id);

        // Now we do the real tests.
        // Test if an empty course group is filled with all the users of the IDM group.
        $this->assertEmpty(groups_get_members($coursegroupidmgroup1->id));
        $groupsyncer->sync_group_members($this->idmgroup1, $coursegroupidmgroup1);
        $coursegroupidmgroup1memberids = array_map(fn($user) => $user->id, groups_get_members($coursegroupidmgroup1->id));
        $this->assertCount(count($this->idmgroup1->get_userids()), $coursegroupidmgroup1memberids);
        foreach ($this->idmgroup1->get_userids() as $userid) {
            $this->assertTrue(in_array($userid, $coursegroupidmgroup1memberids));
        }

        // Test if a user that has been in the course group is properly removed.
        groups_add_member($coursegroupidmgroup1->id, $this->user4->id);
        $coursegroupidmgroup1memberids = array_map(fn($user) => $user->id, groups_get_members($coursegroupidmgroup1->id));
        $this->assertTrue(in_array($this->user4->id, $coursegroupidmgroup1memberids));
        $groupsyncer->sync_group_members($this->idmgroup1, $coursegroupidmgroup1);
        $coursegroupidmgroup1memberids = array_map(fn($user) => $user->id, groups_get_members($coursegroupidmgroup1->id));
        $this->assertCount(count($this->idmgroup1->get_userids()), $coursegroupidmgroup1memberids);
        foreach ($this->idmgroup1->get_userids() as $userid) {
            $this->assertTrue(in_array($userid, $coursegroupidmgroup1memberids));
        }
        $this->assertFalse(in_array($this->user4->id, $coursegroupidmgroup1memberids));

        // Test the mix: IDM users are already in the course group, but there are more IDM users in the course group than
        // there should be.
        // Test if a user that has been in the course group is properly removed.
        groups_add_member($coursegroupidmgroup1->id, $this->user2->id);
        groups_add_member($coursegroupidmgroup1->id, $this->user4->id);
        $coursegroupidmgroup1memberids = array_map(fn($user) => $user->id, groups_get_members($coursegroupidmgroup1->id));
        $this->assertTrue(in_array($this->user4->id, $coursegroupidmgroup1memberids));
        $groupsyncer->sync_group_members($this->idmgroup1, $coursegroupidmgroup1);
        $coursegroupidmgroup1memberids = array_map(fn($user) => $user->id, groups_get_members($coursegroupidmgroup1->id));
        $this->assertCount(count($this->idmgroup1->get_userids()), $coursegroupidmgroup1memberids);
        foreach ($this->idmgroup1->get_userids() as $userid) {
            $this->assertTrue(in_array($userid, $coursegroupidmgroup1memberids));
        }
        $this->assertFalse(in_array($this->user4->id, $coursegroupidmgroup1memberids));
    }

    /**
     * Tests the creation of a course group for an IDM group.
     *
     * @covers \local_groupmerge\local\group_syncer::create_course_group_for_idmgroup
     */
    public function test_create_course_group_for_idmgroup(): void {
        global $DB;
        $course = $this->getDataGenerator()->create_course();
        $this->assertEmpty(groups_get_all_groups($course->id));
        $groupsyncer = new group_syncer($course);
        $createdgroup = $groupsyncer->create_course_group_for_idmgroup($this->idmgroup1);
        $groups = groups_get_all_groups($course->id);
        $group = array_pop($groups);
        $this->assertEquals($group->id, $createdgroup->id);
        $this->assertEquals(utils::get_coursegroupname_for_idmgroup($this->idmgroup1), $createdgroup->name);
        $groupmapping = $DB->get_record('local_groupmerge_groupmapping',
                ['courseid' => $course->id, 'idmgroupid' => $this->idmgroup1->get_id()]);
        $this->assertNotEmpty($groupmapping);
        $this->assertEquals($group->id, $groupmapping->coursegroupid);
        $this->assertEquals($this->idmgroup1->get_id(), $groupmapping->idmgroupid);
    }

    /**
     * Tests the method to find all IDM groups of all the users enrolled in a course.
     *
     * @covers \local_groupmerge\local\group_syncer::get_idmgroups_of_course_users
     */
    public function test_get_idmgroups_of_course_users(): void {
        $course = $this->getDataGenerator()->create_course();
        enrol_try_internal_enrol($course->id, $this->user1->id);
        enrol_try_internal_enrol($course->id, $this->user2->id);
        enrol_try_internal_enrol($course->id, $this->user3->id);
        enrol_try_internal_enrol($course->id, $this->user4->id);

        $courseconfig = new courseconfig($course->id);
        $courseconfig->set_idmgrouptype_syncmode(idmgroup::IDM_GROUP_TYPE['class'], courseconfig::SYNCMODE_SYNC_ALL);
        $courseconfig->set_idmgrouptype_syncmode(idmgroup::IDM_GROUP_TYPE['team'], courseconfig::SYNCMODE_DISABLED);
        $courseconfig->set_idmgrouptype_syncmode(idmgroup::IDM_GROUP_TYPE['classunit'], courseconfig::SYNCMODE_SYNC_ALL);
        $courseconfig->set_enabled(true);
        $courseconfig->set_idmgroups_to_sync([]);
        $courseconfig->store();

        $this->idmgroup1->assign_user($this->user1->id);
        $this->idmgroup3->assign_user($this->user1->id);
        $this->idmgroup3->assign_user($this->user2->id);

        $groupsyncer = new group_syncer($course->id);

        // First of all we test that class units are not being included if setting is disabled.
        set_config('enableclassunits', 0, 'local_groupmerge');
        $idmgroups = $groupsyncer->get_idmgroups_of_course_users();
        $this->assertCount(1, $idmgroups);
        $this->assertEquals($this->idmgroup1->get_id(), array_values($groupsyncer->get_idmgroups_of_course_users())[0]->get_id());

        // Enable class units for the rest of the test.
        set_config('enableclassunits', 1, 'local_groupmerge');
        $idmgroups = $groupsyncer->get_idmgroups_of_course_users();
        $this->assertCount(2, $idmgroups);
        $idmgroupids = array_map(fn($idmgroup) => $idmgroup->get_id(), $groupsyncer->get_idmgroups_of_course_users());
        $this->assertTrue(in_array($this->idmgroup1->get_id(), $idmgroupids));
        $this->assertTrue(in_array($this->idmgroup3->get_id(), $idmgroupids));

        // The IDM group 2 is of type 'team' which we disabled for synchronization. So we would expect that we do not have this
        // group in the result.
        $this->idmgroup2->assign_user($this->user2->id);
        $idmgroups = $groupsyncer->get_idmgroups_of_course_users();
        $this->assertCount(2, $idmgroups);
        $idmgroupids = array_map(fn($idmgroup) => $idmgroup->get_id(), $groupsyncer->get_idmgroups_of_course_users());
        $this->assertFalse(in_array($this->idmgroup2->get_id(), $idmgroupids));

        // Now test the custom sync mode where the user can select the IDM groups to sync.
        // First add some more classes.
        $class1 = idmgroup::create_from_groupinfo(
                '7f1db1a4-6bcc-4b9f-9ac4-461a1f3e8d79=Klasse 6a',
                idmgroup::IDM_GROUP_TYPE['class'],
                $this->schoolid
        );
        $class2 = idmgroup::create_from_groupinfo(
                'c621d87b-5b87-43f6-8c7f-2476ff4d4b96=Klasse 6b',
                idmgroup::IDM_GROUP_TYPE['class'],
                $this->schoolid
        );
        $class3 = idmgroup::create_from_groupinfo(
                '8079366f-da3a-493a-8435-b38391718232=Klasse 6c',
                idmgroup::IDM_GROUP_TYPE['class'],
                $this->schoolid
        );
        $class1->store();
        $class2->store();
        $class3->store();

        $class1->assign_user($this->user1->id);
        $class1->assign_user($this->user2->id);
        $class2->assign_user($this->user1->id);
        $class2->assign_user($this->user3->id);
        $class3->assign_user($this->user2->id);
        $class3->assign_user($this->user3->id);

        // We set random sync modes for the IDM group types. They should not be evaluated as soon as we define IDM groups to sync.
        $courseconfig->set_idmgrouptype_syncmode(idmgroup::IDM_GROUP_TYPE['class'], courseconfig::SYNCMODE_SYNC_ALL);
        $courseconfig->set_idmgrouptype_syncmode(idmgroup::IDM_GROUP_TYPE['team'], courseconfig::SYNCMODE_DISABLED);
        $courseconfig->set_idmgrouptype_syncmode(idmgroup::IDM_GROUP_TYPE['classunit'], courseconfig::SYNCMODE_SYNC_ALL);

        $idmgroupstosync = [$class1->get_id(), $class2->get_id()];
        $courseconfig->set_idmgroups_to_sync($idmgroupstosync);
        $courseconfig->store();

        // We have to reinitialize the group_syncer object, because it caches the courseconfig object.
        $groupsyncer = new group_syncer($course->id);
        $idmgroupids = array_map(fn($idmgroup) => $idmgroup->get_id(), $groupsyncer->get_idmgroups_of_course_users());
        $this->assertFalse(in_array($this->idmgroup1->get_id(), $idmgroupids));
        $this->assertFalse(in_array($this->idmgroup2->get_id(), $idmgroupids));
        $this->assertFalse(in_array($this->idmgroup3->get_id(), $idmgroupids));
        $this->assertTrue(in_array($class1->get_id(), $idmgroupids));
        $this->assertTrue(in_array($class2->get_id(), $idmgroupids));
        $this->assertFalse(in_array($class3->get_id(), $idmgroupids));
    }

    /**
     * Tests the synchronization of IDM groups and course groups.
     *
     * @covers \local_groupmerge\local\group_syncer::sync_groups
     */
    public function test_sync_groups(): void {
        global $DB;
        $course = $this->getDataGenerator()->create_course();
        enrol_try_internal_enrol($course->id, $this->user1->id);
        enrol_try_internal_enrol($course->id, $this->user2->id);
        enrol_try_internal_enrol($course->id, $this->user3->id);
        enrol_try_internal_enrol($course->id, $this->user4->id);

        $this->idmgroup1->assign_user($this->user1->id);
        $this->idmgroup2->assign_user($this->user1->id);
        $this->idmgroup2->assign_user($this->user2->id);
        $this->idmgroup3->assign_user($this->user3->id);
        // Create another IDM group to see that it is not being synced into the course.
        $anotherclass = idmgroup::create_from_groupinfo(
                '7f1db1a4-6bcc-4b9f-9ac4-461a1f3e8d79=Klasse 6a',
                idmgroup::IDM_GROUP_TYPE['class'],
                $this->schoolid
        );
        $anotherclass->store();

        $courseconfig = new courseconfig($course->id);
        // We enable all IDM group types. Further testing what happens if one is disabled is being done in
        // the method ::test_get_idmgroups_of_course_users.
        $courseconfig->set_idmgrouptype_syncmode(idmgroup::IDM_GROUP_TYPE['class'], courseconfig::SYNCMODE_SYNC_ALL);
        $courseconfig->set_idmgrouptype_syncmode(idmgroup::IDM_GROUP_TYPE['team'], courseconfig::SYNCMODE_SYNC_ALL);
        $courseconfig->set_idmgrouptype_syncmode(idmgroup::IDM_GROUP_TYPE['classunit'], courseconfig::SYNCMODE_SYNC_ALL);
        $courseconfig->set_enabled(true);
        $courseconfig->store();

        set_config('enableclassunits', 0, 'local_groupmerge');

        // Based on this test setup we would expect the creation of 2 course groups related to IDM group 1 and IDM group 2,
        // because IDM group 3 is a class unit which is disabled by the admin setting.
        $groupsyncer = new group_syncer($course->id);
        $this->assertEmpty(groups_get_all_groups($course->id));
        $groupsyncer->sync_groups();
        $coursegroups = groups_get_all_groups($course->id);

        $this->assertCount(2, $coursegroups);
        foreach ([$this->idmgroup1, $this->idmgroup2] as $idmgroup) {
            $groupmapping = $DB->get_record('local_groupmerge_groupmapping',
                    ['courseid' => $course->id, 'idmgroupid' => $idmgroup->get_id()]);
            $this->assertNotEmpty($groupmapping);
            $this->assertTrue(in_array($groupmapping->coursegroupid,
                    array_map(fn($coursegroup) => $coursegroup->id, $coursegroups)));
        }

        set_config('enableclassunits', 1, 'local_groupmerge');
        // Now retest with enabled setting.
        $groupsyncer = new group_syncer($course->id);
        // Wipe groups before.
        $groupsyncer->wipe_handled_groups();
        $this->assertEmpty(groups_get_all_groups($course->id));
        $groupsyncer->sync_groups();
        $coursegroups = groups_get_all_groups($course->id);

        $this->assertCount(3, $coursegroups);
        foreach ([$this->idmgroup1, $this->idmgroup2, $this->idmgroup3] as $idmgroup) {
            $groupmapping = $DB->get_record('local_groupmerge_groupmapping',
                    ['courseid' => $course->id, 'idmgroupid' => $idmgroup->get_id()]);
            $this->assertNotEmpty($groupmapping);
            $this->assertTrue(in_array($groupmapping->coursegroupid,
                    array_map(fn($coursegroup) => $coursegroup->id, $coursegroups)));
        }

        // We now remove idmgroup2 and unassign all users from idmgroup3.
        // We then test the setting "cleanupgroups" which is being passed to the sync_groups method.
        $this->idmgroup2->delete();
        $this->idmgroup3->unassign_user($this->user3->id);

        // We use the default parameter which means that we do not want to remove unneeded groups.
        // As idmgroup2 now has been deleted, this will remove the associated course group from the managed groups.
        // So from now on we do not have this group anymore managed by this plugin.
        $groupsyncer->sync_groups();
        // We expect that all 3 groups are still there.
        $coursegroups = groups_get_all_groups($course->id);
        $this->assertCount(3, $coursegroups);
        // IDM group 2 has been deleted, so there is no mapping anymore for it. But the course group still exists.
        $this->assertFalse($courseconfig->get_mapped_coursegroup($this->idmgroup2));
        $orphanedcoursegroup = array_values(array_filter($coursegroups,
                fn($coursegroup) => $coursegroup->name === utils::get_coursegroupname_for_idmgroup($this->idmgroup2)))[0];
        foreach ([$this->idmgroup1, $this->idmgroup3] as $idmgroup) {
            $groupmapping = $DB->get_record('local_groupmerge_groupmapping',
                    ['courseid' => $course->id, 'idmgroupid' => $idmgroup->get_id()]);
            $this->assertNotEmpty($groupmapping);
            $this->assertTrue(in_array($groupmapping->coursegroupid,
                    array_map(fn($coursegroup) => $coursegroup->id, $coursegroups)));
        }
        $courseconfig = new courseconfig($course->id);
        $this->assertEmpty(groups_get_members($orphanedcoursegroup->id));
        $this->assertEmpty(groups_get_members($courseconfig->get_mapped_coursegroup($this->idmgroup3)->id));

        $groupsyncer->sync_groups(true);
        // We expect that the course group that mirrors idmgroup3 is being removed.
        // The course group that formerly mirrored idmgroup2 is not being removed because at this point it is not being
        // handled anymore by this plugin.
        $coursegroups = groups_get_all_groups($course->id);
        $this->assertCount(2, $coursegroups);
        foreach ([$this->idmgroup1] as $idmgroup) {
            $groupmapping = $DB->get_record('local_groupmerge_groupmapping',
                    ['courseid' => $course->id, 'idmgroupid' => $idmgroup->get_id()]);
            $this->assertNotEmpty($groupmapping);
            $this->assertTrue(in_array($groupmapping->coursegroupid,
                    array_map(fn($coursegroup) => $coursegroup->id, $coursegroups)));
        }

        // Test what happens if we have invalid group mappings, even though that should not really happen.
        // We use a completely new course for this to start all over regarding configuration.
        $course2 = $this->getDataGenerator()->create_course();
        $courseconfig = new courseconfig($course2->id);
        enrol_try_internal_enrol($course2->id, $this->user1->id);
        $this->idmgroup1->assign_user($this->user1->id);

        $courseconfig->set_enabled(true);
        $courseconfig->set_idmgroups_to_sync([$this->idmgroup1->get_id()]);
        $courseconfig->store();
        $courseconfig->update_sync_state(courseconfig::SYNC_NEEDED);
        $groupsyncer = new group_syncer($course2->id);
        $groupsyncer->sync_course();
        $this->assertCount(1, $courseconfig->get_managed_groupids());
        $coursegroup = $courseconfig->get_mapped_coursegroup($this->idmgroup1);
        $this->assertNotFalse($coursegroup);
        // We now delete the group. We're doing this by DB statement to simulate broken data.
        // Usually, this should not happen, because the event handler for group_deleted event would remove
        // the mapping anyway. But data in production showed this happens for some reason.
        $DB->delete_records('groups', ['id' => $coursegroup->id]);
        $courseconfig->update_sync_state(courseconfig::SYNC_NEEDED);
        $groupsyncer->sync_course();
        $this->assertFalse(in_array($coursegroup->id, $courseconfig->get_managed_groupids()));

        // Now do the same again and see if the observer for idmgroup_updated event also cleans up the mess.
        $courseconfig->set_enabled(true);
        $courseconfig->set_idmgroups_to_sync([$this->idmgroup1->get_id()]);
        $courseconfig->store();
        $courseconfig->update_sync_state(courseconfig::SYNC_NEEDED);
        $groupsyncer = new group_syncer($course2->id);
        $groupsyncer->sync_course();
        $this->assertCount(1, $courseconfig->get_managed_groupids());
        $coursegroup = $courseconfig->get_mapped_coursegroup($this->idmgroup1);
        $this->assertNotFalse($coursegroup);
        // Now delete the group again to simulate broken data.
        $DB->delete_records('groups', ['id' => $coursegroup->id]);
        $this->idmgroup1->set_name('Some new name');
        $this->idmgroup1->store();
        $this->assertFalse(in_array($coursegroup->id, $courseconfig->get_managed_groupids()));
    }

    /**
     * Tests the wipe_handled_groups method.
     *
     * @covers \local_groupmerge\local\group_syncer::wipe_handled_groups
     */
    public function test_wipe_managed_groups(): void {
        $course = $this->getDataGenerator()->create_course();
        enrol_try_internal_enrol($course->id, $this->user1->id);
        enrol_try_internal_enrol($course->id, $this->user2->id);
        enrol_try_internal_enrol($course->id, $this->user3->id);
        enrol_try_internal_enrol($course->id, $this->user4->id);

        $this->idmgroup1->assign_user($this->user1->id);
        $this->idmgroup2->assign_user($this->user1->id);
        $this->idmgroup2->assign_user($this->user2->id);

        $courseconfig = new courseconfig($course->id);
        $courseconfig->set_idmgrouptype_syncmode(idmgroup::IDM_GROUP_TYPE['class'], courseconfig::SYNCMODE_SYNC_ALL);
        $courseconfig->set_idmgrouptype_syncmode(idmgroup::IDM_GROUP_TYPE['team'], courseconfig::SYNCMODE_SYNC_ALL);
        $courseconfig->set_idmgrouptype_syncmode(idmgroup::IDM_GROUP_TYPE['classunit'], courseconfig::SYNCMODE_SYNC_ALL);
        $courseconfig->set_enabled(true);
        $courseconfig->store();

        // Create a normal course group to later test that this group has been left untouched.
        $newgroupdata = new \stdClass();
        $newgroupdata->name = 'Some test group';
        $newgroupdata->courseid = $course->id;
        $newgroupid = groups_create_group($newgroupdata);
        $unrelatedcoursegroup = groups_get_group($newgroupid);

        $coursegroupids = array_map(fn($coursegroup) => $coursegroup->id, groups_get_all_groups($course->id));
        $this->assertCount(1, $coursegroupids);

        $groupsyncer = new group_syncer($course->id);
        $groupsyncer->sync_course();

        $coursegroupids = array_map(fn($coursegroup) => $coursegroup->id, groups_get_all_groups($course->id));
        $this->assertCount(3, $coursegroupids);

        $this->assertCount(2, $courseconfig->get_managed_groupids());
        foreach ($courseconfig->get_managed_groupids() as $coursegroupid) {
            $this->assertTrue(in_array($coursegroupid, $coursegroupids));
        }

        // Setup done, now wipe the managed groups and check that the managed groups have been deleted.
        $groupsyncer->wipe_handled_groups();
        $coursegroupids = array_map(fn($coursegroup) => $coursegroup->id, groups_get_all_groups($course->id));
        $this->assertCount(1, $coursegroupids);
        // Make sure that the unrelated group has not been deleted.
        $this->assertTrue(in_array($unrelatedcoursegroup->id, $coursegroupids));
        $this->assertCount(0, $courseconfig->get_managed_groupids());
    }
}
