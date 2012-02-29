var reqs = {requires:['base', 'node', 'node-event-delegate', 'io-base', 'json-parse', 'dd-delegate', 'dd-proxy', 'dd-constrain', 'anim', 'dd-drop-plugin']};

YUI.add('moodle-tool_coursemanagement-manager', function(Y) {

var c = Y.Node.create;

var Manager = function(config) {
    Manager.superclass.constructor.apply(this, [config]);
}
Manager.prototype = {
    domresources : null,
    lightbox : null,
    initializer : function(config) {
        var node = Y.one('#'+this.get('nodeid'));
        node.addClass('state-initialising');
        this.set('node', node);
        this.set('categorylisting', new CategoryListing({'node' : node.one('#category-listing'), 'manager' : this}));
        this.set('courselisting', new CourseListing({'node' : node.one('#course-listing'), 'manager' : this}));
        this.set('coursedragdrop', new CourseDragDrop({'manager' : this}));

        this.domresources = c('<div class="course-manager-resources"></div>');
        this.lightbox = c('<div class="loading-lightbox"></div>');
        Y.one(document.body).append(this.domresources.append(this.lightbox));

        node.replaceClass('state-initialising', 'state-initialised');
    },
    show_processing_mask : function() {
        this.lightbox.addClass('in-use');
    },
    hide_processing_mask : function() {
        this.lightbox.removeClass('in-use');
    }
}
Y.extend(Manager, Y.Base, Manager.prototype, {
    NAME : 'manager',
    ATTRS : {
        ajaxurl : {},
        nodeid : {},
        node : {},
        categorylisting : {},
        courselisting : {},
        coursedragdrop : {},
        formats : {},
        defaultformat : {}
    }
});

var CourseDragDrop = function(config) {
    CourseDragDrop.superclass.constructor.apply(this, [config]);
}
CourseDragDrop.prototype = {
    goingup : false,
    goingleft : false,
    lastx : 0,
    lasty : 0,
    lastdrop : null,
    order : null,
    cancelreorder : false,
    initializer : function(config) {
        var node = this.get('manager').get('courselisting').get('node');
        this.set('node', node);

        this.order = [];
        this.get('node').all('.course-item').each(function(n){
            this.order.push(n.getAttribute('rel'));
        }, this);

        var dddelegate = new Y.DD.Delegate({
            container: node, //The common container
            nodes: '.course-item', //The items to make draggable
            useShim : true,
            target : {
                padding : '0 0 0 20'
            }
        });

        dddelegate.dd.plug(Y.Plugin.DDProxy, {
            moveOnEnd: false
        });

        dddelegate.dd.plug(Y.Plugin.DDConstrained, {
            constrain2node: this.get('manager').get('node')
        }); 

        this.set('dddelegate', dddelegate);

        dddelegate.on('drag:start', this.drag_start, this);
        dddelegate.on('drag:end', this.drag_end, this);
        dddelegate.on('drag:drag', this.drag_drag, this);
        dddelegate.on('drag:dropmiss', this.drag_dropmiss, this);
        dddelegate.on('drag:over', this.drag_over, this);

        this.get('manager').get('categorylisting').get('node').all('.category-item .category-info').each(this.prepare_category_for_drop, this);
    },
    prepare_category_for_drop : function(category) {
        if (category.hasClass('category-item')) {
            category = category.one('.category-info');
        }
        if (!category || !category.hasClass('category-info')) {
            return;
        }
        category.plug(Y.Plugin.Drop);
        category.drop.on('drop:hit', this.drop_hit, this);
        category.drop.on('drop:enter', function(e, category){category.addClass('dragging-over')}, this, category);
        category.drop.on('drop:exit', function(e, category){category.removeClass('dragging-over')}, this, category);
    },
    drop_hit : function(e) {
        Y.all('.category-info.dragging-over').removeClass('dragging-over')
        var course = e.drag.get('node');
        var category = e.drop.get('node').ancestor('.category-item');
        this.cancelreorder = true;
        this.move_course_to_category(course, category);
    },
    syncTargets : function(){
        this.get('dddelegate').syncTargets();
    },
    drag_start : function (e){
        e.target.get('dragNode').setStyles({
            backgroundColor: 'transparent',
            opacity : 0
        });
        e.target.get('node').addClass('being-dragged');
        this.get('node').addClass('drag-started');
    },
    drag_end : function (e){
        var coursenode = e.target.get('node');
        coursenode.removeClass('being-dragged');
        this.get('node').removeClass('drag-started');
        if (!this.cancelreorder && this.check_if_order_changed()) {
            var courseid = coursenode.getAttribute('rel');
            var previouscourse = coursenode.previous('.course-item');
            var aftercourseid = 0
            if (previouscourse) {
                aftercourseid = previouscourse.getAttribute('rel');
            }
            this.reorder_course(coursenode, courseid, aftercourseid);
        }
        this.cancelreorder = false;
        if (this.lastdrop != null) this.lastdrop.removeClass('dragged-over');
    },
    drag_drag : function(e) {
        //Get the last x point
        var x = e.target.lastXY[0];
        //Get the last y point
        var y = e.target.lastXY[1];
        //is it greater than the lastY var?
        if (y < this.lasty) {
            //We are going up
            this.goingup = true;
        } else {
            //We are going down.
            this.goingup = false;
        }
        if (x < this.lastx) {
            //We are going up
            this.goingleft = true;
        } else {
            //We are going down.
            this.goingleft = false;
        }
        //Cache for next check
        this.lastx = x;
        this.lasty = y;
    },
    drag_over : function(e) {
        //Get a reference to our drag and drop nodes
        var drag = e.drag.get('node'),
            drop = e.drop.get('node');

        if (this.lastdrop != null) this.lastdrop.removeClass('dragged-over');
        drop.addClass('dragged-over');
        this.lastdrop = drop;

        if (drop.test('.course-item')) {
            //Are we not going up?
            if (!this.goingup && !this.goingleft) {
                drop = drop.get('nextSibling');
            }

            e.drop.get('node').get('parentNode').insertBefore(drag, drop);
            //Resize this nodes shim, so we can drop on it later.
            e.drop.sizeShim();
        }
    },
    drag_dropmiss : function() {
        
    },
    notify_error : function(message) {
        alert(message);
    },
    notify_success : function() {
        
    },
    check_if_order_changed : function() {
        var order = [];
        this.get('node').all('.course-item').each(function(n){
            order.push(n.getAttribute('rel'));
        }, this);
        if (order.join(',') != this.order.join(',')) {
            this.order = order;
            return true;
        }
        return false;
    },
    reorder_course : function(coursenode, courseid, aftercourseid) {
        var cfg = {
            sync : true,
            method : 'POST',
            data : build_querystring({
                'action' : 'reordercourse',
                'courseid' : courseid,
                'aftercourseid' : aftercourseid,
                'sesskey' : M.cfg.sesskey
            })
        }

        var request = Y.io(this.get('manager').get('ajaxurl'), cfg);
        if (request.status == 200) {
            try {
                var response = Y.JSON.parse(request.responseText);
                if (response.success) {
                    this.notify_success(coursenode);
                    return true;
                } else if (response.error) {
                    this.notify_error(response.error);
                } else {
                    this.notify_error(M.str.tool_coursemanagement.errorajaxunknown);
                }
            } catch (e) {
                this.notify_error(e.message);
                this.notify_error(M.str.tool_coursemanagement.errorajaxjsonparse);
            }
        } else {
            this.notify_error(M.str.tool_coursemanagement.errorajaxunknown);
        }
        return false;
    },
    move_course_to_category : function(course, category) {
        var cfg = {
            sync : true,
            method : 'POST',
            data : build_querystring({
                'action' : 'movecourse',
                'courseid' : course.getAttribute('rel'),
                'categoryid' : category.getAttribute('rel'),
                'sesskey' : M.cfg.sesskey
            })
        }

        var request = Y.io(this.get('manager').get('ajaxurl'), cfg);
        if (request.status == 200) {
            try {
                var response = Y.JSON.parse(request.responseText);
                if (response.success) {
                    var oldcategory = this.get('manager').get('categorylisting').get('node').one('.viewing-courses');
                    course.remove();
                    if (!this.get('node').all('.course-item').size()) {
                        oldcategory.removeClass('has-courses');
                    }
                    category.addClass('has-courses')
                    this.notify_success(category);
                    return true;
                } else if (response.error) {
                    this.notify_error(response.error);
                } else {
                    this.notify_error(M.str.tool_coursemanagement.errorajaxunknown);
                }
            } catch (e) {
                this.notify_error(e.message);
                this.notify_error(M.str.tool_coursemanagement.errorajaxjsonparse);
            }
        } else {
            this.notify_error(M.str.tool_coursemanagement.errorajaxunknown);
        }
        return false;
    }
}
Y.extend(CourseDragDrop, Y.Base, CourseDragDrop.prototype, {
    NAME : 'coursedragdrop',
    ATTRS : {
        manager : {},
        dddelegate : {},
        node : {}
    }
});

var CategoryListing = function(config) {
    CategoryListing.superclass.constructor.apply(this, [config]);
}
CategoryListing.prototype = {
    initializer : function(config) {
        var node = this.get('node');
        node.addClass('state-initialising');

        node.delegate('click', this.expand_category, '.category-item', this);
        node.delegate('click', this.show_courses, '.category-item .category-info', this);

        node.replaceClass('state-initialising', 'state-initialised');
    },
    show_courses : function(e) {

        if (!e.target.hasClass('category-info')) {
            return false;
        }

        e.halt(true);
        var category = e.target.ancestor('.category-item');
        var categoryid = category.getAttribute('rel');

        if (!category.hasClass('has-courses')) {
            return false;
        }

        var cfg = {
            sync : true,
            method : 'POST',
            data : build_querystring({
                'action' : 'getcourses',
                'categoryid' : categoryid,
                'sesskey' : M.cfg.sesskey
            })
        }

        var request = Y.io(this.get('manager').get('ajaxurl'), cfg);
        if (request.status == 200) {
            try {
                var response = Y.JSON.parse(request.responseText);
                if (response.success) {
                    var node = this.get('manager').get('courselisting').get('node').one('.courses');
                    node.setContent('');
                    this.get('node').all('.category-item.viewing-courses').removeClass('viewing-courses');
                    category.addClass('viewing-courses');
                    for (var i in response.courses) {
                        var course = response.courses[i];
                        var coursediv = c('<div id="course-'+course.id+'" class="course-item" rel="'+course.id+'"></div>');

                        coursediv.appendChild(c('<div class="course-actions course-info"><input type="checkbox" value="'+course.id+'" class="course-checkbox" /></div>'));
                        coursediv.appendChild(c('<div class="course-idnumber course-info">'+course.idnumber+'&nbsp;</div>'));
                        coursediv.appendChild(c('<div class="course-shortname course-info">'+course.shortname+'&nbsp;</div>'));
                        coursediv.appendChild(c('<div class="course-viewmore"><img alt="View details" /></div>'));
                        coursediv.appendChild(c('<div class="course-fullname course-info">'+course.fullname+'</div>'));

                        coursediv.one('.course-viewmore img').setAttribute('src', M.util.image_url('viewmore', 'tool_coursemanagement'));

                        node.appendChild(coursediv);
                    }

                    var createacourse = c('<div class="createnewcourse"><a href="'+M.cfg.wwwroot+'/course/edit.php?category='+categoryid+'">'+M.str.tool_coursemanagement.createnewcourse+'</a></div>');
                    createacourse.one('a').on('click', this.init_create_new_course, this, categoryid);
                    node.appendChild(createacourse);

                    this.get('manager').get('coursedragdrop').syncTargets();

                    return true;
                } else if (response.error) {
                    this.notify_error(response.error);
                } else {
                    this.notify_error(M.str.tool_coursemanagement.errorajaxunknown);
                }
            } catch (e) {
                this.notify_error(e.message);
                this.notify_error(M.str.tool_coursemanagement.errorajaxjsonparse);
            }
        } else {
            this.notify_error(M.str.tool_coursemanagement.errorajaxunknown);
        }
        return false;
    },
    init_create_new_course : function(e, categoryid) {
        e.halt(true);

        var ajaxurl = this.get('manager').get('ajaxurl');
        var self = this;
        var formats = this.get('manager').get('formats');
        var defaultformat = this.get('manager').get('defaultformat');
        var manager = this.get('manager');

        Y.use('moodle-enrol-notification', 'io-form', function(){
            var CREATECOURSE = function(config) {
                this.categoryid = categoryid;
                CREATECOURSE.superclass.constructor.apply(this, [config]);
            }
            CREATECOURSE.prototype = {
                categoryid : null,
                initializer : function() {
                    this.get('notificationBase').addClass('create-course-overlay');
                    this.setStdModContent(Y.WidgetStdMod.HEADER, M.util.get_string('createnewcourse', 'tool_coursemanagement'), Y.WidgetStdMod.REPLACE);
                    this.setStdModContent(Y.WidgetStdMod.BODY, this.get_content(), Y.WidgetStdMod.REPLACE);
                    this.after('destroyedChange', function(){this.get('notificationBase').remove();}, this);
                    this.after('visibleChange', this.check_if_visible, this);
                },
                check_if_visible : function(e) {
                    if (e.attrName == 'visible' && e.prevVal && !e.newVal) {
                        this.destroy();
                    }
                },
                get_content : function() {
                    var form = c('<form class="create-course-form" method="post" action=""><div class="errors"></div></div>');
                    form.addBasicInput = function(type, name, value, label) {
                        var content = c('<div class="form-input form-input-'+name+'"></div>');
                        if (label !== false) {
                            content.append(c('<label for="'+name+'">'+label+'</label>'));
                        }
                        content.append(c('<input type="'+type+'" id="input_'+name+'" name="'+name+'" value="'+value+'">'));
                        this.appendChild(content);
                    }
                    form.addHiddenInput = function(name, value) {
                        var content = c('<input class="form-input-hidden" type="hidden" id="input_'+name+'" name="'+name+'" value="'+value+'">');
                        this.appendChild(content);
                    }
                    form.addTextarea = function(name, value, label) {
                        var content = c('<div class="form-input form-input-'+name+'"></div>');
                        if (label !== false) {
                            content.append(c('<label for="'+name+'">'+label+'</label>'));
                        }
                        content.append(c('<textarea name="'+name+'" id="textarea_'+name+'">'+value+'</textarea>'));
                        this.appendChild(content);
                    }
                    form.addSelect = function(name, options, label, selected) {
                        var content = c('<div class="form-input form-input-'+name+'"></div>');
                        if (label !== false) {
                            content.append(c('<label for="'+name+'">'+label+'</label>'));
                        }
                        var select = c('<select name="'+name+'"></select>');
                        for (var i in options) {
                            if (i == selected) {
                                select.append(c('<option selected="selected" value="'+i+'">'+options[i]+'</option>'))
                            } else {
                                select.append(c('<option value="'+i+'">'+options[i]+'</option>'))
                            }
                        }
                        content.append(select);
                        this.appendChild(content);
                    }
                    form.addHiddenInput('action', 'createcourse');
                    form.addHiddenInput('categoryid', categoryid);
                    form.addHiddenInput('sesskey', M.cfg.sesskey);
                    form.addBasicInput('text', 'fullname', '', M.util.get_string('coursefullname', 'tool_coursemanagement'));
                    form.addBasicInput('text', 'shortname', '', M.util.get_string('courseshortname', 'tool_coursemanagement'));
                    form.addBasicInput('text', 'idnumber', '', M.util.get_string('courseidnumber', 'tool_coursemanagement'));
                    form.addTextarea('summary', '', M.util.get_string('coursesummary', 'tool_coursemanagement'));
                    form.addSelect('courseformat', formats, M.util.get_string('courseformat', 'tool_coursemanagement'), defaultformat);
                    form.addBasicInput('button', 'createcourse', M.util.get_string('submit', 'tool_coursemanagement'), false);

                    form.one('#input_createcourse').on('click', this.submit, this, form);

                    return form;
                },
                submit : function(e, form) {
                    var errors = this.validate_form(form);
                    if (errors.length > 0) {
                        form.one('.errors').setContent(errors.join('<br />'));
                    } else {
                        manager.show_processing_mask();
                        var cfg = {
                            'sync' : true,
                            'method' : 'POST',
                            'form' : {
                                'id' : form,
                                'useDisabled' : false
                            }
                        }
                        var request = Y.io(ajaxurl, cfg);
                        manager.hide_processing_mask();
                        if (request.status == 200) {
                            try {
                                var response = Y.JSON.parse(request.responseText);
                                if (response.success) {
                                    this.hide();
                                    this.destroy();
                                    self.add_new_course(response.course);
                                } else if (response.error) {
                                    form.one('.errors').setContent(response.error);
                                } else {
                                    form.one('.errors').setContent(M.str.tool_coursemanagement.errorajaxunknown);
                                }
                            } catch (e) {
                                form.one('.errors').setContent(M.str.tool_coursemanagement.errorajaxjsonparse);
                            }
                        } else {
                            form.one('.errors').setContent(M.str.tool_coursemanagement.errorajaxunknown);
                        }
                    }
                },
                validate_form : function(form) {
                    var errors = [];
                    if (form.one('#input_fullname').get('value').replace(/^ +/, '').replace(/ +$/, '') == '') {
                        errors.push('Fullname is a required field');
                    }
                    if (form.one('#input_shortname').get('value').replace(/^ +/, '').replace(/ +$/, '') == '') {
                        errors.push('Shortname is a required field');
                    }
                    return errors;
                }
            }
            Y.extend(CREATECOURSE, M.core.dialogue, CREATECOURSE.prototype, {
                NAME : 'create-course-form',
                ATTRS : {}
            });
            new CREATECOURSE({'width':'600px'});
        });
    },
    add_new_course : function(course) {

        var node = this.get('manager').get('courselisting').get('node').one('.courses');
        var coursediv = c('<div id="course-'+course.id+'" class="course-item" rel="'+course.id+'"></div>');

        coursediv.appendChild(c('<div class="course-actions course-info"><input type="checkbox" value="'+course.id+'" class="course-checkbox" /></div>'));
        coursediv.appendChild(c('<div class="course-idnumber course-info">'+course.idnumber+'&nbsp;</div>'));
        coursediv.appendChild(c('<div class="course-shortname course-info">'+course.shortname+'&nbsp;</div>'));
        coursediv.appendChild(c('<div class="course-viewmore"><img alt="View details" /></div>'));
        coursediv.appendChild(c('<div class="course-fullname course-info">'+course.fullname+'</div>'));

        coursediv.one('.course-viewmore img').setAttribute('src', M.util.image_url('viewmore', 'tool_coursemanagement'));

        node.insert(coursediv, node.one('.createnewcourse'));

        this.get('manager').get('coursedragdrop').syncTargets();

    },
    expand_category : function(e) {

        if (!e.target.hasClass('category-item')) {
            return;
        }

        e.halt(true);

        this.get('manager').get('node').removeClass('showing-course-details');

        var category = e.currentTarget;
        var categoryid = category.getAttribute('rel');
        if (category.hasClass('has-subcategories')) {
            if (category.hasClass('subcategories-loading')) {
                // Do nothing its already loading
            } else if (category.hasClass('expanded')) {
                category.removeClass('expanded');
            } else if (category.hasClass('subcategories-loaded')) {
                category.addClass('expanded');
            } else {
                category.addClass('subcategories-loading');
                this.load_category_subcategories(categoryid, category.one('.subcategory-listing'));
                category.replaceClass('subcategories-loading', 'subcategories-loaded');
                category.addClass('expanded');
            }
        }
    },
    notify_error : function(message) {
        alert(message);
    },
    load_category_subcategories : function(categoryid, node) {
        var cfg = {
            sync : true,
            method : 'POST',
            data : build_querystring({
                'action' : 'getsubcategories',
                'categoryid' : categoryid,
                'sesskey' : M.cfg.sesskey
            })
        }

        var request = Y.io(this.get('manager').get('ajaxurl'), cfg);
        if (request.status == 200) {
            try {
                var response = Y.JSON.parse(request.responseText);
                if (response.success) {
                    var dd = this.get('manager').get('coursedragdrop');
                    
                    for (var i in response.subcategories) {
                        var category = response.subcategories[i];
                        var categorynode = c('<div id="category-'+category.id+'" class="category-item" rel="'+category.id+'"></div>');
                        if (category.coursecount > 0) {
                            categorynode.addClass('has-courses');
                        }
                        if (category.subcategorycount > 0) {
                            categorynode.addClass('has-subcategories');
                        }
                        categorynode.appendChild('<div class="category-info">'+category.name+'</div>');
                        categorynode.appendChild('<div class="subcategory-listing"></div>');
                        node.appendChild(categorynode);
                        dd.prepare_category_for_drop(categorynode);
                    }

                    return true;
                } else if (response.error) {
                    this.notify_error(response.error);
                } else {
                    this.notify_error(M.str.tool_coursemanagement.errorajaxunknown);
                }
            } catch (e) {
                this.notify_error(e.message);
                this.notify_error(M.str.tool_coursemanagement.errorajaxjsonparse);
            }
        } else {
            this.notify_error(M.str.tool_coursemanagement.errorajaxunknown);
        }
        return false;
    }
}
Y.extend(CategoryListing, Y.Base, CategoryListing.prototype, {
    NAME : 'categorylisting',
    ATTRS : {
        node : {},
        manager : {}
    }
});

var CourseListing = function(config) {
    CourseListing.superclass.constructor.apply(this, [config]);
}
CourseListing.prototype = {
    initializer : function(config) {
        var node = this.get('node');
        node.addClass('state-initialising');

        node.delegate('click', this.show_course_details, '.course-item .course-shortname, .course-item .course-fullname, .course-item .course-idnumber, .course-item .course-viewmore', this);
        node.one('#select-all-courses').on('change', this.toggle_selection_of_all, this);

        node.replaceClass('state-initialising', 'state-initialised');
    },
    toggle_selection_of_all : function(e) {
        var checkboxes = this.get('node').all('.course-checkbox');
        if (e.target.get('checked')) {
            checkboxes.set('checked', true);
        } else {
            checkboxes.set('checked', false);
        }
    },
    show_course_details : function(e) {
        if (!e.currentTarget.hasClass('course-info') && !e.currentTarget.hasClass('course-viewmore')) {
            return false;
        }

        e.halt(true);
        var course = e.currentTarget.ancestor('.course-item');
        course.ancestor('.courses').all('.currently-viewing').removeClass('currently-viewing');
        course.addClass('currently-viewing');
        var courseid = course.getAttribute('rel');

        var cfg = {
            sync : true,
            method : 'POST',
            data : build_querystring({
                'action' : 'getcoursedetails',
                'courseid' : courseid,
                'sesskey' : M.cfg.sesskey
            })
        }

        var request = Y.io(this.get('manager').get('ajaxurl'), cfg);
        if (request.status == 200) {
            try {
                var response = Y.JSON.parse(request.responseText);
                if (response.success) {
                    this.get('manager').get('node').addClass('showing-course-details');
                    var node = this.get('manager').get('node').one('#course-details .course-details-content');

                    node.setContent('');
                    node.appendChild(c('<h3>'+response.details.fullname+'</h3>'));
                    node.appendChild(c('<h4>'+response.details.shortname+'</h4>'));

                    var formats = this.get('manager').get('formats');
                    if (typeof formats[response.details.format] != 'undefined') {
                        node.appendChild(c('<p class="format">'+formats[response.details.format]+'</p>'));
                    }

                    if (response.details.idnumber != '') {
                        node.appendChild(c('<p class="idnumber">'+response.details.idnumber+'</p>'));
                    }

                    if (response.details.summary != '') {
                        //node.appendChild(c('<div class="summary">'+response.details.summary+'</div>'));
                    }

                    var actions = c('<div class="actions"></div>');
                    for (var i in response.details.actions) {
                        var action = response.details.actions[i];
                        var actionnode = c('<div class="action action-'+i+'" title="'+action.icon.attributes.title+'"><a href="'+action.url+'"><img alt="'+action.icon.attributes.alt+'" /></a></div>');
                        actionnode.one('img').setAttribute('src', M.util.image_url(action.icon.pix, action.icon.component));
                        actions.appendChild(actionnode);
                    }
                    node.appendChild(actions);

                    node.appendChild(c('<h3>Sections</h3>'));
                    var sectionnode = c('<ul class="sections"></ul>');
                    for (var i in response.details.sections) {
                        var section = response.details.sections[i];
                        var sectionli = c('<li class="section"><h4>'+section.name+'</h4><ul class="activities"></ul></li>');
                        var sectionul = sectionli.one('ul');
                        for (var j in section.modules) {
                            var module = section.modules[j];
                            if (module.url != '') {
                                var modulea = c('<a href="'+module.url+'">'+module.name+'<a/>');
                            } else {
                                var modulea = c('<span>'+module.name+'<span/>');
                            }

                            if (module.icon != '') {
                                module.icon = c('<img alt="" />').setAttribute('src', module.icon);
                                modulea.insert(module.icon, 0);
                            }
                            var moduleli = c('<li class="activity"></li>');
                            moduleli.appendChild(modulea);
                            sectionul.appendChild(moduleli);
                        }
                        sectionnode.appendChild(sectionli);
                    }
                    node.appendChild(sectionnode);

                    return true;
                } else if (response.error) {
                    this.notify_error(response.error);
                } else {
                    this.notify_error(M.str.tool_coursemanagement.errorajaxunknown);
                }
            } catch (e) {
                this.notify_error(e.message);
                this.notify_error(M.str.tool_coursemanagement.errorajaxjsonparse);
            }
        } else {
            this.notify_error(M.str.tool_coursemanagement.errorajaxunknown);
        }
        return false;
    },
    notify_error : function(message) {
        alert(message);
    }
}
Y.extend(CourseListing, Y.Base, CourseListing.prototype, {
    NAME : 'courselisting',
    ATTRS : {
        node : {},
        manager : {}
    }
});

M.tool_coursemanagement = M.tool_coursemanagement || {};
M.tool_coursemanagement.manager = null;
M.tool_coursemanagement.init_manager = function(config) {
    M.tool_coursemanagement.manager = new Manager(config);
}

}, '@VERSION@', reqs);
