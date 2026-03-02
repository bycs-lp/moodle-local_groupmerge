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
use stdClass;

/**
 * Utility class for local_groupmerge.
 *
 * @package   local_groupmerge
 * @copyright 2025 ISB Bayern
 * @author    Philipp Memmel
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class group_syncer {

    /** @var array Temporary storage for mapping the IDM groups to local course groups. */
    private array $groupsmapping = [];

    /** @var stdClass The course record of the course which this object is operating on. */
    private stdClass $course;
    /** @var courseconfig The courseconfig object, cached inside this object. */
    private courseconfig $courseconfig;

    /**
     * Creates the group syncer object which will perform the sync operations.
     *
     * @param int|stdClass $courseorid The id or the course object of the course which this object is operating on.
     */
    public function __construct(int|stdClass $courseorid) {
        $this->course = is_object($courseorid) ? $courseorid : get_course($courseorid);
        $this->courseconfig = new courseconfig($this->course->id);
    }

    /**
     * This will return all IDM groups of the users in the course.
     *
     * CARE: If the setting "enableclassunits" is disabled, there won't be any IDM groups of type "classunit" returned.
     *
     * @param bool $onlygroupstosync true, if only the groups that should be synced should be returned. Will return all IDM groups
     *  of users in this course if set to false. Defaults to true.
     *
     * @return array array of {@see \local_bycsauth\idmgroup} objects
     */
    public function get_idmgroups_of_course_users(bool $onlygroupstosync = true): array {
        global $DB;
        $usersincourse = get_enrolled_users(\context_course::instance($this->course->id));
        $userids = array_map(fn($user) => $user->id, array_filter($usersincourse, fn($user) => !empty($user->institution)));
        if (empty($userids)) {
            return [];
        }
        [$insql, $params] = $DB->get_in_or_equal($userids);
        $sql = "SELECT DISTINCT idmgroupid FROM {local_bycsauth_membership} WHERE userid " . $insql;
        $idmgroupids = $DB->get_records_sql($sql, $params);
        if (empty($idmgroupids)) {
            return [];
        }
        $idmgroupids = array_column($idmgroupids, 'idmgroupid');
        [$insql, $params] = $DB->get_in_or_equal($idmgroupids);
        $sql = "SELECT * FROM {local_bycsauth_idmgroup} WHERE id " . $insql;
        $idmgrouprecords = $DB->get_records_sql($sql, $params);
        $idmgroups = array_map(fn($idmgrouprecord) => idmgroup::create_from_record($idmgrouprecord), $idmgrouprecords);
        if (empty(get_config('local_groupmerge', 'enableclassunits'))) {
            $idmgroups = array_filter($idmgroups,
                    fn($idmgroup) => $idmgroup->get_idmgrouptype() !== idmgroup::IDM_GROUP_TYPE['classunit']);
        }
        if ($onlygroupstosync) {
            // An IDM group should be included in the result if the sync mode is "sync all groups of this IDM group type"
            // or if the sync mode is "only sync selected groups" of this IDM group type and the IDM group has been selected by
            // the user.
            if (!empty($this->courseconfig->get_idmgroups_to_sync())) {
                // This is custom sync mode.
                // Now get only groups that actually exist. The values in the course config might be outdated.
                [$insql, $params] = $DB->get_in_or_equal($this->courseconfig->get_idmgroups_to_sync());
                $select = "id $insql";
                return array_map(fn($record) => idmgroup::create_from_record($record),
                        $DB->get_records_select('local_bycsauth_idmgroup', $select, $params));
            } else {
                $idmgroups = array_filter($idmgroups,
                        fn($idmgroup) => $this->courseconfig->get_idmgrouptype_syncmode($idmgroup->get_idmgrouptype()) ===
                                courseconfig::SYNCMODE_SYNC_ALL);
            }
        }
        return $idmgroups;
    }

    /**
     * Syncs the course.
     *
     * This is the main method to do a full sync including creating/deleteing groups and memberships.
     */
    public function sync_course(): void {
        $syncstatebefore = $this->courseconfig->get_current_sync_state();
        if (!$this->courseconfig->is_enabled()) {
            // Nothing to do if idmgroupsync is disabled for this course.
            return;
        }

        // We do not react to the deletion of an IDM group because this would be pretty expensive. Instead, we just do an
        // "on demand" cleanup right here, before we sync the course.
        foreach ($this->courseconfig->get_idmgroups_to_sync() as $idmgroupid) {
            try {
                idmgroup::create_from_id($idmgroupid);
            } catch (\coding_exception $exception) {
                // This means the idmgroup does not exist, so we can remove it.
                $newidmgroupstosync = $this->courseconfig->get_idmgroups_to_sync();
                unset($newidmgroupstosync[$idmgroupid]);
                $this->courseconfig->set_idmgroups_to_sync($newidmgroupstosync);
            }
        }
        $this->courseconfig->store();

        // Sync IDM groups of users in the course to course groups. This also updates $this->groupsmapping.
        $this->sync_groups($this->courseconfig->get_cleanup_groups());
        // Now sync group members for each group.
        foreach ($this->groupsmapping as $mapping) {
            $this->sync_group_members($mapping[0], $mapping[1]);
        }
        $syncstateafter = $this->courseconfig->get_current_sync_state();
        if ($syncstateafter === $syncstatebefore) {
            $this->courseconfig->update_sync_state(courseconfig::NO_SYNC_NEEDED);
        }
    }

    /**
     * Method for taking over a subtask of {@see self::sync_course} which is syncing the groups.
     *
     * That means that for every IDM group that the course users have a corresponding course group is being created/deleted,
     * of course depending on the given configs. This method will not sync memberships, see {@see self::sync_group_members}.
     *
     * @param bool $removeunneeded if true, unneeded groups will be removed. "Unneeded" means groups without members or groups
     *  that have no related IDM group anymore.
     */
    public function sync_groups(bool $removeunneeded = false): void {
        global $CFG;
        require_once($CFG->dirroot . '/group/lib.php');

        // We reset the current groups mapping, because we refill it.
        $this->groupsmapping = [];
        $idmgroups = $this->get_idmgroups_of_course_users();
        $managedgroupids = $this->courseconfig->get_managed_groupids();
        foreach ($idmgroups as $idmgroup) {
            $mappedcoursegroup = $this->courseconfig->get_mapped_coursegroup($idmgroup);
            if (empty($mappedcoursegroup)) {
                // We have to create a new group.
                $mappedcoursegroup = $this->create_course_group_for_idmgroup($idmgroup);
            }
            $this->groupsmapping[] = [$idmgroup, $mappedcoursegroup];
            if (($key = array_search($mappedcoursegroup->id, $managedgroupids)) !== false) {
                unset($managedgroupids[$key]);
            }
        }

        // There is a chance that an IDM group has been deleted, but the corresponding group is still managed.
        foreach ($managedgroupids as $managedgroupid) {
            $managedgroup = groups_get_group($managedgroupid);
            try {
                if (!$managedgroup) {
                    // This typically should not happen, because the event listener for group_deleted event
                    // should clean up the mapping before we even get here. But production data shows that these
                    // cases exist, maybe due to migration.
                    throw new \coding_exception('Managed group does not exist');
                }
                utils::get_idmgroup_from_managed_group($managedgroup);
            } catch (\coding_exception $exception) {
                // IDM group does not exist (anymore), so let's remove it from the managed groups.
                if ($removeunneeded) {
                    groups_delete_group($managedgroupid);
                }
                // Make sure we remove the mapping entry, even though the group_deleted event observer should
                // have already done this in most cases.
                $this->courseconfig->remove_managed_groupid($managedgroupid);
            }
        }

        // Eventually remove course groups that are not needed anymore, because no enrolled user of this course is member
        // of the related IDM group.
        // It's not the default behavior, because this is not always a good idea, because when enrolling and unenrolling
        // one could be interested in keeping the groups because they are referenced in activities etc. and you are about to enrol
        // new users that again are members in this group. If you remove them and enrol new users that belong to the corresponding
        // IDM group, you will end up with a new group with the same name, but a different id and broken references in your course.
        if ($removeunneeded) {
            foreach ($managedgroupids as $managedgroupid) {
                if (empty(groups_get_members($managedgroupid))) {
                    // No need to update managedcoursegroups value in course config, because this is being done by the event
                    // listener for the group_deleted event.
                    groups_delete_group($managedgroupid);
                }
            }
        }
    }

    /**
     * Syncs the members of an IDM group to the corresponding course group.
     *
     * @param idmgroup $idmgroup The IDM group to sync the members from
     * @param stdClass $coursegroup the course group to which the memberships are being synced
     * @return void
     * @throws \coding_exception
     */
    public function sync_group_members(idmgroup $idmgroup, stdClass $coursegroup): void {
        $coursegroupusers = groups_get_members($coursegroup->id);
        $coursegroupuserids = array_map(fn($user) => $user->id, $coursegroupusers);
        $idmgroupuserids = $idmgroup->get_userids();
        foreach ($idmgroupuserids as $userid) {
            // We do not need to check if the user is enrolled, because groups_add_member is doing this for us.
            if (!in_array($userid, $coursegroupuserids)) {
                groups_add_member($coursegroup, $userid);
            }
            unset($coursegroupuserids[$userid]);
        }
        if (!empty($coursegroupuserids)) {
            foreach ($coursegroupuserids as $userid) {
                groups_remove_member($coursegroup, $userid);
            }
        }
    }

    /**
     * Creates the course group for a given IDM group.
     *
     * It will create a group, add this group as managed group and set a defined name including the IDM group name.
     * This method will not sync any memberships. It just creates a related course group.
     *
     * @param idmgroup $idmgroup The IDM group to create a course group for
     * @return stdClass the course group that has been created
     */
    public function create_course_group_for_idmgroup(idmgroup $idmgroup): stdClass {
        global $CFG;
        require_once($CFG->libdir . '/grouplib.php');
        require_once($CFG->dirroot . '/group/lib.php');
        $newgroupdata = new \stdClass();
        $newgroupdata->name = utils::get_coursegroupname_for_idmgroup($idmgroup);
        $newgroupdata->courseid = $this->course->id;
        $newgroupid = groups_create_group($newgroupdata);

        $this->courseconfig->add_managed_groupid($idmgroup->get_id(), $newgroupid);
        $newgroup = groups_get_group($newgroupid);
        return $newgroup;
    }

    /**
     * This method deletes all groups managed by this plugin in this course.
     */
    public function wipe_handled_groups(): void {
        global $CFG;
        require_once($CFG->dirroot . '/group/lib.php');

        $courseconfig = new courseconfig($this->course->id);
        foreach ($courseconfig->get_managed_groupids() as $groupid) {
            groups_delete_group($groupid);
            // We do not need to remove the group from the groupmapping table, because this will be done
            // by the groups_deleted event listener.
        }
    }
}
