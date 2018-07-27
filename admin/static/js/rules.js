var h = hyperapp.h;

var RULE_TYPE_INCLUDE = '1';
var RULE_TYPE_EXCLUDE = '2';

var RULE_POSTS = '1';
var RULE_TAXONOMY = '2';
var RULE_ARCHIVE = '4';
var RULE_TEMPLATE = '8';


var defaultRule = {
    type: RULE_TYPE_INCLUDE,
    appliedFor: '',
    entityType: '',
    entityValues: []
};

var state = {
    rule: defaultRule,
    groups: []
};

var api = {
    getGroupList: function () {
        return jQuery.getJSON(Brizy_Admin_Data.url, {action: 'brizy_rule_group_list'});
    },
    getPosts: function (postType, filter, exclude) {
        return jQuery.getJSON(Brizy_Admin_Data.url, {
            action: 'brizy_get_posts',
            excludePostTypes: exclude,
            postType: postType,
            filterTerm: filter
        })
    },
    getTerms: function (taxonomy) {
        return jQuery.getJSON(Brizy_Admin_Data.url, {
            action: 'brizy_get_terms',
            taxonomy: taxonomy
        })
    },
    createRule: function (rule) {
        return jQuery.post(Brizy_Admin_Data.url, {action: 'brizy_rule_create', rule: rule})
    }
};

var RuleTypeField = function (params) {
    return h("select", {
        onchange: function (e) {
            params.onChange({type: e.target.value})
        }
    }, [
        h(
            "option",
            {value: RULE_TYPE_INCLUDE, selected: params.value === RULE_TYPE_INCLUDE},
            "Include"
        ),
        h(
            "option",
            {value: RULE_TYPE_EXCLUDE, selected: params.value === RULE_TYPE_EXCLUDE},
            "Exclude"
        )
    ]);
};

var Select2 = function (params) {
    var oncreate = function (element) {
        var el = jQuery(element).select2().on('change', params.onChange);
        if (params.optionRequest) {
            params.optionRequest.done(function (data) {
                var options = params.convertResponseToOptions(data);
                options.forEach(function (option) {
                    el.append(option).trigger('change');
                });
            })
        }
    };

    var onremove = function (element, done) {
        jQuery(element).select2('destroy');
        done();
    };

    return h("select", {
        key: params.id,
        style: params.style,
        oncreate: oncreate,
        onremove: onremove,
    }, []);
};


var PostSelect2Field = function (params) {

    var convertResponseToOptions = function (data) {
        var options = [new Option("All", null, false, false)];
        data.posts.forEach(function (post) {
            options.push(new Option(post.title, post.ID, false, false));
        });
        return options;
    };

    return h(Select2, {
        id: params.id,
        style: params.style ? params.style : {width: '200px'},
        optionRequest: params.optionRequest,
        convertResponseToOptions: convertResponseToOptions,
        onChange: params.onChange
    }, []);
};

var RuleCustomPostSearchField = function (params) {
    return h(PostSelect2Field, {
        id: params.id,
        optionRequest: api.getPosts(params.postType),
        onChange: params.onChange
    }, []);
};

var RuleTaxonomySearchField = function (params) {
    var convertResponseToOptions = function (data) {
        var options = [new Option("All", null, false, false)];
        data.forEach(function (term) {
            options.push(new Option(term.name, term.term_id, false, false));
        });
        return options;
    };

    return h(Select2, {
        id: 'taxonomies-' + params.taxonomy,
        style: params.style ? params.style : {width: '200px'},
        optionRequest: api.getTerms(params.taxonomy),
        convertResponseToOptions: convertResponseToOptions,
        onChange: params.onChange
    }, []);
};


var RuleApplyGroupField = function (params) {
    var appliedFor = params.rule.appliedFor;
    var entityType = params.rule.entityType;
    var value = appliedFor + '|' + entityType;
    var groups = [];

    params.groups.forEach(function (group) {
        var options = [];
        group.items.forEach(function (option) {
            options.push(
                h("option", {value: group.value + '|' + option.value}, option.title)
            );
        });
        const attributes = {label: group.title};

        if (group.value + '|' === '|') {
            groups.push(h("option", {value: '|'}, group.title));
        }
        else {
            groups.push(h("optgroup", attributes, options));
        }
    });

    var elements = [
        h("select", {
            onchange: function (e) {
                let values = e.target.value.split('|');
                return params.onChange({appliedFor: values[0], entityType: values[1]});
            }
        }, groups)];

    switch (appliedFor) {
        case RULE_POSTS:
            elements.push(
                h(RuleCustomPostSearchField, {
                    id: appliedFor + value,
                    postType: entityType,
                    rule: params.rule,
                    onChange: function (e) {
                        return params.onChange({entityValues: e.target.value});
                    }
                })
            );
            break;

        case RULE_TAXONOMY:
            elements.push(
                h(RuleTaxonomySearchField, {
                    id: appliedFor + value,
                    rule: params.rule,
                    taxonomy: entityType,
                    onChange: function (e) {
                        return params.onChange({entityValues: e.target.value});
                    }
                })
            );
            break;
    }

    return elements;
};


var RuleForm = function (params) {
    var elements = [
        h(RuleTypeField, {value: params.rule.type, onChange: params.onChange}),
        h(RuleApplyGroupField, {rule: params.rule, onChange: params.onChange, groups: params.groups}),
        h("input", {
            type: "button",
            class: "button",
            onclick: params.onSubmit,
            value: "Add"
        })
    ];

    return h('div', {}, elements)
};

var actions = {
    updateGroups: function (value) {
        return function (state) {
            return _.extend({}, state, {groups: value});
        }
    },
    ruleChange: function (value) {
        return function (state) {
            return _.extend({}, state, {rule: _.extend({}, state.rule, value)});
        }
    },
    resetRule: function () {
        return function (state) {
            return _.extend({}, state, {rule: defaultRule});
        }
    }
};

var view = function (state, actions) {

    return h("div", {
            oncreate: function (element) {
                api.getGroupList().done(actions.updateGroups);
            }
        },
        [
            h(RuleForm, {
                rule: state.rule,
                onChange: actions.ruleChange,
                groups: state.groups,
                onSubmit: function () {
                    api.createRule(state.rule).done(function () {
                        actions.resetRule();
                    }).fail(function () {
                        // show some errors
                    })
                }
            })

        ]);
};

jQuery(document).ready(function ($) {
    hyperapp.app(state, actions, view, document.getElementById('add-rule-form'));
});
