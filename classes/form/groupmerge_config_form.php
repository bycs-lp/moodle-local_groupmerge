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

use core\output\html_writer;
use local_bycsauth\idmgroup;
use local_groupmerge\local\courseconfig;
use local_groupmerge\local\utils;

defined('MOODLE_INTERNAL') || die;

global $CFG;
require_once($CFG->libdir . '/formslib.php');

/**
 * Config form for the plugin local_groupmerge.
 *
 * @package    local_groupmerge
 * @copyright  2025 ISB Bayern
 * @author     Philipp Memmel
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class groupmerge_config_form extends \moodleform {

    /**
     * Form definition.
     */
    public function definition() {
        $mform = &$this->_form;
        $courseid = &$this->_customdata['courseid'];
        $mform->addElement('hidden', 'courseid', $courseid);
        $mform->setType('courseid', PARAM_INT);

        $currentgroups = groups_get_all_groups($courseid);

        $groupselectionarray = [];
        foreach ($currentgroups as $group) {
            $groupselectionarray[$group->id] = $group->name;
        }

        $repeatarray = [
                $mform->createElement('autocomplete', 'sourcegroupids', 'SOURCE GROUPS',
                        $groupselectionarray,
                        [
                                'multiple' => true,
                                'noselectionstring' => 'QUELLGRUPPEN AUSWÄHLEN',
                        ]
                ),
                $mform->createElement('select', 'targetgroupid', 'TARGET GROUP',
                        $groupselectionarray),
                $mform->createElement('submit', 'delete', get_string('delete'), [], false),
        ];

        $repeateloptions = [
                'targetgroupid' => [
                        'type' => PARAM_INT,
                        'helpbutton' => [
                                'targetgroupid',
                                'local_groupmerge',
                        ]
                ],
                'sourcegroupids' => [
                        'helpbutton' => [
                                'sourcegroupids',
                                'local_groupmerge',
                        ]
                ]
            /*                'limit' => [
                                    'default' => 0,
                                    //'disabledif' => ['limitanswers', 'eq', 0],
                                    'rule' => 'numeric',
                                    'type' => PARAM_INT,
                            ],
                            'option' => [
                                    'helpbutton' => [
                                            'choiceoptions',
                                            'choce',
                                    ]
                            ]*/
        ];

        $this->repeat_elements(
                $repeatarray,
                5,
                $repeateloptions,
                'option_repeats',
                'option_add_fields',
                3,
                'MEHR GROUPMAPPINGS HINZUFÜGEN',
                true,
                'delete',
        );

        $this->add_action_buttons();
    }

    /**
     * Some additional validation for the config form.
     *
     * @param array $data The data submitted by the form
     * @param array $files files submitted by the fom
     * @return array array of validation errors, if there are any
     */
    public function validation($data, $files): array {
        $errors = [];
        // TODO
        return $errors;
    }

}
