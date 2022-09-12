/*jslint nomen:true*/
/*global define, requirejs*/
define([
    'jquery',
    'bootstrap'
], function($) {
    'use strict';

    /* init drodowns*/
    $('.dropdown-toggle').dropdown();

    /* header actions */
    $('#hdr-nav-toggle').on('click', function() {
        return $('body').toggleClass('freeze-scroll'), $('.hdr').toggleClass('mobile-nav-active'), !1;
    });
    $('#nav-search .btn-search').on('click', function() {
        return $('.hdr').addClass('nav-search-active'), $('#form-hdr-search-input').focus(), !1;
    });
    $('#form-hdr-search-input').on('blur', function() {
        return $('.hdr').removeClass('nav-search-active'), !1;
    });
    $('#form-hdr-search-input').on('blur', function() {
        return $('.hdr').removeClass('nav-search-active'), !1;
    });

    /* scroll-to-top */
    $(window).scroll(function() {
        if ($(this).scrollTop() > 0) {
            $('a.scroll-to-top').fadeIn(200);
        } else {
            $('a.scroll-to-top').fadeOut(200);
        }
    });

    // scroll to tag
    $('a[href*=#]:not([href=#])').on('click', function() {
        if (location.pathname.replace(/^\//, '') == this.pathname.replace(/^\//, '') && location.hostname == this.hostname) {
            var target = $(this.hash);
            target = target.length ? target : $('[name=' + this.hash.slice(1) + ']');
            if (target.length) {
                $('html, body').animate({
                    scrollTop: target.offset().top
                }, 1000);

                return false;
            }
        }
    });


});

/**
 * File funtion
 */
function getExtension(filename) {
    var parts = filename.split('.');
    return parts[parts.length - 1];
}

function isImage(filename) {
    var ext = getExtension(filename);
    switch (ext.toLowerCase()) {
        case 'jpg':
        case 'jpeg':
        case 'gif':
        case 'bmp':
        case 'png':
            //etc
            return true;
    }
    return false;
}

function isVideo(filename) {
    var ext = getExtension(filename);
    switch (ext.toLowerCase()) {
        case 'm4v':
        case 'mp3':
        case 'wav':
        case 'avi':
        case 'mpg':
        case 'mp4':
            // etc
            return true;
    }
    return false;
}

/*View club*/
function viewClub (obj) {
    require(['jquery'],
        function($) {
            var clubId = $(obj).data('clubid');
            var url = $(obj).data('cluburl');
            var $loader = $('.clubItem_' + clubId).find('.ajax-loader');
            var $loader2 = $('#viewClubModal .ajax-loader');

            var $clubDiscusContent = $('#clubDiscusContent');
            var $clubDiscussTemplate = _.template($('#clubDiscussTemplate').html());

            if (clubId) {
                $loader.show();
                $loader2.show();
                $.getJSON(url, {
                    token: ChatApp.userToken,
                    club_id: clubId
                }, function (json) {
                    $loader.hide();
                    $loader2.hide();
                    $('#clubViewMessage').hide();
                    var $club = json.data.club;
                    var $chats = $club.chats;
                    var owner_img = '';
                    var gender = '';
                    if ($club.owner.gender) {
                        gender = $club.owner.gender.toLocaleLowerCase();
                    }

                    if ($club.owner.photo) {
                        owner_img = '<img src="' + $club.owner.photo.replace("/origin/", "/medium/") + '"/>';
                    }

                    // console.log('viewClub',$club);
                    $('.club-modal-name').html($club.name);

                    if ($club.background) {
                        $('.modal-club-cover').css('background-image', 'url(' + $club.background + ')');
                    } else if ($club.photo) {
                        $('.modal-club-cover').css('background-image', 'url(' + $club.photo + ')');
                    }


                    $('.modal-club-owner-name').html($club.owner.name);
                    $('.modal-club-description').html($club.description);
                    $('.modal-club-owner').html('<a class="gender ' + gender + '" href="/profile/' + $club.owner.user + '">' + owner_img + '</a>');

                    var participants = '';
                    var participants_applicant = '';
                    var flag_joined = false;
                    var count = 0;
                    var invited = 0;
                    var me = null;
                    var isOwner = ($club.owner.user == ChatApp.userUsername) ? true : false;

                    for (var i = 0; i < $club.participants.length; i++) {

                        if ($club.participants[i].enabled) {
                            count++;
                            var member_avata = '';
                            gender = '';
                            if ($club.participants[i].gender) {
                                gender = $club.participants[i].gender.toLocaleLowerCase();
                            }

                            if ($club.participants[i].photo) {
                                member_avata = '<img src="' + $club.participants[i].photo.replace("/origin/", "/medium/") + '"/>';
                            }
                            var member_action = '';
                            if (isOwner && $club.participants[i].username != ChatApp.userUsername) {
                                member_action = '<div class="member_action"><a data-username="' + $club.participants[i].username + '" data-clubid="' + $club.id + '"  onclick="rejectMemberOfClub(this)" class="btn btn-danger btn-sm btn-reject" title="Remove"><i class="fa fa-close"></i></a></div>';
                            }
                            participants += '<li><a href="/profile/' + $club.participants[i].user + '" class="gender ' + gender + '" title="' + $club.participants[i].name + '">' + member_avata + '</a>' + member_action + '</li>';
                        } else {
                            invited++;
                            var member_avata = '';
                            gender = '';
                            if ($club.participants[i].gender) {
                                gender = $club.participants[i].gender.toLocaleLowerCase();
                            }

                            if ($club.participants[i].photo) {
                                member_avata = '<img src="' + $club.participants[i].photo.replace("/origin/", "/medium/") + '"/>';
                            }
                            var member_action = '';
                            if (isOwner) {
                                member_action = '<div class="member_action"><a data-username="' + $club.participants[i].username + '" data-clubid="' + $club.id + '" onclick="approveMemberOfClub(this)" class="btn btn-success btn-sm btn-approve" title="Approve"><i class="fa fa-check"></i></a>' +
                                    '<a data-username="' + $club.participants[i].username + '" data-clubid="' + $club.id + '" onclick="rejectMemberOfClub(this)" class="btn btn-danger btn-sm btn-reject" title="Reject"><i class="fa fa-close"></i></a></div>';
                            }

                            participants_applicant += '<li><a href="/profile/' + $club.participants[i].user + '" class="gender ' + gender + '" title="' + $club.participants[i].name + '">' + member_avata + '</a>' + member_action + '</li>';

                        }

                        if ($club.participants[i].username == ChatApp.userUsername) {
                            flag_joined = true;
                            me = $club.participants[i];
                        }

                    }

                    if (flag_joined) {
                        $('.clubModalContent .btn-joinClub').hide();
                    } else {
                        $('.clubModalContent .btn-joinClub').show();
                    }

                    if (me && !me.enabled) {

                        $('#clubViewMessage').css('color', 'red').html(ChatApp.userUsername + " Pending Approval").show();

                    }

                    if(isOwner || (me && me.enabled)){
                        $('#viewClubModal #clubDiscussion').show();
                    }else{
                        $('#viewClubModal #clubDiscussion').hide();
                    }

                    $('.modal-club-member #boardMember').html(participants);

                    //check owner
                    if (isOwner && invited > 0) {
                        $('#applicantsWrap').show();
                        $('#boardApplicants').html(participants_applicant);
                    } else {
                        $('#applicantsWrap').hide();
                        $('#boardApplicants').html('');
                    }

                    var members = count + ' member';
                    if (count > 1) {
                        members += 's';
                    }

                    if (invited) {
                        members += ', ' + invited + ' applicant'
                    }

                    if (invited > 1) {
                        members += 's';
                    }

                    $('.modal-club-member .head b').html(members);

                    $('.clubModalContent .inputClubId').attr('data-clubid', $club.id);
                    $('.clubModalContent .inputClubIdForm').val($club.id);


                    if ($club.owner.user == ChatApp.userUsername) {
                        $('.modal-club-action').show();
                    } else {
                        $('.modal-club-action').hide();
                    }

                    //Discussion
                    $clubDiscusContent.html('');
                    // console.log($club);
                    if ($chats.length > 0) {
                        for (var i = 0; i < $chats.length; i++) {
                            var item = {}
                            item.club_id = $club.id;
                            item.chat_id = $chats[i].chat_id;
                            item.subject = $chats[i].subject;
                            item.message = $chats[i].last_message.message;
                            item.date_created = $chats[i].last_message.date_created;
                            participants_string = '';

                            for (var j = 0; j <  $chats[i].participants.length; j++) {
                                if(participants_string) participants_string +=',';
                                participants_string += $chats[i].participants[j].username;
                            }

                            for (var j = 0; j < $chats[i].participants.length; j++) {

                                if ($chats[i].last_message.from_username == $chats[i].participants[j].username) {
                                    item.user = $chats[i].participants[j];
                                    break;
                                }
                            }

                            item.participants = participants_string;

                            var chatItem = $clubDiscussTemplate($.extend(chatItem, item));
                            $clubDiscusContent.append(chatItem);
                        }


                    } else {
                        $clubDiscusContent.html('<div class="text-center">No discussion.</div>')
                    }

                    $('#viewClubModal').modal('show');
                });
            }
        });

}

/*Join club*/
function joinClub (obj) {
    var clubId = $(obj).data('clubid');
    var url = $(obj).data('inviteurl');
    $('#viewClubModal').modal('hide');
    var $loader = $('.clubItem_'+clubId).find('.ajax-loader');
    var $loader2 = $('#viewClubModal .ajax-loader');
    // alert(clubId);
    if(clubId){
        $loader.show();
        $loader2.show();
        $.getJSON(url, {
            token: ChatApp.userToken,
            username:ChatApp.userUsername,
            club_id:clubId,
            accept:null
        }, function(json) {
            $loader.hide();
            $loader2.hide();
            if(json.success){
                $('.clubItem_'+clubId).find('.clubAction .btn-primary').remove();
                setTimeout(function() {
                    $('.masonry').masonry();
                }, 0);

                $.iGrowl({
                    type: 'success-sat',
                    message: 'Your request have been sent.',
                    icon: 'fa-check-circle-o',
                    delay:3000
                })
            }else{
                $.iGrowl({
                    type: 'error-sat',
                    message: json.data.message,
                    icon: 'fa-times-circle-o',
                    delay:5000
                })
            }

        });
    }



}


function deleteClub(obj) {
    var clubId = $(obj).data('clubid');
    var url = $(obj).data('deleteclub-url');
    $('#viewClubModal').modal('hide');
    $('#confirmDeleteClub').modal('show');
    var $loader = $('.clubItem_'+clubId).find('.ajax-loader');
    if(clubId){

        $('#deleteClubNow').click(function(e) {
            $loader.show();
            $.getJSON(url, {
                token: ChatApp.userToken,
                club_id:clubId
            }, function(json) {
                $loader.hide();
                if(json.success){
                    $('.clubItem_'+clubId).closest('.masonry-item').remove();
                    setTimeout(function() {
                        $('.masonry').masonry();
                    }, 0);

                    $.iGrowl({
                        type: 'success-sat',
                        message: 'The club has been removed.',
                        icon: 'fa-check-circle-o',
                        delay:3000
                    })
                }else{
                    $.iGrowl({
                        type: 'error-sat',
                        message: json.data.message,
                        icon: 'fa-times-circle-o',
                        delay:5000
                    })
                }

            });
        });
    }


}


function editClub (obj) {
    var clubId = $(obj).data('clubid');
    var url = $(obj).data('editclub-url');
    $('#viewClubModal').modal('hide');
    var $loader = $('.clubItem_'+clubId).find('.ajax-loader');
    if(clubId){
        $loader.show();
        $.getJSON(url, {
            token: ChatApp.userToken,
            club_id:clubId
        }, function(json) {
            $loader.hide();
            console.log("editClub",json);
            $club = json.data.club;

            $('#frmClub_edit input[name=club_id]').val($club.id);
            $('#frmClub_edit input[name=name]').val($club.name);
            $('#frmClub_edit textarea[name=description]').val($club.name);
            $('#frmClub_edit input[name=photo]').val($club.photo);
            $('#frmClub_edit input[name=background]').val($club.background);

            if($club.photo){
                $('#frmClub_edit .coverPhoto #coverPhoto_preview').attr('src',$club.photo);
                $('#frmClub_edit .coverPhoto .preview').show();
            }

            if($club.background){
                $('#frmClub_edit .backgroundPhoto #backgroundPhoto_preview').attr('src',$club.background);
                $('#frmClub_edit .backgroundPhoto .preview').show();
            }

            $('#editClubModal').modal("show");

        });
    }
}

function approveMemberOfClub(obj) {
    var $loader = $('#viewClubModal .ajax-loader');
    var clubId = $(obj).data('clubid');
    var username = $(obj).data('username');
    var url = 'api/clubs/invite';
    var accept = 1; //accept

    $loader.show();
    $.getJSON(url, {
        token: ChatApp.userToken,
        club_id:clubId,
        username:username,
        accept:accept
    }, function(json) {
        $loader.hide();
        if(json.success){
            $(obj).closest('li').find('.btn-approve').remove();
            $('#boardMember').append(clone($(obj).closest('li')));
            $(obj).closest('li').remove();

            $.iGrowl({
                type: 'success-sat',
                message: 'Approved user!',
                icon: 'fa-check-circle-o',
                delay:3000
            })

            setTimeout(function() {
                $('.masonry').masonry();
            }, 0);
        }else{
            $.iGrowl({
                type: 'error-sat',
                message: json.data.message,
                icon: 'fa-times-circle-o',
                delay:5000
            })
        }

    });
}

function rejectMemberOfClub(obj) {
    var $loader = $('#viewClubModal .ajax-loader');
    var clubId = $(obj).data('clubid');
    var username = $(obj).data('username');
    var url = 'api/clubs/invite';
    var accept = 0; //reject

    $loader.show();
    $.getJSON(url, {
        token: ChatApp.userToken,
        club_id:clubId,
        username:username,
        accept:accept
    }, function(json) {
        $loader.hide();
        if(json.success){
            $(obj).closest('li').remove();
            $.iGrowl({
                type: 'success-sat',
                message: 'Removed user',
                icon: 'fa-check-circle-o',
                delay:3000
            })
            setTimeout(function() {
                $('.masonry').masonry();
            }, 0);
        }else{
            $.iGrowl({
                type: 'error-sat',
                message: json.data.message,
                icon: 'fa-times-circle-o',
                delay:5000
            })
        }

    });
}

function confirmDialog(funct,message,title) {
    $confirm = $('#confirmModal');
    if(!title) title ='Confirm';
    if(!message) message ='Are you sure?';
    $confirm.find('.modal-title').text(title)
    $confirm.find('.modal-body').text(message)
    $confirm.find('.btn-primary').attr('data-function',funct);
    $confirm.modal('show');
}

function time_ago(time) {

    switch (typeof time) {
        case 'number':
            break;
        case 'string':
            time = +new Date(time);
            break;
        case 'object':
            if (time.constructor === Date) time = time.getTime();
            break;
        default:
            time = +new Date();
    }
    var time_formats = [
        [60, 'seconds', 1], // 60
        [120, '1 minute ago', '1 minute from now'], // 60*2
        [3600, 'minutes', 60], // 60*60, 60
        [7200, '1 hour ago', '1 hour from now'], // 60*60*2
        [86400, 'hours', 3600], // 60*60*24, 60*60
        [172800, 'Yesterday', 'Tomorrow'], // 60*60*24*2
        [604800, 'days', 86400], // 60*60*24*7, 60*60*24
        [1209600, 'Last week', 'Next week'], // 60*60*24*7*4*2
        [2419200, 'weeks', 604800], // 60*60*24*7*4, 60*60*24*7
        [4838400, 'Last month', 'Next month'], // 60*60*24*7*4*2
        [29030400, 'months', 2419200], // 60*60*24*7*4*12, 60*60*24*7*4
        [58060800, 'Last year', 'Next year'], // 60*60*24*7*4*12*2
        [2903040000, 'years', 29030400], // 60*60*24*7*4*12*100, 60*60*24*7*4*12
        [5806080000, 'Last century', 'Next century'], // 60*60*24*7*4*12*100*2
        [58060800000, 'centuries', 2903040000] // 60*60*24*7*4*12*100*20, 60*60*24*7*4*12*100
    ];
    var seconds = (+new Date() - time) / 1000,
        token = 'ago',
        list_choice = 1;

    if (seconds == 0) {
        return 'Just now'
    }
    if (seconds < 0) {
        seconds = Math.abs(seconds);
        token = 'from now';
        list_choice = 2;
    }
    var i = 0,
        format;
    while (format = time_formats[i++])
        if (seconds < format[0]) {
            if (typeof format[2] == 'string')
                return format[list_choice];
            else
                return Math.floor(seconds / format[2]) + ' ' + format[1] + ' ' + token;
        }
    return time;
}