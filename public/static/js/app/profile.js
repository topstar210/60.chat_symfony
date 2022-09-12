/*jslint nomen:true*/
/*global define, requirejs*/
define([
    'jquery'
], function($) {
    'use strict';

    /**
     * Report/Delete profile
     *
     * @see \ChatApp\Controller\Api\ProfileController:deleteAction
     * @see \ChatApp\Controller\Api\ProfileController:reportAction
     */
    $(document).on('click', '.js-profile-delete,.js-profile-report', function() {
        if (confirm('Are you sure you want to ' + ($(this).hasClass('js-profile-report') ? 'report' : 'delete') + ' profile?')) {
            $.post($(this).data('url'), {
                token: ChatApp.userToken,
                username: $(this).data('username')
            }, function(json) {
                location.href = ChatApp.baseUrl;
            }, 'json');
        }
        return false;
    });

    /**
     * Reset profile photo
     *
     * @see \ChatApp\Controller\Api\ProfileController:resetPhotoAction
     */
    $(document).on('click', '.js-profile-reset-photo,.js-profile-reset-background', function() {
        if (confirm('Are you sure you want to reset profile ' + ($(this).hasClass('js-profile-reset-background') ? 'background' : 'photo') + '?')) {
            $.getJSON($(this).data('url'), {
                token: ChatApp.userToken
            }, function(json) {
                location.reload();
            });
        }
        return false;
    });

    /**
     * Favorite/Unfavorite users
     *
     * @see \ChatApp\Controller\Api\ContactsController:favoriteAction
     * @see \ChatApp\Controller\Api\ContactsController:unfavoriteAction
     */
    $(document).on('click', '.js-contact-favorite', function () {
        var $this = $(this);
        var url = $this.data('is-favorite') ? $this.data('unfavorite-url') : $this.data('favorite-url');

        $.getJSON(url, {
            token: ChatApp.userToken
        }, function (json) {
            if ($this.data('is-favorite')) {
                $this
                    .data('is-favorite', false)
                    .addClass('btn-default')
                    .removeClass('btn-danger')
                ;

                if ($this.attr('title')) {
                    $this
                        .attr('title', 'Add Contact')
                        .html('<i class="fa fa-plus"></i> Add Contact')
                    ;
                } else {
                    $this.html('Add Contact');
                }
            } else {
                $this
                    .data('is-favorite', true)
                    .addClass('btn-danger')
                    .removeClass('btn-default')
                ;

                if ($this.attr('title')) {
                    $this
                        .attr('title', 'Remove Contact')
                        .html('<i class="fa fa-minus"></i> Remove Contact')
                    ;
                } else {
                    $this.html('Remove Contact');
                }
            }
        })
        .fail(function(xhr) {
            alert(xhr.responseJSON.data.message || 'An error occurred, please try again later..');
        });

        return false;
    });
});
