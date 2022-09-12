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
    var $loadmore = $('.js-loadmore-contacts');

    $masonry.masonry({
        columnWidth: 270,
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
         * Load profiles
         *
         * @see \ChatApp\Controller\Api\ContactsController:searchAction
         */
        path: function(index) {
            return $loadmore.data('url') + '&page=' + (index-1) + '&token=' + ChatApp.userToken;
        }
    }, function(json, opts) {

        // console.log("Luc",json.data.contacts);
        if(json.data.contacts.info.offset == 0 && json.data.contacts.count == 0){
            $('#peopleMsg').html('<span style="font-size: 1.2em; color: red;">No contact found.</span>');
        }else{
            $('#peopleMsg').html('');
        }

        if (json.data.contacts.info.offset+json.data.contacts.info.limit >= json.data.contacts.info.count || json.data.contacts.info.count <= json.data.contacts.info.limit) {
            $masonry.infinitescroll('destroy');
            $loadmore.remove();
        } else $loadmore.show();

        if (json.success) {
            var contacts = [];
            for (var i=0; i<json.data.contacts.result.length; i++) {
                var contact = json.data.contacts.result[i];

                var moments = [];
                _.each(contact.moments, function(val, index, list) {
                    if (val.images && val.images.length>0) {
                        moments.push({name: val.name, image: val.images[0]});
                    }
                });

                // is online?
                contact.is_online = false;

                var template = _.template($('#contact-template').html());
                contact = template($.extend(contact, {
                    moments: moments,
                }))
                .replace(/USERNAME/g, contact.username);

                var elem = document.createElement('div');
                $(elem).addClass('masonry-item item').html(contact);
                contacts.push(elem);
            }

            // add items
            var $items = $(contacts);
            $masonry.masonryImagesReveal($items);
        }
    });

    // load new pages by clicking a link
    $('.masonry-loadmore a').click(function() {
        return $masonry.infinitescroll('retrieve'), !1;
    })
    .click();
});
