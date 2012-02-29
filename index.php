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
 * Course management main page
 *
 * @package    tool
 * @subpackage coursemanagement
 * @copyright  2012 Sam Hemelryk
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(dirname(dirname(dirname(dirname(__FILE__)))) . '/config.php');
require_once($CFG->dirroot.'/'.$CFG->admin.'/tool/coursemanagement/locallib.php');

$page = optional_param('page', 0, PARAM_INT);

$url = new moodle_url('/admin/tool/coursemanagement/index.php');
if ($page) {
    $url->param('page', $url);
}

$context = get_system_context();
$PAGE->set_url($url);
$PAGE->set_context($context);

require_login();
require_capability('moodle/category:manage', $context);

list($totalrootcategories, $rootcategories) = coursemanagement_category::get_root_categories($page);

$PAGE->set_title(format_string($PAGE->course->fullname, true, $PAGE->context));
$PAGE->set_heading(get_string('coursecategorymanagement', 'tool_coursemanagement'));
$PAGE->set_pagelayout('admin');

$renderer = $PAGE->get_renderer('tool_coursemanagement');

echo $renderer->header();
echo $renderer->course_management($rootcategories, $totalrootcategories, $page);
echo $renderer->footer();