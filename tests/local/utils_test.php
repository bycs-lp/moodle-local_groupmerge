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
 * Unit tests for the utils class of local_groupmerge.
 *
 * @package   local_groupmerge
 * @copyright 2025 ISB Bayern
 * @author    Philipp Memmel
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers    \local_groupmerge\local\utils
 */
final class utils_test extends \advanced_testcase {

    /**
     * Tests the mark_courses_for_syncing method.
     */
    public function test_mark_courses_for_syncing(): void {
        $this->resetAfterTest();
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
        $idmgroup1->store();
        $idmgroup2 = idmgroup::create_from_groupinfo(
                'c5c7f8a3-a88a-433c-ac38-69b6234fe2ec=Video-AG',
                idmgroup::IDM_GROUP_TYPE['team'],
                $schoolid
        );
        $idmgroup2->store();

        $userdata = [
                'institution' => $schoolid,
        ];
        $userdata['idnumber'] = '005e8c96-20fb-49b7-856f-1b19b58f0e62';
        $user1 = $this->getDataGenerator()->create_user($userdata);
        $userdata['idnumber'] = '9cdce1f4-81a0-4cfb-b8a1-a9f1257e3683';
        $user2 = $this->getDataGenerator()->create_user($userdata);
        $userdata['idnumber'] = '54ad8503-3d54-4ab5-bf57-e925fa9e74fd';
        $user3 = $this->getDataGenerator()->create_user($userdata);
        $userdata['idnumber'] = '9e19a27d-d15b-49f4-9cef-1aad5846e04c';
        $user4 = $this->getDataGenerator()->create_user($userdata);

        $idmgroup1->assign_user($user1->id);
        $idmgroup1->assign_user($user2->id);
        $idmgroup2->assign_user($user1->id);
        $idmgroup2->assign_user($user3->id);

        $course1 = $this->getDataGenerator()->create_course();
        // We enrol $user1, so this course will have a user assigned to both $idmgroup1 and $idmgroup2.
        enrol_try_internal_enrol($course1->id, $user1->id);

        $courseconfig1 = new courseconfig($course1->id);
        $courseconfig1->set_enabled(true);
        $courseconfig1->set_idmgrouptype_syncmode(idmgroup::IDM_GROUP_TYPE['class'], courseconfig::SYNCMODE_SYNC_ALL);
        $courseconfig1->set_idmgrouptype_syncmode(idmgroup::IDM_GROUP_TYPE['team'], courseconfig::SYNCMODE_DISABLED);
        $courseconfig1->store();

        // Test $user1 and $idmgroup1. We expect that the course should have set the sync flag.
        $this->assertFalse($courseconfig1->is_sync_needed());
        ob_start();
        utils::mark_courses_for_syncing($user1->id, $idmgroup1->get_externalid());
        ob_end_clean();
        $this->assertTrue($courseconfig1->is_sync_needed());

        // Reset course config.
        $courseconfig1->update_sync_state(courseconfig::NO_SYNC_NEEDED);

        // Same user, but with $idmgroup2 which is of type "team" which is disabled in course config. We expect the course not to
        // have the flag set.
        $this->assertFalse($courseconfig1->is_sync_needed());
        ob_start();
        utils::mark_courses_for_syncing($user1->id, $idmgroup2->get_externalid());
        ob_end_clean();
        $this->assertFalse($courseconfig1->is_sync_needed());

        // Now run function with $user2. This time $course1 should not be updated, because $user2 is not enrolled in this course.
        $this->assertFalse($courseconfig1->is_sync_needed());
        ob_start();
        utils::mark_courses_for_syncing($user2->id, $idmgroup1->get_externalid());
        ob_end_clean();
        $this->assertFalse($courseconfig1->is_sync_needed());

        // Now we change the sync modes. We define $idmgroup1 as "to be synced" in this course.
        $courseconfig1->set_idmgrouptype_syncmode(idmgroup::IDM_GROUP_TYPE['class'], courseconfig::SYNCMODE_DISABLED);
        $courseconfig1->set_idmgrouptype_syncmode(idmgroup::IDM_GROUP_TYPE['team'], courseconfig::SYNCMODE_DISABLED);
        $courseconfig1->set_idmgroups_to_sync([$idmgroup1->get_id()]);
        $courseconfig1->store();
        $courseconfig1->update_sync_state(courseconfig::NO_SYNC_NEEDED);

        // Now run the function with $user1 (which is enrolled) and $idmgroup1 (which should be synced). So we expect the flag to
        // be set.
        $this->assertFalse($courseconfig1->is_sync_needed());
        ob_start();
        utils::mark_courses_for_syncing($user1->id, $idmgroup1->get_externalid());
        ob_end_clean();
        $this->assertTrue($courseconfig1->is_sync_needed());

        // Reset course config.
        $courseconfig1->update_sync_state(courseconfig::NO_SYNC_NEEDED);

        // Now run the function with $user1 (which is enrolled) and $idmgroup2 (which should not be synced). So we expect the flag
        // not to be set.
        $this->assertFalse($courseconfig1->is_sync_needed());
        ob_start();
        utils::mark_courses_for_syncing($user1->id, $idmgroup2->get_externalid());
        ob_end_clean();
        $this->assertFalse($courseconfig1->is_sync_needed());

        // We now simulate a full sync of classes, then we disable the sync of classes.
        $courseconfig1->set_enabled(true);
        $courseconfig1->set_idmgrouptype_syncmode(idmgroup::IDM_GROUP_TYPE['class'], courseconfig::SYNCMODE_SYNC_ALL);
        $courseconfig1->set_idmgrouptype_syncmode(idmgroup::IDM_GROUP_TYPE['team'], courseconfig::SYNCMODE_DISABLED);
        $courseconfig1->store();
        $groupsyncer = new group_syncer($course1->id);
        $groupsyncer->sync_course();
        $courseconfig1->set_idmgrouptype_syncmode(idmgroup::IDM_GROUP_TYPE['class'], courseconfig::SYNCMODE_DISABLED);
        $courseconfig1->store();
        $courseconfig1->update_sync_state(courseconfig::NO_SYNC_NEEDED);
        $mappedcoursegroup = $courseconfig1->get_mapped_coursegroup($idmgroup1);
        $this->assertNotEmpty($mappedcoursegroup);
        // We now have the situation that we disabled the syncing, but groups are still mapped. So we expect the flag to be set so
        // that this mapped group can eventually be removed.
        $this->assertFalse($courseconfig1->is_sync_needed());
        ob_start();
        utils::mark_courses_for_syncing($user1->id, $idmgroup1->get_externalid());
        ob_end_clean();
        $this->assertTrue($courseconfig1->is_sync_needed());
    }

    /**
     * Test the function that creates the name of a course group.
     */
    public function test_get_coursegroupname_for_idmgroup(): void {
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
        $this->assertEquals('K - Klasse 5a (1234)', utils::get_coursegroupname_for_idmgroup($idmgroup));

        $idmgroup2 = idmgroup::create_from_groupinfo(
                '36855b1b-52bc-4767-8d70-a9d9275948ef=Video-AG',
                idmgroup::IDM_GROUP_TYPE['team'],
                $schoolid
        );
        $idmgroup2->store();
        $this->assertEquals('AG - Video-AG (1234)', utils::get_coursegroupname_for_idmgroup($idmgroup2));

        $idmgroup3 = idmgroup::create_from_groupinfo(
                '36855b1b-52bc-4767-8d70-a9d9275948ef=5ab_K1',
                idmgroup::IDM_GROUP_TYPE['classunit'],
                $schoolid
        );
        $idmgroup3->store();
        $this->assertEquals('U - 5ab_K1 (1234)', utils::get_coursegroupname_for_idmgroup($idmgroup3));
    }
}
