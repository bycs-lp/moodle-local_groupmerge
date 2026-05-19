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

use context;
use context_course;
use core_form\dynamic_form;
use local_groupmerge\hook\restrict_target_groups;
use local_groupmerge\local\group_syncer;
use local_groupmerge\local\utils;
use local_groupmerge\output\unallowed_targetgroups_info;
use moodle_url;

/**
 * Dynamic form for creating/editing a group mapping.
 *
 * @package    local_groupmerge
 * @copyright  2026 ISB Bayern
 * @author     Philipp Memmel
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class mapping_form extends dynamic_form {
    /**
     * Form definition.
     */
    protected function definition() {
        global $CFG, $OUTPUT;
        require_once($CFG->dirroot . '/group/lib.php');

        $mform = $this->_form;

        $courseid = $this->optional_param('courseid', 0, PARAM_INT);
        $mappingid = $this->optional_param('mappingid', 0, PARAM_INT);

        $mform->addElement('hidden', 'courseid', $courseid);
        $mform->setType('courseid', PARAM_INT);

        $mform->addElement('hidden', 'mappingid', $mappingid);
        $mform->setType('mappingid', PARAM_INT);

        // Mapping name.
        $mform->addElement(
            'text',
            'name',
            get_string('mappingname', 'local_groupmerge')
        );
        $mform->setType('name', PARAM_TEXT);
        $mform->addHelpButton('name', 'mappingname', 'local_groupmerge');

        $groups = groups_get_all_groups($courseid);
        $groupoptions = [];
        foreach ($groups as $group) {
            $groupoptions[$group->id] = $group->name;
        }

        if ($mappingid > 0) {
            // Edit mode: target group is fixed and cannot be changed.
            $currenttargetgroupid = utils::get_targetgroupid_for_mappingid($mappingid);

            $mform->addElement('hidden', 'targetgroupid', $currenttargetgroupid);
            $mform->setType('targetgroupid', PARAM_INT);
            $mform->addElement(
                'static',
                'targetgroupid_display',
                get_string('targetgroupid', 'local_groupmerge'),
                $groupoptions[$currenttargetgroupid] ?? $currenttargetgroupid
            );
        } else {
            // Add mode: user selects the target group.
            // Dispatch hook to allow other plugins to restrict available target groups.
            $hook = new restrict_target_groups($courseid);
            \core\di::get(\core\hook\manager::class)->dispatch($hook);
            $unallowedtargetgroupids = $hook->get_unallowed_targetgroupids();
            $targetgroupoptions = array_diff_key($groupoptions, $unallowedtargetgroupids);

            $mform->addElement(
                'select',
                'targetgroupid',
                get_string('targetgroupid', 'local_groupmerge'),
                $targetgroupoptions
            );
            $mform->addHelpButton('targetgroupid', 'targetgroupid', 'local_groupmerge');
            $mform->addRule('targetgroupid', get_string('required'), 'required', null, 'client');
            $mform->setType('targetgroupid', PARAM_INT);

            // Show info about disallowed target groups grouped by reason.
            if (!empty($unallowedtargetgroupids)) {
                $unallowedtargetgroupsinfo = new unallowed_targetgroups_info($unallowedtargetgroupids, $groupoptions);
                $mform->addElement(
                    'static',
                    'unallowed_targetgroups_info',
                    get_string('unallowed_targetgroups', 'local_groupmerge'),
                    $OUTPUT->render($unallowedtargetgroupsinfo)
                );
            }
        }

        $mform->addElement(
            'autocomplete',
            'sourcegroupids',
            get_string('sourcegroupids', 'local_groupmerge'),
            $groupoptions,
            [
                'multiple' => true,
                'noselectionstring' => get_string('choosedots'),
            ]
        );
        $mform->addHelpButton('sourcegroupids', 'sourcegroupids', 'local_groupmerge');
        $mform->addRule('sourcegroupids', get_string('required'), 'required', null, 'client');

        $typeoptions = [
            group_syncer::TYPE_COVER => get_string('type_cover', 'local_groupmerge'),
            group_syncer::TYPE_SUBSET => get_string('type_subset', 'local_groupmerge'),
        ];
        $mform->addElement(
            'select',
            'type',
            get_string('mappingtype', 'local_groupmerge'),
            $typeoptions
        );
        $mform->addHelpButton('type', 'mappingtype', 'local_groupmerge');
        $mform->setDefault('type', group_syncer::TYPE_SUBSET);
        $mform->setType('type', PARAM_INT);
    }

    /**
     * Returns context where this form is used.
     *
     * @return context
     */
    protected function get_context_for_dynamic_submission(): context {
        $courseid = $this->optional_param('courseid', 0, PARAM_INT);
        if (empty($courseid)) {
            throw new \coding_exception('Course ID is required');
        }
        return context_course::instance($courseid);
    }

    /**
     * Checks if current user has access to this form, otherwise throws exception.
     */
    protected function check_access_for_dynamic_submission(): void {
        require_capability('local/groupmerge:manage', $this->get_context_for_dynamic_submission());
    }

    /**
     * Process the form submission, used if form was submitted via AJAX.
     *
     * @return array
     */
    public function process_dynamic_submission(): array {
        $data = $this->get_data();
        if (empty($data->courseid)) {
            throw new \coding_exception('Course ID is required');
        }
        $mappingid = (int) $data->mappingid;
        $targetgroupid = (int) $data->targetgroupid;
        $sourcegroupids = $data->sourcegroupids;
        $name = !empty($data->name) ? $data->name : null;
        if (!property_exists($data, 'type') || is_null($data->type)) {
            throw new \coding_exception('Mapping type is required und must not be null');
        }
        $type = (int) $data->type;

        if ($mappingid > 0) {
            utils::update_mapping($mappingid, $sourcegroupids, $type, $name);
        } else {
            utils::create_mapping((int) $data->courseid, $targetgroupid, $sourcegroupids, $type, $name);
        }

        $groupsyncer = new group_syncer($data->courseid);
        $groupsyncer->sync_group_members();

        return [];
    }

    /**
     * Load in existing data as form defaults.
     */
    public function set_data_for_dynamic_submission(): void {
        global $DB;

        $courseid = $this->optional_param('courseid', 0, PARAM_INT);
        $mappingid = $this->optional_param('mappingid', 0, PARAM_INT);

        $data = [
            'courseid' => $courseid,
            'mappingid' => $mappingid,
        ];

        if ($mappingid > 0) {
            $mapping = $DB->get_record('local_groupmerge_mapping', ['id' => $mappingid], '*', MUST_EXIST);
            $data['name'] = $mapping->name;
            $data['type'] = (int) $mapping->type;

            $sourcerecords = utils::get_sourcegroups_for_mapping($mappingid);
            $data['sourcegroupids'] = array_values(
                array_map(fn($record) => (int) $record->sourcegroupid, $sourcerecords)
            );

            $data['targetgroupid'] = utils::get_targetgroupid_for_mappingid($mappingid);
        }

        $this->set_data($data);
    }

    /**
     * Returns url to set in $PAGE->set_url() when form is being rendered or submitted via AJAX.
     *
     * @return moodle_url
     */
    protected function get_page_url_for_dynamic_submission(): moodle_url {
        $courseid = $this->optional_param('courseid', 0, PARAM_INT);
        return new moodle_url('/local/groupmerge/groupmerge_config.php', ['courseid' => $courseid]);
    }

    /**
     * Form validation.
     *
     * @param array $data
     * @param array $files
     * @return array
     */
    public function validation($data, $files): array {
        global $DB;

        $errors = parent::validation($data, $files);

        $targetgroupid = (int) $data['targetgroupid'];
        $sourcegroupids = array_map('intval', $data['sourcegroupids'] ?? []);
        $mappingid = (int) ($data['mappingid'] ?? 0);
        $courseid = (int) $data['courseid'];

        // At least one source group must be selected.
        if (empty($sourcegroupids)) {
            $errors['sourcegroupids'] = get_string('required');
        }

        // Target group must not be in source groups (direct self-loop).
        if (in_array($targetgroupid, $sourcegroupids)) {
            $errors['sourcegroupids'] = get_string('error_targetinsource', 'local_groupmerge');
        }

        // Add mode: the chosen target group must not already have a mapping.
        if ($mappingid === 0) {
            $existingtarget = $DB->record_exists('local_groupmerge_mapping', ['targetgroupid' => $targetgroupid]);
            if ($existingtarget) {
                $errors['targetgroupid'] = get_string('error_targetalreadymapped', 'local_groupmerge');
            }

            // Validate that the target group is not disallowed by a hook subscriber.
            $hook = new restrict_target_groups($courseid);
            \core\di::get(\core\hook\manager::class)->dispatch($hook);
            $unallowed = $hook->get_unallowed_targetgroupids();
            if (isset($unallowed[$targetgroupid])) {
                $errors['targetgroupid'] = get_string('error_target_unallowed', 'local_groupmerge', $unallowed[$targetgroupid]);
            }
        }

        // Check for transitive circular mappings (e.g. A->B, B->C, C->A).
        if (empty($errors['sourcegroupids'])) {
            $existingrecords = utils::get_mapping_records_for_course($courseid);

            $existingmappings = [];
            foreach ($existingrecords as $record) {
                // Skip mappings being replaced in edit mode.
                if ($mappingid > 0 && (int) $record->mappingid === $mappingid) {
                    continue;
                }
                $existingmappings[] = [
                    'sourcegroupid' => (int) $record->sourcegroupid,
                    'targetgroupid' => (int) $record->targetgroupid,
                ];
            }

            $newmappings = array_map(
                fn($sid) => ['sourcegroupid' => $sid, 'targetgroupid' => $targetgroupid],
                $sourcegroupids
            );

            if (utils::has_circular_mapping(array_merge($existingmappings, $newmappings))) {
                $errors['sourcegroupids'] = get_string('error_circular_mapping', 'local_groupmerge');
            }
        }

        return $errors;
    }
}
