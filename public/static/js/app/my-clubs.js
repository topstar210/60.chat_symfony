/*jslint nomen:true*/
/*global define, requirejs*/
define([
    'underscore',
    'jquery',
    'masonry',
    'imagesloaded',
    'jquery.infinitescroll',
    'jquery.bridget'
], function(_, $, Masonry, ImagesLoaded) {
    'use strict';

    $.bridget('masonry', Masonry);
    $.bridget('imagesLoaded', ImagesLoaded);

    // Reveal when images have been loaded
    $.fn.masonryImagesReveal = function($items) {

        var msnry = this.data('masonry');
        var itemSelector = msnry.options.itemSelector;
        // hide by default
        $items.hide();
        // append to container
        this.append($items);
        $items.imagesLoaded(function(imgLoad, image) {
            // get item
            // image is imagesLoaded class, not <img>, <img> is image.img
            if (_.isUndefined(imgLoad.images[0])) {
                var $item = $(imgLoad.elements[0]);
            } else {
                var $item = $(imgLoad.images[0].img).parents(itemSelector);
            }
            // un-hide item
            $item.show();
            // masonry does its thing
            msnry.appended($item);

        });

        return this;
    };

    // configure
    var $masonry  = $('.masonry');
    var $loadmore = $('.js-loadmore-clubs');

    $masonry.masonry({
        columnWidth: 240,
        gutter: 20,
        itemSelector: '.masonry-item',
        isFitWidth: true
    });

    $masonry.infinitescroll({
        loading: {
            finishedMsg: '',
            img: null,
            msgText: '<div class="clubLoading"><span class="ajax-loader-inline"></span> <span class="ajax-loader-inlinelabel">Loading...</span></div><div class="page-loader"></div>',
        },
        navSelector: '.masonry-loadmore',
        nextSelector: '.masonry-loadmore a',
        dataType: 'json',
        appendCallback: false,

        /**
         * Load clubs
         *
         * @see \ChatApp\Controller\Api\ClubsController:searchAction
         */
        path: function(index) {
            return $loadmore.data('url') + '&page=' + (index-1) + '&token=' + ChatApp.userToken;
        }
    }, function(json, opts) {
        console.log("search",json);
        if (json.data.clubs.info.offset+json.data.clubs.info.limit >= json.data.clubs.info.count || json.data.clubs.info.count <= json.data.clubs.info.limit) {
            $masonry.infinitescroll('destroy');
            $loadmore.remove();
        } else $loadmore.show();

        if(json.data.clubs.count == 0){
            $('#clubMessage').html('No club found.');
            $('#clubMessage').show();
        }else{
            $('#clubMessage').hide();
        }

        if (json.success) {
            var clubs = [];
            for (var i=0; i<json.data.clubs.result.length; i++) {
                var club = json.data.clubs.result[i];

                // skip if missing location
                if (!club.latitude || !club.longitude) {
                    continue;
                }
                // add marker
                else {
                    var marker = new google.maps.Marker({
                        position: new google.maps.LatLng(club.latitude, club.longitude),
                        map: clubMap,
                        title: club.name,
                        info: '<h4>' + club.name + '</h4><p>' + club.description + '</p>',
                        icon: '//maps.google.com/mapfiles/ms/icons/red-dot.png'
                    });

                    var infowindow = new google.maps.InfoWindow();

                    google.maps.event.addListener(marker, 'click', function() {
                        infowindow.setContent(this.info);
                        infowindow.open(clubMap, this);
                    });
                }

                var participants = [];
                var participants_picures = [];
                var participants_nopicures = [];

                var me = null;
                var me_position = -1;
                var total_members = 0;
                var total_applications = 0;

                _.each(club.participants, function(val, index, list) {



                    var member_img = '';
                    var gender = '';
                    if(val.gender){
                        gender = val.gender.toLocaleLowerCase();
                    }

                    if(val.photo){
                        member_img =  '<img src="'+val.photo.replace("/origin/", "/medium/")+'"/>';
                    }
                    val.avatar = '<a class="gender '+gender+'">'+member_img+'</a>'

                    if(val.user == ChatApp.userUsername){
                        me = val;
                        me_position = index;
                    }

                    if(val.enabled){
                        if(val.photo){
                            participants_picures.push(val)
                        }else{
                            participants_nopicures.push(val)
                        }
                        participants.push(val);
                        total_members++;
                    }else{
                        total_applications++;
                    }

                });

                //show the picture
                // console.log('participants',participants,participants_picures,participants_nopicures)
                var memeber_head = []
                var j = 0;
                if(me && me.enabled){
                    if(me_position < 3){
                        j = 0;
                        while (j<4){

                            if(participants_picures[j]){
                                memeber_head.push(participants_picures[j]);
                            }else{
                                break;
                            }
                            j++;
                        }

                        if(j<4){
                            while (j<4){

                                if(participants_nopicures[j]){
                                    memeber_head.push(participants_nopicures[j]);
                                }else{
                                    break;
                                }
                                j++;
                            }
                        }
                    }else{
                        j = 0;
                        while (j<3){

                            if(participants_picures[j]){
                                memeber_head.push(participants_picures[j]);
                            }else{
                                break;
                            }
                            j++;
                        }

                        if(j<3){
                            while (j<3){

                                if(participants_nopicures[j]){
                                    memeber_head.push(participants_nopicures[j]);
                                }else{
                                    break;
                                }
                                j++;
                            }
                        }
                        if(me.enabled){
                            memeber_head.push(me);
                        }
                    }

                }else {
                    j = 0;
                    while (j<4){

                        if(participants_picures[j]){
                            memeber_head.push(participants_picures[j]);
                        }else{
                            break;
                        }
                        j++;
                    }

                    if(j<4){
                        while (j<4){

                            if(participants_nopicures[j]){
                                memeber_head.push(participants_nopicures[j]);
                            }else{
                                break;
                            }
                            j++;
                        }
                    }

                }

                var template = _.template($('#club-template').html());
                club = template($.extend(club, {
                    total_members: total_members,
                    total_applications: total_applications,
                    me: me,
                    memeber_head: memeber_head
                }))
                    .replace(/CID/g, club.id);

                var elem = document.createElement('div');
                $(elem).addClass('masonry-item item').html(club);
                clubs.push(elem);
            }

            // add items
            var $items = $(clubs);
            $masonry.masonryImagesReveal($items);
        }
    });

    // load new pages by clicking a link
    $('.masonry-loadmore a').click(function() {
        return $masonry.infinitescroll('retrieve'), !1;
    })
        .click();

    /**
     * Delete moment
     *
     * @see \ChatApp\Controller\Api\ClubsController:deleteAction
     */
    $(document).on('click', '.js-club-delete', function() {
        if (confirm('Are you sure you want to delete club')) {
            var $this = $(this);
            $.getJSON($this.data('url'), {
                token: ChatApp.userToken
            }, function(json) {
                $this.closest('.item').remove();
                $masonry.masonry();
            });
        }
        return false;
    });



});


