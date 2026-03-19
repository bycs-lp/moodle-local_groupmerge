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
        $mform = $this->_form;

        $courseid = $this->optional_param('courseid', 0, PARAM_INT);
        $targetgroupid = $this->optional_param('targetgroupid', 0, PARAM_INT);

        $mform->addElement('hidden', 'courseid', $courseid);
        $mform->setType('courseid', PARAM_INT);

        $mform->addElement('hidden', 'targetgroupid_original', $targetgroupid);
        $mform->setType('targetgroupid_original', PARAM_INT);

        $groups = groups_get_all_groups($courseid);
        $groupoptions = [];
        foreach ($groups as $group) {
            $groupoptions[$group->id] = $group->name;
        }

        $mform->addElement(
            'select',
            'targetgroupid',
            get_string('targetgroupid', 'local_groupmerge'),
            $groupoptions
        );
        $mform->addHelpButton('targetgroupid', 'targetgroupid', 'local_groupmerge');
        $mform->addRule('targetgroupid', get_string('required'), 'required', null, 'client');
        $mform->setType('targetgroupid', PARAM_INT);

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
    }

    /**
     * Returns context where this form is used.
     *
     * @return context
     */
    protected function get_context_for_dynamic_submission(): context {
        $courseid = $this->optional_param('courseid', 0, PARAM_INT);
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
    public function process_dynamic_submission() {
        global $DB;

        $data = $this->get_data();
        $clock = \core\di::get(\core\clock::class);
        $currenttime = $clock->time();
        $targetgroupidoriginal = (int) $data->targetgroupid_original;
        $targetgroupid = (int) $data->targetgroupid;
        $sourcegroupids = $data->sourcegroupids;

        // If editing an existing mapping, remove old records for the original target group.
        if ($targetgroupidoriginal > 0) {
            $DB->delete_records('local_groupmerge_groupmapping', ['targetgroupid' => $targetgroupidoriginal]);
        }

        // Insert new mapping records.
        foreach ($sourcegroupids as $sourcegroupid) {
            $record = new \stdClass();
            $record->sourcegroupid = (int) $sourcegroupid;
            $record->targetgroupid = $targetgroupid;
            $record->timecreated = $currenttime;
            $record->timemodified = $currenttime;
            $DB->insert_record('local_groupmerge_groupmapping', $record);
        }

        return [];
    }

    /**
     * Load in existing data as form defaults.
     */
    public function set_data_for_dynamic_submission(): void {
        global $DB;

        $targetgroupid = $this->optional_param('targetgroupid', 0, PARAM_INT);
        $data = [
            'courseid' => $this->optional_param('courseid', 0, PARAM_INT),
            'targetgroupid_original' => $targetgroupid,
        ];

        if ($targetgroupid > 0) {
            $data['targetgroupid'] = $targetgroupid;
            $records = $DB->get_records('local_groupmerge_groupmapping', ['targetgroupid' => $targetgroupid]);
            $data['sourcegroupids'] = array_values(
                array_map(fn($record) => $record->sourcegroupid, $records)
            );
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
        $errors = parent::validation($data, $files);

        $targetgroupid = (int) $data['targetgroupid'];
        $sourcegroupids = $data['sourcegroupids'] ?? [];

        // Target group must not be in source groups.
        if (in_array($targetgroupid, array_map('intval', $sourcegroupids))) {
            $errors['sourcegroupids'] = get_string('error_targetinsource', 'local_groupmerge');
        }

        return $errors;
    }
}
