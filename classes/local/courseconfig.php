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
 * Course config wrapper class for the course config entry in local_groupmerge_config.
 *
 * @package   local_groupmerge
 * @copyright 2025 ISB Bayern
 * @author    Philipp Memmel
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class courseconfig {

    /** @var int Constant representing that the local_groupmerge plugin is enabled for this course. */
    const ENABLED = 1;
    /** @var int Constant representing that the local_groupmerge plugin is disabled for this course. */
    const DISABLED = 0;

    /** @var int Constant representing that the current course does not need to be synced. */
    const NO_SYNC_NEEDED = 0;
    /** @var int Constant representing that the current course needs to be synced. */
    const SYNC_NEEDED = 1;

    /** @var int Constant representing that an IDM group type should not be synced. */
    const SYNCMODE_DISABLED = 0;

    /** @var int Constant representing that an IDM group type should be synced. */
    const SYNCMODE_SYNC_ALL = 1;

    /** @var int The course id of the course which this courseconfig object is handling. */
    private int $courseid;

    /** @var ?stdClass The database record if available. */
    private ?stdClass $record = null;

    /** @var bool Variable representing if a record exists yet. */
    private bool $recordexists = false;

    /**
     * Simple constructor for this courseconfig object.
     *
     * It sets the course id of the course this object is handling and tries to load the database record if it exists yet.
     *
     * @param int $courseid The course id of the course being managed
     */
    public function __construct(int $courseid) {
        $this->courseid = $courseid;
        $this->load_from_db();
    }

    /**
     * Loads the database record into the object.
     *
     * If no record exists the object will just recognize this fact.
     */
    public function load_from_db(): void {
        global $DB;

        $dbrecord = $DB->get_record('local_groupmerge_config', ['courseid' => $this->courseid]);
        $this->record = $dbrecord ?: null;
        $this->recordexists = $dbrecord !== false;
    }

    /**
     * Checks if the courseconfig record already exists.
     *
     * If no record has been loaded yet, this method will try to load a record.
     *
     * @param bool $reload check again if a record is there since creation of the object
     * @return bool true if the record exists, false otherwise.
     */
    public function record_exists($reload = true): bool {
        if (!$reload) {
            return $this->recordexists;
        }

        $this->load_from_db();
        return $this->recordexists;
    }

    /**
     * Standard getter for the enabled flag for this course.
     *
     * @return bool true if the plugin is enabled for this course, false otherwise
     */
    public function is_enabled(): bool {
        return !empty($this->record->enabled);
    }

    /**
     * Getter for the sync mode of the passed IDM group type.
     *
     * @param int $idmgrouptype The idmgroup type, see {@see idmgroup::IDM_GROUP_TYPE}
     * @return int either {@see self::SYNCMODE_DISABLED} or {@see self::SYNCMODE_ENABLED}
     * @throws \coding_exception if an unknown IDM group type has been passed
     */
    public function get_idmgrouptype_syncmode(int $idmgrouptype): int {
        switch ($idmgrouptype) {
            case idmgroup::IDM_GROUP_TYPE['class']:
                return empty($this->record->class) ? self::SYNCMODE_DISABLED : intval($this->record->class);
            case idmgroup::IDM_GROUP_TYPE['team']:
                return empty($this->record->team) ? self::SYNCMODE_DISABLED : intval($this->record->team);
            case idmgroup::IDM_GROUP_TYPE['classunit']:
                return empty($this->record->classunit) ? self::SYNCMODE_DISABLED : intval($this->record->classunit);
            default:
                throw new \coding_exception('Unknown IDM group type');
        }
    }

    /**
     * Returns an array of ids of all course groups that are currently being managed by this plugin.
     *
     * This method queries directly the database.
     *
     * @return array of ids of course groups that are being managed by this plugin
     */
    public function get_managed_groupids(): array {
        global $DB;
        return array_map(fn($groupid) => intval($groupid),
                $DB->get_fieldset('local_groupmerge_groupmapping', 'coursegroupid', ['courseid' => $this->courseid]));
    }

    /**
     * Setter for the sync mode of the passed IDM group type.
     *
     * @param int $idmgrouptype The idmgroup type, see {@see idmgroup::IDM_GROUP_TYPE}
     * @param int $syncmode either {@see self::SYNCMODE_DISABLED} or {@see self::SYNCMODE_ENABLED}
     * @throws \coding_exception if a wrong sync mode or an unkown IDM group type has been passed
     */
    public function set_idmgrouptype_syncmode(int $idmgrouptype, int $syncmode): void {
        if (!in_array($syncmode, [self::SYNCMODE_DISABLED, self::SYNCMODE_SYNC_ALL])) {
            throw new \coding_exception('Wrong sync mode');
        }
        if ($this->record === null) {
            $this->record = new stdClass();
        }
        switch ($idmgrouptype) {
            case idmgroup::IDM_GROUP_TYPE['class']:
                $this->record->class = $syncmode;
                break;
            case idmgroup::IDM_GROUP_TYPE['team']:
                $this->record->team = $syncmode;
                break;
            case idmgroup::IDM_GROUP_TYPE['classunit']:
                $this->record->classunit = $syncmode;
                break;
            default:
                throw new \coding_exception('Unknown IDM group type');
        }
    }

    /**
     * Setter for the enabled state of this plugin for this course.
     *
     * @param bool $enabled true if the plugin should be enabled for this course, false otherwise
     */
    public function set_enabled(bool $enabled): void {
        if ($this->record === null) {
            $this->record = new stdClass();
        }
        $this->record->enabled = $enabled ? self::ENABLED : self::DISABLED;
        if (!$enabled) {
            // If we disable the feature we will not continue to sync anything.
            $this->record->syncstate = self::NO_SYNC_NEEDED;
        }
    }

    /**
     * Setter for the parameter cleanupgroups.
     *
     * This specifies if during the sync process empty course groups and groups that have no related IDM group anymore, are being
     * removed or not.
     *
     * @param bool $cleanupgroups true if empty course groups and groups without related IDM group should be removed during the
     *         sync process
     */
    public function set_cleanup_groups(bool $cleanupgroups): void {
        if ($this->record === null) {
            $this->record = new stdClass();
        }
        $this->record->cleanupgroups = $cleanupgroups ? self::ENABLED : self::DISABLED;
    }

    /**
     * Getter for the cleanupgroups parameter.
     *
     * This specifies if during the sync process empty course groups and groups that have no related IDM group anymore, are being
     *  removed or not.
     *
     * @return bool true if groups are being removed, false otherwise
     */
    public function get_cleanup_groups(): bool {
        return !empty($this->record->cleanupgroups);
    }

    /**
     * Use this method to persist the {@see \local_groupmerge\local\courseconfig} object to the database.
     *
     * This method will overwrite the current record in the database with the values in the object.
     */
    public function store(): void {
        global $DB;
        $clock = \core\di::get(\core\clock::class);
        if ($this->record === null) {
            $this->record = new stdClass();
        }
        $this->record->courseid = $this->courseid;
        $currentrecord = $DB->get_record('local_groupmerge_config', ['courseid' => $this->courseid]);
        $this->record->timemodified = $clock->time();
        if (!$currentrecord) {
            $this->record->timecreated = $this->record->timemodified;
            $DB->insert_record('local_groupmerge_config', $this->record);
        } else {
            $this->record->id = $currentrecord->id;
            $DB->update_record('local_groupmerge_config', $this->record);
        }
    }

    /**
     * Updates the state of a courseconfig as "needs to be synced" or "does not need to be synced".
     *
     * CARE: This will always directly operate on the database and ignore what's currently loaded as courseconfig object. So you
     * probably want to call {@see self::store()} before if made any changes to the courseconfig.
     *
     * @param int $newsyncstate The new sync state, either {@see self::NO_SYNC_NEEDED} or {@see self::SYNC_NEEDED}
     * @throws \coding_exception in case there is no record yet or an invalid sync state has been passed
     */
    public function update_sync_state(int $newsyncstate): void {
        global $DB;

        if (!$this->record_exists()) {
            throw new \coding_exception('No course config record for course id ' . $this->courseid);
        }
        // We wrap the update call in a lock to avoid race conditions when incrementing the syncstate field. Unfortunately,
        // we do not have an API for the database layer to lock a single record.
        $lockkey = 'syncstate-' . $this->courseid;
        $lockfactory = \core\lock\lock_config::get_lock_factory('local_groupmerge');

        // Updating a sync state should be pretty fast, a timeout of 5 seconds should be ok.
        $updatesyncstatelock = $lockfactory->get_lock($lockkey, 5, MINSECS);
        if (!$updatesyncstatelock) {
            debugging('Could not retrieve sync state update lock for course with id ' . $this->courseid
                    . '. Will not be able to set the course to state ' . $newsyncstate);
            return;
        }

        $exception = null;
        try {
            switch ($newsyncstate) {
                case self::NO_SYNC_NEEDED:
                    $DB->execute("UPDATE {local_groupmerge_config} SET syncstate = :syncstate WHERE courseid = :courseid",
                            ['syncstate' => self::NO_SYNC_NEEDED, 'courseid' => $this->courseid]);
                    break;
                case self::SYNC_NEEDED:
                    $DB->execute("UPDATE {local_groupmerge_config} SET syncstate = syncstate + 1 WHERE courseid = :courseid",
                            ['courseid' => $this->courseid]);
                    break;
                default:
                    throw new \coding_exception('Sync state to which should be updated has '
                            . 'to be either NO_SYNC_NEEDED or SYNC_NEEDED');
            }
        } catch (\Exception $thrownexception) {
            $exception = $thrownexception;
        } finally {
            $updatesyncstatelock->release();
            if (!is_null($exception)) {
                throw $exception;
            }
        }
    }

    /**
     * Helper function to add an IDM group <-> course group mapping for this course.
     *
     * @param int $idmgroupid the id of the IDM group
     * @param int $groupid the id of the course group
     */
    public function add_managed_groupid(int $idmgroupid, int $groupid): void {
        global $DB;
        $clock = \core\di::get(\core\clock::class);
        $record = $DB->get_record('local_groupmerge_groupmapping', ['courseid' => $this->courseid, 'coursegroupid' => $groupid]);
        if (!empty($record)) {
            return;
        }
        $record = $DB->get_record('local_groupmerge_groupmapping', ['courseid' => $this->courseid, 'idmgroupid' => $idmgroupid]);
        if (!empty($record)) {
            return;
        }
        $record = new \stdClass();
        $record->courseid = $this->courseid;
        $record->idmgroupid = $idmgroupid;
        $record->coursegroupid = $groupid;
        $record->timecreated = $clock->time();
        $record->timemodified = $clock->time();
        $DB->insert_record('local_groupmerge_groupmapping', $record);
    }

    /**
     * Removes the course group id from the table of managed course group ids.
     *
     * The removed group will no longer being managed by this plugin.
     *
     * @param int $groupid
     */
    public function remove_managed_groupid(int $groupid): void {
        global $DB;
        $DB->delete_records('local_groupmerge_groupmapping', ['courseid' => $this->courseid, 'coursegroupid' => $groupid]);
    }

    /**
     * Checks the courseconfig record if the related course needs to be synced.
     *
     * CARE: This will always directly query the database and ignore what's currently loaded as courseconfig object.
     */
    public function is_sync_needed(): bool {
        return $this->get_current_sync_state() > self::NO_SYNC_NEEDED;
    }

    /**
     * Retrieves the current syncstate from the courseconfig record.
     *
     * CARE: This will always directly query the database and ignore what's currently loaded as courseconfig object.
     * @return int will return either {@see self::SYNC_NEEDED} or {@see self::NO_SYNC_NEEDED}; in case of a not yet existing
     *  database record it will return {@see self::NO_SYNC_NEEDED}
     */
    public function get_current_sync_state(): int {
        global $DB;
        $courseconfigrecord = $DB->get_record('local_groupmerge_config', ['courseid' => $this->courseid]);

        return $courseconfigrecord ? intval($courseconfigrecord->syncstate) : self::NO_SYNC_NEEDED;
    }

    /**
     * Setter for the IDM groups that should be synced.
     *
     * This will set the IDM groups that should be synced. This is an alternative approach to setting the IDM group type.
     * Either you set {@see self::SYNCMODE_SYNC_ALL} to the IDM group types or you specify IDM groups which should be synced by
     * using this function. Whenever IDM groups are specified, the IDM group state will be ignored.
     *
     * @param array $idmgroupids array of IDM group ids for which course groups should be created
     */
    public function set_idmgroups_to_sync(array $idmgroupids): void {
        if ($this->record === null) {
            $this->record = new stdClass();
        }
        if (empty($idmgroupids)) {
            $this->record->idmgroupstosync = '';
        } else {
            $this->record->idmgroupstosync = implode(';', $idmgroupids);
        }
    }

    /**
     * Getter for the IDM groups that should be synced.
     *
     * Will only return the idmgroups that are being stored in courseconfig in case of *custom sync mode*.
     *
     * @return array of integer ids of the IDM groups that should be synced
     */
    public function get_idmgroups_to_sync(): array {
        if (empty($this->record->idmgroupstosync)) {
            return [];
        }
        return array_map('intval', explode(';', $this->record->idmgroupstosync));
    }

    /**
     * Returns if we are in custom sync mode.
     *
     * Custom sync mode means that we do not sync *all* classes, teams, classunits, but the user has selected specific
     * IDM groups that should be synced.
     *
     * @return bool true if the syncmode is "custom", false otherwise
     */
    public function is_syncmode_custom(): bool {
        return !empty($this->record->idmgroupstosync);
    }

    /**
     * Helper function to determine the currently actively synced group ids.
     *
     * Background: Select an IDM group, let it sync, then disable the IDM group.
     * The associated course group will then not be removed to avoid damage to the course. However, it is still being
     * managed by local_groupmerge, because it could potentially be reactivated. This function does not return the ids
     * of those "leftover" groups.
     *
     * @param bool $returnidmgroupids if set to true the function will return an array of IDM group ids, if false it will return the
     *  ids of the corresponding course groups
     * @return array array of ids with course group ids that are *actively* being synced by local_groupmerge
     */
    public function get_currently_synced_groupids(bool $returnidmgroupids = true): array {
        if (!$this->is_enabled()) {
            return [];
        }
        $groupmappings = utils::get_group_mappings($this->courseid);
        $coursegroups = [];
        foreach ($groupmappings as $groupmapping) {
            try {
                $idmgroup = idmgroup::create_from_id($groupmapping->idmgroupid);
            } catch (\Exception $e) {
                // In case of an exception there is no related IDM group anymore. So this
                // group is not being synced and we can check the next one.
                continue;
            }
            if ($this->is_syncmode_custom()) {
                if (in_array($groupmapping->idmgroupid, $this->get_idmgroups_to_sync())) {
                    $coursegroups[] = $returnidmgroupids ? $groupmapping->idmgroupid : $groupmapping->coursegroupid;
                }
            } else {
                if (
                        ($idmgroup->get_idmgrouptype() === idmgroup::IDM_GROUP_TYPE['class'] &&
                                $this->get_idmgrouptype_syncmode(idmgroup::IDM_GROUP_TYPE['class']) === self::SYNCMODE_SYNC_ALL) ||
                        ($idmgroup->get_idmgrouptype() === idmgroup::IDM_GROUP_TYPE['team'] &&
                                $this->get_idmgrouptype_syncmode(idmgroup::IDM_GROUP_TYPE['team']) === self::SYNCMODE_SYNC_ALL) ||
                        ($idmgroup->get_idmgrouptype() === idmgroup::IDM_GROUP_TYPE['classunit'] &&
                                $this->get_idmgrouptype_syncmode(idmgroup::IDM_GROUP_TYPE['classunit']) ===
                                self::SYNCMODE_SYNC_ALL)
                ) {
                    $coursegroups[] = $returnidmgroupids ? $groupmapping->idmgroupid : $groupmapping->coursegroupid;
                }
            }
        }
        return $coursegroups;
    }

    /**
     * Helper wrapper function to check if a course group is currently being managed by this plugin.
     *
     * @param int $groupid the id of the course group to check
     * @return bool true if the group is managed by this plugin, false otherwise
     */
    public function is_coursegroup_managed(int $groupid): bool {
        return in_array($groupid, $this->get_managed_groupids());
    }

    /**
     * Returns the mapped course group in this course for an IDM group.
     *
     * @param idmgroup $idmgroup the IDM group to return the course group for
     * @return stdClass|false the course group, or false if there is no related course group
     */
    public function get_mapped_coursegroup(idmgroup $idmgroup): stdClass|false {
        global $CFG, $DB;
        require_once($CFG->libdir . '/grouplib.php');
        $groupmapping = $DB->get_record('local_groupmerge_groupmapping',
                ['courseid' => $this->courseid, 'idmgroupid' => $idmgroup->get_id()]);
        if (!$groupmapping) {
            return false;
        }
        return groups_get_group($groupmapping->coursegroupid);
    }

    /**
     * Deletes all relevant database entries for this courseconfig object.
     */
    public function delete(): void {
        global $DB;
        $DB->delete_records('local_groupmerge_config', ['courseid' => $this->courseid]);
        $DB->delete_records('local_groupmerge_groupmapping', ['courseid' => $this->courseid]);
    }
}
