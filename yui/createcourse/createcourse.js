YUI.add('moodle-tool_coursemanagement-createcourse', function(Y) {

var c = Y.Node.create;

var CreateCourse = function(config) {
    CreateCourse.superclass.constructor.apply(this, [config]);
}
CreateCourse.prototype = {
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
        form.addHiddenInput('categoryid', this.get('categoryid'));
        form.addHiddenInput('sesskey', M.cfg.sesskey);
        form.addBasicInput('text', 'fullname', '', M.util.get_string('coursefullname', 'tool_coursemanagement'));
        form.addBasicInput('text', 'shortname', '', M.util.get_string('courseshortname', 'tool_coursemanagement'));
        form.addBasicInput('text', 'idnumber', '', M.util.get_string('courseidnumber', 'tool_coursemanagement'));
        form.addTextarea('summary', '', M.util.get_string('coursesummary', 'tool_coursemanagement'));
        form.addSelect('courseformat', this.get('manager').get('formats'), M.util.get_string('courseformat', 'tool_coursemanagement'), this.get('manager').get('defaultformat'));
        form.addBasicInput('button', 'createcourse', M.util.get_string('submit', 'tool_coursemanagement'), false);

        form.one('#input_createcourse').on('click', this.submit, this, form);

        return form;
    },
    submit : function(e, form) {
        var errors = this.validate_form(form);
        if (errors.length > 0) {
            form.one('.errors').setContent(errors.join('<br />'));
        } else {
            this.get('manager').show_processing_mask();
            var cfg = {
                'sync' : true,
                'method' : 'POST',
                'form' : {
                    'id' : form,
                    'useDisabled' : false
                }
            }
            var request = Y.io(this.get('manager').get('ajaxurl'), cfg);
            this.get('manager').hide_processing_mask();
            if (request.status == 200) {
                try {
                    var response = Y.JSON.parse(request.responseText);
                    if (response.success) {
                        this.hide();
                        this.destroy();
                        this.get('manager').get('categorylisting').add_new_course(response.course);
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
Y.extend(CreateCourse, M.core.dialogue, CreateCourse.prototype, {
    NAME : 'create-course-form',
    ATTRS : {
        categoryid : {},
        manager : {}
    }
});

M.tool_coursemanagement = M.tool_coursemanagement || {};
M.tool_coursemanagement.createcourse = null;
M.tool_coursemanagement.init_createcourse = function(config) {
    M.tool_coursemanagement.createcourse = new CreateCourse(config);
}

}, '@VERSION@', {requires:['moodle-enrol-notification', 'io-form']});