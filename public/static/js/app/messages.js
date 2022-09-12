/*jslint nomen:true*/
/*global define, requirejs*/
define([
    'underscore',
    'jquery',
    'faye',
    'igrowl',
    'jquery.quicksearch',
    'jquery.autosize',
    'jquery.uploader'
], function(_, $) {
    'use strict';


    //add Moment
    $('#hdr-nav-moment').click(function () {
        $('#addMomentModal').modal('show');
    });

    /**
     * Handle message notification
     *
     * Note: once receive message, wait 2 seconds and execute
     */
    ChatApp.fayeClient.getClient().subscribe('/' + ChatApp.userToken + '/messages', function(response) {
        setTimeout(function() {
            var message = response.message,
                $chatLI = $messagesUI.find('li#' + message.chat_id);

            // check if chat exists
            if ($chatLI.length === 0) {
                var template = _.template($('#messages-name-template').html());

                $messagesUI.prepend(template({
                    chat_id: message.chat_id,
                    participants: [message.from_username],
                    username: message.from_username,
                    name: message.from_name,
                    photo: message.from_photo,
                    gender: message.from_gender,
                    last_message: { message: message.message },
                    unread: null,
                    is_online: true
                }));

                $chatLI = $messagesUI.find('li:eq(0)');
            }

            // increase unread number
            $headerNavItem.find('.unread').text(parseInt($headerNavItem.find('.unread').text() || 0) + 1);
            $chatLI.find('.messages-notification').text(parseInt($chatLI.find('.messages-notification').text() || 0) + 1);

            unreadMessages++;

            // reload history messages
            if ($chatLI.hasClass('active')) {
                bKeepMessasge = true;
                $hdrNavMessages.addClass('open');
                $messagesWrapper.show();
                $chatLI.find('.messages-name').click();
            }
        }, 2000);
    });

    /**
     * Show/hide messages
     *
     * @see \ChatApp\Controller\Api\MessagesController:openChatsAction
     */
    var $hdrNavMessages = $('#hdr-nav-messages');
    var $chatLoaderListUser = $('#chatListBoxLoading');
    var $chatLoaderBox = $('#chatMessageLoading');

    $hdrNavMessages.on('click', function() {
        var $self = $(this);

        if ($messagesWrapper.hasClass('is-loaded')) {
            $self.toggleClass('open');
            $messagesWrapper.toggle();
            return;
        }

        $.when(getOpenMessages()).done(function() {
            $self.toggleClass('open');
            $messagesWrapper.toggle();
        });
    });
    
    $('#hideChatPanel').click(function () {
        var $self =$('#hdr-nav-messages');

        if ($messagesWrapper.hasClass('is-loaded')) {
            $self.toggleClass('open');
            $messagesWrapper.toggle();
            return;
        }

        $.when(getOpenMessages()).done(function() {
            $self.toggleClass('open');
            $messagesWrapper.toggle();
        });
    });

    $('#loadMoreChatPeople').click(function () {
         var page = parseInt($messagesWrapper.data('page'));
         if(!page) page = 1;
         page++;
         $messagesWrapper.data('page',page);
         getOpenMessages();
    });

    function getOpenMessages() {
        $messagesWrapper.addClass('is-loaded');
        var limit = parseInt($messagesWrapper.data('limit'));
        var page = parseInt($messagesWrapper.data('page'));
        if(!limit) limit = 20;
        if(!page) page = 1;

        $chatLoaderListUser.show();

        return $.getJSON($messagesWrapper.data('open-url'), {
            token: ChatApp.userToken,
            limit:limit,
            page:page

        }, function(json) {
            $chatLoaderListUser.hide();
            if(json.data.chats.result.length < limit){
                $('#loadMoreChatPeople').hide();
            }else{
                $('#loadMoreChatPeople').show();
            }

            if (json.success && json.data.chats.result.length > 0) {
                for (var i=0; i<json.data.chats.result.length; i++) {
                    var chat = json.data.chats.result[i];

                    var messageUnread = 0,
                        participant   = null,
                        participants  = [];
                    for (var j=0; j<chat.participants.length; j++) {
                        if (chat.participants[j].username == ChatApp.userUsername) {
                            messageUnread = parseInt(chat.participants[j].unread);
                            unreadMessages += messageUnread;
                        } else {
                            participants.push(chat.participants[j].username);

                            if (!participant) {
                                participant = chat.participants[j];
                            }
                        }
                    }

                    if (participant) {
                        var template = _.template($('#messages-name-template').html());

                        $messagesUI.append(template($.extend(participant, {
                            chat_id: chat.chat_id,
                            participants: participants,
                            last_message: chat.last_message,
                            unread: messageUnread,
                            is_online: false
                        })));
                    }
                }

                // quick search on messages users
                if ($('.messages-search input').length) {
                    $('.messages-search input').val('').quicksearch('.messages-ui li');
                }
            } else {
                $messagesWrapper.removeClass('is-loaded');
            }

            // set unread messages number
            if (unreadMessages > 0) {
                $headerNavItem.find('.unread').text(unreadMessages);
            }
        });
    };

    function showHistory($chatLI, participants, messages) {
        $messagesUI.find('li').removeClass('active');
        $chatLI.addClass('active');



        // decrease unread number
        var total = parseInt($headerNavItem.find('.unread').text() || 0) - parseInt($chatLI.find('.messages-notification').text() || 0);
        $headerNavItem.find('.unread').text(total > 0 ? total : '');

        // reset unread messages count
        $chatLI.find('.messages-notification').text('');

        // clear history messages
        $messagesMessages.html('');

        // sort: oldest first
        if (messages && messages.length) {
            _.each(messages.reverse(), function(element, index, list) {
                var template = _.template($('#messages-message-template').html());

                $messagesMessages.append(
                    template($.extend(element, {
                        is_now: false
                    }))
                        .replace(/FROM_USERNAME/g, element.from_username)
                );
            });
        }

        // assign chatid
        $messagesChatId.val($chatLI.attr('id'));

        // assign participants (only assign on new chat
        $messagesParticipants.val(!$chatLI.attr('id') ? participants : null);

        // show $messagesBox
        $messagesBox.addClass('box-show');

        // hide $messagesList
        $messagesList.addClass('hide-it');

        // scroll to bottom
        $messagesWrapper.animate({
            scrollTop: $messagesWrapper[0].scrollHeight
        }, 1000);

        // clear text and resize text area to orginal state
        if (!bKeepMessasge) {
            $messagesMsgbox.val('').autosize();
        }
        $messagesMsgbox.focus();

        // reset
        bKeepMessasge = false;

        //show chat username
        $('#selectedChatUser').text(participants);
    }

    var bKeepMessasge         = false;
    var unreadMessages        = 0;
    var $headerNavItem        = $('.hdr .hdr-nav-messages');
    var $messagesWrapper      = $('.messages-wrapper');
    var $messagesList         = $('.messages-list');
    var $messagesUI           = $messagesList.find('.messages-ui');
    var $messagesBox          = $('.messages-box');
    var $messagesMessages     = $messagesBox.find('.messages-messages');
    var $closeMessages        = $messagesBox.find('a.btn-close');
    var $messagesChatId       = $messagesBox.find('input[name=chat_id]');
    var $messagesParticipants = $messagesBox.find('input[name=participants]');
    var $messagesFile         = $messagesBox.find('input[name=file]');
    var $messagesMsgbox       = $messagesBox.find('textarea[name=sendmsg]');



    // load open message on page load
    getOpenMessages();

    /**
     * Load chat history
     */
    $(document).on('click', 'a.messages-name', function(e) {
        e.preventDefault();

        var $chatLI = $(this).closest('li');

        // show $messagesBox
        $messagesBox.addClass('box-show');
        // hide $messagesList
        $messagesList.addClass('hide-it');

        $chatLoaderBox.show();

        if ($chatLI.attr('id')) {

            $.getJSON($messagesBox.data('history-url'), {
                token: ChatApp.userToken,
                chat_id: $chatLI.attr('id')
            }, function(json) {
                $chatLoaderBox.hide();
                if (json.success) {
                    showHistory($chatLI, $chatLI.data('participants'), json.data.messages.result);
                }
            });
        } else {
            showHistory($chatLI, $chatLI.data('participants'));
        }

    });

    /**
     * Close chat
     */
    $closeMessages.on('click', function(e) {
        e.preventDefault();

        // close $messagesBox
        $messagesList.removeClass('hide-it');
        $messagesBox.removeClass('box-show');

        $messagesWrapper.animate({
            scrollTop: 0
        }, 1000);
        $messagesList.animate({
            scrollTop: 0
        }, 1000);

    });

    /**
     * Handle send message
     */
    $messagesMsgbox.on('keyup', function(e) {
        if (e.which == 13 && !e.shiftKey) {
            var msg = $(this).val(),
                file = $messagesFile.val();

            msg = $.trim(msg);
            if (msg.length === 0) {
                // clear text and resize text area to orginal state
                $(this).val('').trigger('autosize.resize');
                if(!$('#shareFileChat').val()){
                    $('.messages-write textarea').focus();
                    return false;
                }

            }

            // clear text and resize text area to orginal state
            $(this).val('').trigger('autosize.resize');

            // clear file
            $messagesFile.val('');

            var data = {
                token: ChatApp.userToken,
                chat_id: $messagesChatId.val(),
                file: file,
                text: msg,
            };
            if (!data.chat_id) {
                data.participants = $messagesParticipants.val()
            }

            $('#uploadImg').show();
            $('#message_error').show();
            $('#message_error').text('Sending...');
            $('#chatPreviewImage').fadeOut();
            $.getJSON($messagesBox.data('add-url'), data, function(json) {
                //clear attach
                $('#uploadImg').hide();
                $('#message_error').hide();
                $('#message_error').text('')

                $('#btn-attach-photo .fa-photo').removeClass('selectedImg');
                $('#btn-attach-photo').attr('title','');
                $('#chatPreviewImage').fadeOut();
                // console.log(json);
                if (json.success) {
                    // append msg
                    var template = _.template($('#messages-message-template').html());

                    $messagesMessages.append(
                        template({
                            is_now: true,
                            is_me: true,
                            from_username: ChatApp.userUsername,
                            from_name: $messagesBox.find('input[name=my_name]').val(),
                            from_photo:  $messagesBox.find('input[name=my_photo]').val(),
                            files: json.data.message.files ? json.data.message.files : [],
                            message: msg,
                        })
                            .replace(/FROM_USERNAME/g, ChatApp.userUsername)
                    );

                    // scroll to bottom
                    $messagesWrapper.animate({
                        scrollTop: $messagesWrapper[0].scrollHeight
                    }, 1000);

                    // assign chatid if missing
                    if (!data.chat_id) {
                        $messagesUI.find('li.active').attr('id', json.data.message.chat_id);
                        $messagesChatId.val(json.data.message.chat_id);
                    }
                }
            });
        }
    });

    /**
     * Upload file
     */
    var isUpload = false;
    var uploader = new Uploader({
        selectButton: '#btn-attach-photo',
        url: $messagesBox.data('upload-file'),
        multiple: false
    });


    uploader.on('uploadProgress', function() {
        // console.log(uploader);
        $('#uploadImg').show();
        isUpload = true;
    });

    uploader.on('uploadComplete', function() {
        $('#uploadImg').hide();
        isUpload = false;

        var respone = JSON.parse(arguments[2]);
        if(respone.success){
            var f = respone.data.split('/');
            $('#btn-attach-photo .fa-photo').addClass('selectedImg')
            $('#btn-attach-photo').attr('title',f[1]);
            $('#removeChatImg').show();
            // $('#btn-attach-photo').tooltip('enable');
            $('#message_error').hide();
            $messagesFile.val(respone.data);
        }else{
            $('#btn-attach-photo .fa-photo').removeClass('selectedImg');
            $('#chatPreviewImage').fadeOut();
            $('#removeChatImg').hide();
            $('#btn-attach-photo').attr('title','');
            // $('#btn-attach-photo').tooltip('disable');
            $('#message_error').show();

            $('#message_error').text(respone.data.message)
            $messagesFile.val('');
        }


    });



    /**
     * Create new chat
     */
    $(document).on('click', '.js-send-message', function() {

        var isProfile = $(this).closest('.profile-header-wrapper').length > 0;

        var username = isProfile
            ? $(this).closest('.fixed-header-name-and-image').find('.fixed-header-name').text()
            : $(this).closest('.masonry-item').find('.username').text()
        ;

        var photo = isProfile
            ? $(this).closest('.fixed-header-name-and-image').find('.fixed-header-image img').attr('src')
            : $(this).closest('.item').find('.board-cover').attr('src')
        ;

        // add to existing discussion
        if ($messagesUI.find('li[data-participants="' + username + '"]').length > 0) {
            $hdrNavMessages.addClass('open');
            $messagesWrapper.show();
            $messagesUI.find('li[data-participants="' + username + '"] > .messages-name').trigger("click");

        }else {
            // create a new discussion
            var template = _.template($('#messages-name-template').html());

            $messagesUI.prepend(template({
                chat_id: null,
                participants: [username],
                username: username,
                name: username,
                photo: photo,
                last_message: {message: null},
                unread: true,
                is_online: true
            }));
            //show chat
            $hdrNavMessages.addClass('open');
            $messagesWrapper.show();
            $messagesUI.find('li[data-participants="' + username + '"] > .messages-name').trigger("click");
            // showHistory($messagesUI.find('li:eq(0)'), username);
        }
    });

    //group chat
    $(document).on('click', '.js-group-message', function() {
        var club_id = $(this).data('club_id');
        var chat_id = $(this).data('chat_id');
        var message = $(this).data('message');

        var participants = $(this).data('participants');

        var photo = '';
        // add to existing discussion
        if ($messagesUI.find('li[data-participants="' + participants + '"]').length > 0) {
            $hdrNavMessages.addClass('open');
            $messagesWrapper.show();
            $messagesUI.find('li[data-participants="' + participants + '"] > .messages-name').trigger("click");

        }else {
            // create a new discussion
            var template = _.template($('#messages-name-template').html());

            $messagesUI.prepend(template({
                chat_id: chat_id,
                participants: participants.split(','),
                username: participants,
                name: participants,
                last_message:message,
                photo: null,
                gender: null,
                unread: null,
                is_online: true
            }));
            //show chat
            $hdrNavMessages.addClass('open');
            $messagesWrapper.show();
            $messagesUI.find('li[data-participants="' + participants + '"] > .messages-name').trigger("click");

        }

        $('#viewClubModal').modal('hide');
    });


    //group chat
    $(document).on('click', '.js-recent-message', function() {
        var chat_id = $(this).data('chat_id');
        var message = $(this).data('message');

        var participants = $(this).data('participants');

        var photo = '';
        // add to existing discussion
        if ($messagesUI.find('li[data-participants="' + participants + '"]').length > 0) {
            $hdrNavMessages.addClass('open');
            $messagesWrapper.show();
            $messagesUI.find('li[data-participants="' + participants + '"] > .messages-name').trigger("click");

        }else {
            // create a new discussion
            var template = _.template($('#messages-name-template').html());

            $messagesUI.prepend(template({
                chat_id: chat_id,
                participants: participants.split(','),
                username: participants,
                name: participants,
                last_message:message,
                photo: null,
                gender: null,
                unread: null,
                is_online: false
            }));
            //show chat
            $hdrNavMessages.addClass('open');
            $messagesWrapper.show();
            $messagesUI.find('li[data-participants="' + participants + '"] > .messages-name').trigger("click");

        }
    });
    
    /*
    * Remove attach image*/
    $('#removeChatImg').click(function () {
        $('#chatPreviewImage').fadeOut();
        $('#btn-attach-photo .fa-photo').removeClass('selectedImg');
        $('#btn-attach-photo').attr('title','');
        $messagesFile.val('');
    });


    function preview_chat_image(event)
    {

        $('#chatPreviewImage').show();
        var reader = new FileReader();
        reader.onload = function()
        {
            var output = $('#chatPreviewContent_src');
            output.attr('src',reader.result);
            $('#chatPreviewImage').parent().show();
        }
        reader.readAsDataURL(event.target.files[0]);
    }
    setInterval(inputFileEvent,100);
    function inputFileEvent() {
        require(['jquery'],
            function($) {
                $('#btn-attach-photo input[type=file]').change(function (e) {
                    var filename = $(this).val();
                    if (!isImage(filename) && !isVideo(filename)) {
                        $('#message_error').show();
                        $('#message_error').text("Invalid file.");
                        $('#btn-attach-photo .selectedImg').removeClass('selectedImg');

                        if (uploader) uploader.abortAll();
                        $('#chatPreviewImage').hide();
                        e.preventDefault();
                        return false;
                    }

                    $('#removeChatImg').hide();
                    if (isImage(filename)) {
                        preview_chat_image(e);
                    }
                    isUpload = true;
                });
            });
    }

});