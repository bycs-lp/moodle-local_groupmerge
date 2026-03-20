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
     * Tests the event handler for the {@see group_deleted} event.
     *
     * @covers \local_groupmerge\local\observers::group_deleted
     */
    public function test_group_deleted(): void {
        global $CFG;
        require_once($CFG->dirroot . '/group/lib.php');
        $this->resetAfterTest();

        // TODO Implement
    }

    /**
     * Tests the event handler for the {@see course_deleted} event.
     *
     * @covers \local_groupmerge\local\observers::course_deleted
     * @covers \local_groupmerge\local\courseconfig::delete
     */
    public function test_course_deleted(): void {
        $this->resetAfterTest();

        // TODO Implement
    }
}
