/*jslint nomen:true*/
/*global define, requirejs*/
define([
    'jquery',
    'bootstrap'
], function($) {
    'use strict';

    $(document.body).scrollspy({target: '.settings-sidebar'});

    $(window).on('load',function() {
        $(document.body).scrollspy('refresh')
    });

    // show/hide password change fields
    $(document).on('click', '.js-password-change', function(e) {
        $(this).remove();
        $('.js-password-fields').show();
        e.preventDefault();
    });

    $(document).on('click', '.js-verify-code', function(e) {
        var $this = $(this);
        var $input = $this.closest('li').find('input');
        var type = $input.attr('id') == 'profile_email' ? 'email' : 'phone';
        var data = type == 'email'
            ? {email: $input.val()}
            : {phone_number: $input.val()};

        $this.html('<i class="fa fa-spinner fa-spin"></i>');

        $.post($this.data('url'), data, function(json) {
            $this.text('re-verify');
        }, 'json');

        e.preventDefault();
    });
});
