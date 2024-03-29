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
 * Course management AJAX actions
 *
 * @package    tool
 * @subpackage coursemanagement
 * @copyright  2012 Sam Hemelryk
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('AJAX_SCRIPT', true);

require(dirname(dirname(dirname(dirname(__FILE__)))) . '/config.php');
require_once($CFG->dirroot.'/'.$CFG->admin.'/tool/coursemanagement/locallib.php');

$action = required_param('action', PARAM_ALPHA);
require_login();
require_sesskey();

/**
 * This check is here because proper capability checks are not yet in place for
 * all actions.
 * To help prevent abuse if people try this tool out I've added this check to make
 * sure that only users with site config can try this tool.
 * @todo Fix capability checks and remove this check
 */
require_capability('moodle/site:config', get_system_context());

$PAGE->set_context(get_system_context());
$PAGE->set_url('/admin/tool/coursemanagement/ajax.php');

$outcome = array('success' => 0, 'error' => 'Unknown error');
try {
    switch ($action) {

        case 'getsubcategories':
            $categoryid = required_param('categoryid', PARAM_INT);
            $category = coursemanagement_category::get_category($categoryid);
            $category->load_subcategories();
            $outcome['success'] = 1;
            $outcome['error'] = '';
            $outcome['subcategories'] = new stdClass;
            $count = 0;
            foreach ($category->get_subcategories() as $subcategory) {
                if ($subcategory->can_view()) {
                    $outcome['subcategories']->{$count} = $subcategory->to_json();
                    $count++;
                }
            }
            break;

        case 'getcourses':
            $categoryid = required_param('categoryid', PARAM_INT);
            $category = coursemanagement_category::get_category($categoryid);
            $category->load_courses(optional_param('page', 0, PARAM_INT));
            $outcome['success'] = 1;
            $outcome['error'] = '';
            $outcome['courses'] = new stdClass;
            $count = 0;
            foreach ($category->get_courses() as $course) {
                if ($course->can_view()) {
                    $outcome['courses']->{$count} = $course->to_json();
                    $count++;
                }
            }
            break;

        case 'getcoursedetails':
            $courseid = required_param('courseid', PARAM_INT);
            $course = coursemanagement_course::get_course($courseid);
            if ($course->can_view()) {
                $outcome['success'] = 1;
                $outcome['error'] = '';
                $outcome['details'] = $course->to_json(true);
            } else {
                $outcome['error'] = 'You cannot view this course';
            }
            break;

        case 'reordercourse' :
            $courseid = required_param('courseid', PARAM_INT);
            $aftercourseid = required_param('aftercourseid', PARAM_INT);
            $course = coursemanagement_course::get_course($courseid);
            if ($course->can_view() && $course->get_category()->can_reordercourses()) {
                $course->reorder_course_in_category($aftercourseid);
            }
            $outcome['success'] = 1;
            $outcome['error'] = '';
            break;

        case 'movecourse':
            $courseid = required_param('courseid', PARAM_INT);
            $course = coursemanagement_course::get_course($courseid);

            $categoryid = required_param('categoryid', PARAM_INT);
            $category = coursemanagement_category::get_category($categoryid);

            if ($category->move_course_in($course)) {
                $outcome['success'] = 1;
                $outcome['error'] = '';
            } else {
                $outcome['error'] = 'Cannot move this course into the selected category';
            }
            
            break;

        case 'createcourse':

            $categoryid = required_param('categoryid', PARAM_INT);
            $fullname = required_param('fullname', PARAM_MULTILANG);
            $shortname = required_param('shortname', PARAM_MULTILANG);
            $idnumber = required_param('idnumber', PARAM_RAW);
            $summary = required_param('summary', PARAM_CLEANHTML);
            $courseformat = required_param('courseformat', PARAM_ALPHANUMEXT);

            $category = $DB->get_record('course_categories', array('id' => $categoryid), '*', MUST_EXIST);
            $context = context_coursecat::instance($categoryid);

            require_capability('moodle/course:create', $context);

            $courseformats = get_plugin_list('format');
            if (!array_key_exists($courseformat, $courseformats)) {
                $outcome['error'] = get_string('invalidformat', 'error');
                break;
            }

            $course = new stdClass;
            $course->category = $categoryid;
            $course->fullname = $fullname;
            $course->shortname = $shortname;
            $course->idnumber = $idnumber;
            $course->summary = $summary;
            $course->summaryformat = FORMAT_HTML;
            $course->format = $courseformat;

            require_once($CFG->dirroot.'/course/lib.php');
            $course = create_course($course);
            $context = context_course::instance($course->id);

            $outcome['success'] = '1';
            $outcome['error'] = '';
            $outcome['course'] = new stdClass;
            $outcome['course']->id = $course->id;
            $outcome['course']->idnumber = $course->idnumber;
            $outcome['course']->fullname = format_string($course->fullname, true, array('context' => $context));
            $outcome['course']->shortname = format_string($course->shortname, true, array('context' => $context));
            $outcome['course']->category = $course->category;
            break;

        default :
            $outcome['error'] = 'An unknown action was requested';
            break;

    }
} catch (coding_exception $ex) {
    $outcome['error'] = 'An unknown coding has exception occurred.';
    if (debugging()) {
        $outcome .= "\n" + $ex->getMessage();
    }
} catch (moodle_exception $ex) {
    $outcome['error'] = $ex->getMessage();
} catch (Exception $ex) {
    $outcome['error'] = 'An unknown PHP exception occurred.';
    if (debugging()) {
        $outcome .= "\n" + $ex->getMessage();
    }
}

if (isset($ex)) {
    $outcome['debug'] = $ex;
}

echo json_encode((object)$outcome);