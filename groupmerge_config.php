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

/**
 * Configuration page for local_groupmerge.
 *
 * @package    local_groupmerge
 * @copyright  2025 ISB Bayern
 * @author     Philipp Memmel
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use local_groupmerge\local\group_syncer;
use local_groupmerge\local\utils;

require_once(dirname(__FILE__) . '/../../config.php');
global $OUTPUT, $PAGE;

$courseid = required_param('courseid', PARAM_INT);
require_course_login($courseid);

$context = context_course::instance($courseid);
require_capability('local/groupmerge:use', $context);
$url = new moodle_url('/local/groupmerge/groupmerge_config.php', ['courseid' => $courseid]);
$PAGE->set_url($url);
$PAGE->set_context($context);
$PAGE->add_body_class('limitedwidth');

$strtitle = get_string('plugintitle', 'local_groupmerge');
$PAGE->set_title($strtitle);
$PAGE->set_heading($strtitle);
$PAGE->navbar->add($strtitle);

$returnurl = new moodle_url('/group/index.php', ['id' => $courseid]);



$groupmergeconfigform = new \local_groupmerge\form\groupmerge_config_form(null, ['courseid' => $courseid]);

// Standard form processing if statement.
if ($groupmergeconfigform->is_cancelled()) {
    redirect($returnurl);
} else if ($data = $groupmergeconfigform->get_data()) {
    utils::handle_config_form_data($data);

    redirect($PAGE->url, get_string('configupdated', 'local_groupmerge'));
}

echo $OUTPUT->header();
echo $OUTPUT->render_participants_tertiary_nav(get_course($courseid));

$groupmergeconfigform->set_data(utils::get_data_for_configform($courseid));
$groupmergeconfigform->display();

echo $OUTPUT->footer();
