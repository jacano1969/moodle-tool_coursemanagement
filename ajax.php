<?php

define('AJAX_SCRIPT', true);

require(dirname(dirname(dirname(dirname(__FILE__)))) . '/config.php');
require_once($CFG->dirroot.'/'.$CFG->admin.'/tool/coursemanagement/locallib.php');

$action = required_param('action', PARAM_ALPHA);
require_login();
require_sesskey();

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
            foreach ($category->get_subcategories() as $subcategory) {
                if ($subcategory->can_view()) {
                    $outcome['subcategories']->{$subcategory->get_id()} = $subcategory->to_json();
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
            foreach ($category->get_courses() as $course) {
                if ($course->can_view()) {
                    $outcome['courses']->{$course->get_id()} = $course->to_json();
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