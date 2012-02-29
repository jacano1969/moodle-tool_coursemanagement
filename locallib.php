<?php

/**
 *
 * @global moodle_database $DB
 */
function coursemanagement_get_root_categories() {
    global $DB;

    
}

class coursemanagement_category {

    /**
     * @global moodle_database $DB
     */
    public static function get_root_categories($page, $limit = 50) {
        global $DB;

        list($select, $join) = context_instance_preload_sql('c.id', CONTEXT_COURSECAT, 'ctx');
        $sql = "SELECT c.id, c.name, c.path, c.parent, c.visible, cc.subcategorycount $select
                  FROM {course_categories} c
             LEFT JOIN (
                           SELECT cc.parent, COUNT(cc.id) AS subcategorycount
                             FROM {course_categories} cc
                         GROUP BY cc.parent
                       ) cc ON cc.parent = c.id
                       $join
                 WHERE c.parent = 0
              ORDER BY c.sortorder ASC";
        $rs = $DB->get_recordset_sql($sql, null, $page * $limit, $limit);
        $categories = array();
        foreach ($rs as $category) {
            context_instance_preload($category);
            $categories[$category->id] = new coursemanagement_category($category);
        }
        $rs->close();
        
        if (count($categories) < $limit) {
            $totalcount = count($categories);
        } else {
            $totalcount = $DB->count_records('course_categories', array('parent' => '0'));
        }
        
        return array($totalcount, $categories);
    }

    public static function get_category($categoryid) {
        global $DB;

        list($select, $join) = context_instance_preload_sql('c.id', CONTEXT_COURSECAT, 'ctx');
        $sql = "SELECT c.id, c.name, c.path, c.parent, c.coursecount, c.visible, cc.subcategorycount $select
                  FROM {course_categories} c
             LEFT JOIN (
                           SELECT cc.parent, COUNT(cc.id) AS subcategorycount
                             FROM {course_categories} cc
                         GROUP BY cc.parent
                       ) cc ON cc.parent = c.parent
                       $join
                 WHERE c.id = :categoryid
              ORDER BY c.sortorder ASC";
        $category = $DB->get_record_sql($sql, array('categoryid' => $categoryid), MUST_EXIST);
        $category = new coursemanagement_category($category);

        return $category;
    }

    protected $id;
    protected $name;
    protected $idnumber;
    protected $description;
    protected $descriptionformat;
    protected $parent;
    protected $sortorder;
    protected $coursecount;
    protected $visible;
    protected $visibleold;
    protected $timemodified;
    protected $depth;
    protected $path;
    protected $theme;

    protected $context;
    protected $subcategories = null;
    protected $courses = null;
    protected $subcategorycount = 0;

    protected function __construct(stdClass $data) {
        if (!isset($data->id) || !isset($data->name) || !isset($data->parent) || !isset($data->path)) {
            throw new coding_exception('You must provide id, name, parent, and path when constructing a coursemanagement_category');
        }

        $this->load_data($data);

        if (isset($data->context) && $data->context instanceof context) {
            $this->context = $data->context;
        } else {
            $this->context = context_coursecat::instance($this->id);
        }
    }

    protected function load_data(stdClass $data) {
        $this->id = $data->id;
        $this->name = $data->name;
        $this->parent = $data->parent;
        $this->path = $data->path;

        if (isset($data->idnumber))             $this->idnumber = $data->idnumber;
        if (isset($data->description))          $this->description = $data->description;
        if (isset($data->descriptionformat))    $this->descriptionformat = $data->descriptionformat;
        if (isset($data->sortorder))            $this->sortorder = $data->sortorder;
        if (isset($data->coursecount))          $this->coursecount = $data->coursecount;
        if (isset($data->visible))              $this->visible = $data->visible;
        if (isset($data->visibleold))           $this->visibleold = $data->visibleold;
        if (isset($data->timemodified))         $this->timemodified = $data->timemodified;
        if (isset($data->depth))                $this->depth = $data->depth;
        if (isset($data->path))                 $this->path = $data->path;
        if (isset($data->theme))                $this->theme = $data->theme;

        if (isset($data->subcategorycount))     $this->subcategorycount = $data->subcategorycount;
    }

    protected function ensure_data_loaded($field) {
        global $DB;
        if ($this->{$field} !== null) {
            return $this->{$field};
        }

        $sql = "SELECT c.*, cc.subcategorycount
                  FROM {course_categories} c
             LEFT JOIN (
                    SELECT cc.parent, COUNT(cc.id) AS subcategorycount
                      FROM {course_categories} cc
                  GROUP BY cc.parent
                       ) cc ON cc.parent = c.id
                 WHERE c.id = :categoryid";
        $category = $DB->get_record_sql($sql, array('categoryid' => $this->id), MUST_EXIST);
        $this->load_data($category);
        return $this->{$field};
    }

    public function get_id() {
        return $this->id;
    }

    public function get_name() {
        return $this->name;
    }

    public function get_idnumber() {
        return $this->ensure_data_loaded('idnumber');
    }

    public function get_description() {
        return $this->ensure_data_loaded('description');
    }

    public function get_descriptionformat() {
        return $this->ensure_data_loaded('descriptionformat');
    }

    public function get_parent() {
        return $this->ensure_data_loaded('parent');
    }

    public function get_sortorder() {
        return $this->ensure_data_loaded('sortorder');
    }

    public function get_coursecount() {
        return $this->ensure_data_loaded('coursecount');
    }

    public function get_visible() {
        return $this->ensure_data_loaded('visible');
    }

    public function get_visibleold() {
        return $this->ensure_data_loaded('visibleold');
    }

    public function get_timemodified() {
        return $this->ensure_data_loaded('timemodified');
    }

    public function get_depth() {
        return $this->ensure_data_loaded('depth');
    }

    public function get_path() {
        return $this->ensure_data_loaded('path');
    }

    public function get_theme() {
        return $this->ensure_data_loaded('theme');
    }

    public function get_subcategorycount() {
        return $this->ensure_data_loaded('subcategorycount');
    }

    public function has_courses() {
        return $this->get_coursecount() > 0;
    }

    public function has_subcategories() {
        return $this->get_subcategorycount() > 0;
    }

    public function has_subcategories_loaded() {
        return is_array($this->subcategories) && count($this->subcategories) > 0;
    }

    public function get_subcategories() {
        return $this->subcategories;
    }

    public function get_courses() {
        return $this->courses;
    }

    public function get_formatted_name() {
        return format_string($this->get_name(), true, array('context' => $this->context));
    }

    public function load_subcategories() {
        global $DB;

        list($select, $join) = context_instance_preload_sql('c.id', CONTEXT_COURSECAT, 'ctx');
        $sql = "SELECT c.id, c.name, c.path, c.parent, c.coursecount, cc.subcategorycount $select
                  FROM {course_categories} c
             LEFT JOIN (
                           SELECT cc.parent, COUNT(cc.id) AS subcategorycount
                             FROM {course_categories} cc
                         GROUP BY cc.parent
                       ) cc ON cc.parent = c.id
                       $join
                 WHERE c.parent = :categoryid
              ORDER BY c.sortorder ASC";
        $rs = $DB->get_recordset_sql($sql, array('categoryid' => $this->get_id()));
        $this->subcategories = array();
        foreach ($rs as $category) {
            context_instance_preload($category);
            $this->subcategories[$category->id] = new coursemanagement_category($category);
        }
        $rs->close();
        return true;
    }

    public function load_courses($page = 0) {
        $this->courses = coursemanagement_course::get_category_courses($this, $page);
        return true;
    }

    public function to_json($fullinfo = false) {

        $return = array(
            'id' => $this->get_id(),
            'name' => $this->get_formatted_name(),
            'path' => $this->get_path(),
            'parent' => $this->get_parent(),
            'coursecount' => $this->get_coursecount(),
            'subcategorycount' => $this->get_subcategorycount()
        );

        return $return;

    }

    public function move_course_in(coursemanagement_course $course) {
        global $CFG;
        require_once($CFG->dirroot.'/course/lib.php');
        if ($course->get_category()->can_move_course_from() && $this->can_move_course_in()) {
            move_courses(array($course->get_id()), $this->get_id());
            return true;
        }
        return false;
    }

    public function can_view() {
        return $this->get_visible() || has_capability('moodle/category:viewhiddencategories', context_coursecat::instance($this->get_parent()));
    }

    public function can_reordercourses() {
        return has_capability('moodle/category:manage', $this->context);
    }

    public function can_move_course_from() {
        return has_capability('moodle/category:manage', $this->context);
    }

    public function can_move_course_in() {
        return has_capability('moodle/category:manage', $this->context);
    }
}

class coursemanagement_course {

    public static function get_category_courses(coursemanagement_category $category, $page) {
        global $DB;

        list($select, $join) = context_instance_preload_sql('c.id', CONTEXT_COURSE, 'ctx');
        $sql = "SELECT c.id, c.shortname, c.fullname, c.idnumber, c.format, c.visible $select
                  FROM {course} c
                       $join
                 WHERE c.category = :categoryid
              ORDER BY c.sortorder ASC";

        $rs = $DB->get_recordset_sql($sql, array('categoryid' => $category->get_id()), $page * 50, 50);
        $courses = array();
        foreach ($rs as $course) {
            context_instance_preload($course);
            $courses[$course->id] = new coursemanagement_course($course, $category);
        }
        return $courses;
    }

    public static function get_course($courseid) {
        global $DB;

        list($select, $join) = context_instance_preload_sql('c.id', CONTEXT_COURSE, 'ctx');
        $sql = "SELECT c.id, c.shortname, c.fullname, c.idnumber, c.format, c.category, c.visible $select
                  FROM {course} c
                       $join
                 WHERE c.id = :courseid
              ORDER BY c.sortorder ASC";

        $course = $DB->get_record_sql($sql, array('courseid' => $courseid), MUST_EXIST);
        context_instance_preload($course);

        $category = coursemanagement_category::get_category($course->category);

        return new coursemanagement_course($course, $category);
    }

    protected $id;
    protected $category;
    protected $shortname;
    protected $fullname;
    protected $idnumber;
    protected $format;
    protected $visible;

    protected $courserecord;

    protected function __construct(stdClass $data, coursemanagement_category $category) {
        if (!isset($data->id) || !isset($data->shortname) || !isset($data->fullname) || !isset($data->idnumber) || !isset($data->format) || !isset($data->visible)) {
            throw new coding_exception('You must provide id, shortname, fullname, idnumber, format, visible when constructing a coursemanagement_course');
        }

        $this->load_data($data);
        $this->category = $category;

        if (isset($data->context) && $data->context instanceof context) {
            $this->context = $data->context;
        } else {
            $this->context = context_course::instance($this->id);
        }
    }

    protected function load_data(stdClass $data) {
        $this->id = $data->id;
        $this->shortname = $data->shortname;
        $this->fullname = $data->fullname;
        $this->idnumber = $data->idnumber;
        $this->format = $data->format;
        $this->visible = $data->visible;
        if (isset($data->modinfo)) {
            $this->courserecord = $data;
        }
    }

    public function get_id() {
        return $this->id;
    }

    public function get_shortname() {
        return $this->shortname;
    }

    public function get_fullname() {
        return $this->fullname;
    }

    public function get_idnumber() {
        return $this->idnumber;
    }

    public function get_format() {
        return $this->format;
    }

    public function get_category() {
        return $this->category;
    }

    public function get_visible() {
        return $this->visible;
    }

    public function get_formatted_shortname() {
        return format_string($this->get_shortname(), true, array('context' => $this->context));
    }

    public function get_formatted_fullname() {
        return format_string($this->get_fullname(), true, array('context' => $this->context));
    }

    public function to_json($fullinfo = false) {
        global $DB;

        $return = array(
            'id' => $this->get_id(),
            'shortname' => $this->get_formatted_shortname(),
            'fullname' => $this->get_formatted_fullname(),
            'idnumber' => $this->get_idnumber(),
            'format' => $this->get_format(),
            'category' => $this->get_category()->get_id()
        );

        if ($fullinfo) {

            $this->ensure_course_record_loaded();

            $return['summary'] = format_text($this->courserecord->summary, $this->courserecord->summaryformat, array('context' => $this->context));
            $return['actions'] = $this->get_actions();

            $modinfo = get_fast_modinfo($this->courserecord);
            //$return['debug'] = print_r($modinfo, true);

            $return['sections'] = array();
            $cms = $modinfo->get_cms();
            $sectionscms = $modinfo->get_sections();
            $sections = get_all_sections($this->courserecord->id);
            foreach ($sectionscms as $section => $cmarray) {
                $return['sections'][$section] = new stdClass;
                $return['sections'][$section]->name = get_section_name($this->courserecord, $sections[$section]);
               
                $return['sections'][$section]->modules = array();
                foreach ($cmarray as $cmid) {
                    $url = $cms[$cmid]->get_url();
                    if ($url instanceof moodle_url) {
                        $url = $url->out(false);
                    } else {
                        $url = '';
                    }

                    $icon = $cms[$cmid]->get_icon_url();
                    if ($icon instanceof moodle_url) {
                        $icon = $icon->out(false);
                    } else {
                        $icon = '';
                    }

                    $return['sections'][$section]->modules[$cmid] = array(
                        'name' => format_string($cms[$cmid]->name, true, array('context' => $cms[$cmid]->context)),
                        'url' => $url,
                        'icon' => $icon
                    );
                }
            }
        }

        return $return;
    }

    public function reorder_course_in_category($aftercourseid) {
        global $DB;

        $thiscourse = new stdClass;
        $thiscourse->id = $this->get_id();
        if ($aftercourseid !== 0) {
            $course = $DB->get_record('course', array('id' => $aftercourseid, 'category' => $this->get_category()->get_id()), 'id, sortorder, category', MUST_EXIST);
            $sql = "UPDATE {course} SET sortorder = sortorder + 1 WHERE category = :categoryid AND sortorder > :sortorder";
            $DB->execute($sql, array('categoryid' => $course->category, 'sortorder' => $course->sortorder));

            $thiscourse->sortorder = $course->sortorder + 1;
        } else {
            $sql = "SELECT MIN(c.sortorder) AS sortorder FROM {course} c WHERE c.category = :categoryid";
            $thiscourse->sortorder = $DB->get_field_sql($sql, array('categoryid' => $this->get_category()->get_id()));

            $sql = "UPDATE {course} SET sortorder = sortorder + 1 WHERE category = :categoryid";
            $DB->execute($sql, array('categoryid' => $this->get_category()->get_id()));
        }
        $DB->update_record('course', $thiscourse);
        fix_course_sortorder();
    }

    public function can_view() {
        return $this->get_visible() || has_capability('moodle/course:viewhiddencourses', $this->context);
    }

    public function ensure_course_record_loaded() {
        global $DB;

        if (!($this->courserecord instanceof stdClass)) {
            $this->courserecord = $DB->get_record('course', array('id' => $this->get_id()), '*', MUST_EXIST);
        }
    }

    public function get_actions() {
        global $CFG;
        require_once($CFG->dirroot.'/course/lib.php');

        $this->ensure_course_record_loaded();

        $actions = array();
        if (can_access_course($this->courserecord)) {
            $url = new moodle_url('/course/view.php', array('id'=>$this->courserecord->id));
            $actions['view'] = new stdClass;
            $actions['view']->icon = new pix_icon('i/search', get_string('view'));
            $actions['view']->url = $url->out();
        }

        if (has_capability('moodle/course:update', $this->context)) {
            $url = new moodle_url('/course/edit.php', array('id'=>$this->courserecord->id));
            $actions['edit'] = new stdClass;
            $actions['edit']->icon = new pix_icon('i/settings', get_string('editsettings'));
            $actions['edit']->url = $url->out();
        }

        if (has_capability('moodle/backup:backupcourse', $this->context)) {
            $url = new moodle_url('/backup/backup.php', array('id'=>$this->courserecord->id));
            $actions['backup'] = new stdClass;
            $actions['backup']->icon = new pix_icon('i/backup', get_string('backup'));
            $actions['backup']->url = $url->out();
        }

        if (has_capability('moodle/course:publish', $this->context)) {
            $url = new moodle_url('/course/publish/index.php', array('id'=>$this->courserecord->id));
            $actions['publish'] = new stdClass;
            $actions['publish']->icon = new pix_icon('i/publish', get_string('publish'));
            $actions['publish']->url = $url->out();
        }

        if (can_delete_course($this->courserecord->id)) {
            $url = new moodle_url('/course/delete.php', array('id'=>$this->courserecord->id));
            $actions['delete'] = new stdClass;
            $actions['delete']->icon = new pix_icon('t/delete', get_string('delete'));
            $actions['delete']->url = $url->out();
        }

        return $actions;
    }
}