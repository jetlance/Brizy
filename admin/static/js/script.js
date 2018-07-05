jQuery(document).ready(function ($) {
    $('.enable-brizy-editor').on('click', function (event) {
        event.preventDefault();

        jQuery(window).off('beforeunload.edit-post');

        if (wp.autosave) {
            wp.autosave.server.triggerSave();
        }

        window.location = $(this).attr('href');
    })
});


function reloadRuleBox() {
    var action  = $('#rule-reload-action').val();
    $.get(action,function (data) {
       $('#template-rules').find('.inside').html(data);
    });
}

function createTemplateRule(button) {
    var $this = $(button);
    var action = $this.data('action');
    var data = $this.closest('.new-rule').find('.rule-field,input:hidden').serialize();

    $.post(action, data).then(function (data,status) {
        console.log(data,status);
        reloadRuleBox()
    }).fail(function (data) {
        $this.closest('.new-rule').find('p.error').remove();
        $this.closest('.new-rule').append('<p class="error">'+data.responseJSON.data.message+'</p>');
    });
}


function deleteTemplateRule(button) {
    var $this = $(button);
    var action = $this.data('action');

    $.get(action,function (data) {
        reloadRuleBox();
    });
}