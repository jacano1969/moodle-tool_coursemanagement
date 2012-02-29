<?php

require(dirname(dirname(dirname(dirname(__FILE__)))) . '/config.php');
require_once($CFG->dirroot.'/'.$CFG->admin.'/tool/coursemanagement/locallib.php');

if (false) {
    $PAGE = new moodle_page;
    $DB = new pgsql_native_moodle_database;
    $OUTPUT = new core_renderer;
}

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