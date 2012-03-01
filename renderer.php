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
 * Output renderer for the course management tool
 *
 * @package    tool
 * @subpackage coursemanagement
 * @copyright  2012 Sam Hemelryk
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Rendering methods for the tool widgets
 */
class tool_coursemanagement_renderer extends plugin_renderer_base {

    public function course_management(array $rootcategories, $totalrootcategories, $page) {
        global $CFG;

        $this->page->requires->strings_for_js(array(
            'errorajaxunknown',
            'errorajaxjsonparse',
            'createnewcourse',
            'courseidnumber',
            'courseshortname',
            'coursefullname',
            'coursesummary',
            'courseformat',
            'submit',
            'sections'
        ), 'tool_coursemanagement');

        $config = new stdClass;
        $config->nodeid = 'course-management';
        $config->ajaxurl = $CFG->wwwroot.'/'.$CFG->admin.'/tool/coursemanagement/ajax.php';
        $config->formats = new stdClass;
        foreach (array_keys(get_plugin_list('format')) as $format) {
            $config->formats->$format = get_string('pluginname', 'format_'.$format);
        }
        $config->defaultformat = get_config('moodlecourse', 'format');

        $this->page->requires->yui_module('moodle-tool_coursemanagement-manager', 'M.tool_coursemanagement.init_manager', array($config));

        $html  = html_writer::start_tag('div', array('id' => 'course-management'));
        $html .= html_writer::start_tag('div', array('class' => 'course-management-wrap'));
        $html .= $this->category_tree_listing($rootcategories, $totalrootcategories, $page);
        $html .= $this->course_listing();
        $html .= $this->course_details();
        $html .= html_writer::end_tag('div');
        $html .= html_writer::end_tag('div');

        return $html;

    }

    protected function course_listing() {
        $html  = html_writer::start_tag('div', array('id' => 'course-listing', 'class' => 'page-0'));
        $html .= html_writer::start_tag('div', array('class' => 'course-listing-wrap'));

        $html .= html_writer::start_tag('div', array('class' => 'headings'));
        $html .= html_writer::tag('div', '<input type="checkbox" value="selectall" id="select-all-courses" />', array('class' => 'course-actions course-info'));
        $html .= html_writer::tag('div', get_string('courseidnumber', 'tool_coursemanagement'), array('class' => 'course-idnumber course-info'));
        $html .= html_writer::tag('div', get_string('courseshortname', 'tool_coursemanagement'), array('class' => 'course-shortname course-info'));
        $html .= html_writer::tag('div', get_string('coursefullname', 'tool_coursemanagement'), array('class' => 'course-fullname course-info'));
        $html .= html_writer::end_tag('div');
        $html .= html_writer::start_tag('div', array('class' => 'courses'));
        $html .= html_writer::end_tag('div');

        $html .= html_writer::end_tag('div');
        $html .= html_writer::end_tag('div');

        return $html;
    }

    protected function course_details() {
        $html  = html_writer::start_tag('div', array('id' => 'course-details', 'rel' => '0'));
        $html .= html_writer::start_tag('div', array('class' => 'course-details-wrap'));
        $html .= html_writer::tag('h3', get_string('coursedetails', 'tool_coursemanagement'), array('class' => 'heading'));
        $html .= html_writer::start_tag('div', array('class' => 'course-details-content'));
        $html .= html_writer::end_tag('div');
        $html .= html_writer::end_tag('div');
        $html .= html_writer::end_tag('div');

        return $html;
    }

    protected function category_tree_listing(array $categories, $totalcategories, $page) {

        $html  = html_writer::start_tag('div', array('id' => 'category-listing', 'class' => 'page-'.$page));
        $html .= html_writer::start_tag('div', array('class' => 'category-listing-wrap'));
        $html .= html_writer::tag('h3', get_string('categories', 'tool_coursemanagement'), array('class' => 'heading'));

        foreach ($categories as $category) {
            if ($category->can_view()) {
                $html .= $this->category_tree_item($category);
            }
        }

        if (count($categories) < $totalcategories) {
            $html .= html_writer::tag('div', get_string('loadmorecategories', 'tool_coursemanagement'), array('id' => 'category-load-more', 'rel' => $page));
        }

        $html .= html_writer::end_tag('div');
        $html .= html_writer::end_tag('div');

        return $html;

    }

    protected function category_tree_item(coursemanagement_category $category, $depth = 0) {

        $attributes = array(
            'id' => 'category-'.$category->get_id(),
            'rel' => $category->get_id(),
            'class' => 'category-item'
        );
        if ($category->has_courses()) {
            $attributes['class'] .= ' has-courses';
        }
        if ($category->has_subcategories()) {
            $attributes['class'] .= ' has-subcategories';
        }

        $html  = html_writer::start_tag('div', $attributes);
        $html .= html_writer::tag('div', $category->get_formatted_name(), array('class' => 'category-info'));
        $html .= html_writer::start_tag('div', array('class' => 'subcategory-listing'));
        if ($category->has_subcategories_loaded()) {
            foreach ($category->get_subcategories() as $subcategory) {
                $html .= $this->category_tree_item($subcategory, $depth + 1);
            }
        }
        $html .= html_writer::end_tag('div');
        $html .= html_writer::end_tag('div');

        return $html;
    }
}