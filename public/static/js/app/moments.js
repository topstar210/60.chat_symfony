/*jslint nomen:true*/
/*global define, requirejs*/

define([
    'underscore',
    'jquery',
    'masonry',
    'imagesloaded',
    'jquery.infinitescroll',
    'jquery.view',
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
    var $loadmore = $('.js-loadmore-moments');



    $masonry.masonry({
        columnWidth: 270,
        gutter: 20,
        itemSelector: '.masonry-item',
        isFitWidth: true
    });

    ChatApp._masonry = $masonry;

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
         * Load moments
         *
         * @see \ChatApp\Controller\Api\MomentsController:searchAction
         */
        path: function(index) {
            return $loadmore.data('url') + '&page=' + (index-1) + '&token=' + ChatApp.userToken;
        }
    }, function(json, opts) {

        // console.log("Luc",json.data.moments);
        if(json.data.moments.info.offset == 0 && json.data.moments.count == 0){
            $('#momentMsg').html('<span style="font-size: 1.2em; color: red;">No moments found.</span>');
        }else{
            $('#momentMsg').html('');
        }

        if (json.data.moments.info.offset+json.data.moments.info.limit >= json.data.moments.info.count || json.data.moments.info.count <= json.data.moments.info.limit) {
            $masonry.infinitescroll('destroy');
            $loadmore.remove();
        } else $loadmore.show();

        if (json.success) {
            var moments = [];
            for (var i=0; i<json.data.moments.result.length; i++) {
                var moment = json.data.moments.result[i];
                if (!moment.images || moment.images.length === 0) continue;
                var comments = [];
                for (var j=0; j<moment.comments.length; j++) {
                    // console.log(moment.comments[j].user,moment.comments[j]);
                    var template = _.template($('#moment-comment-template').html());
                    comments.push(template($.extend(moment.comments[j], {index: j})).replace(/USERNAME/g, moment.comments[j].user));
                }

                var isLike = false;
                for (var j=0; j<moment.likes.length; j++) {
                    if (moment.likes[j].user == ChatApp.userUsername) {
                        isLike = true;
                    }
                }

                var template = _.template($('#moment-template').html());
                moment = template($.extend(moment, {
                    id: moment.id,
                    image: moment.images[0],
                    comments: comments.join(''),
                    totalComments: moment.comments.length,
                    totalLikes: moment.likes.length,
                    isLike: isLike
                }))
                .replace(/MID/g, moment.id)
                .replace(/USERNAME/g, moment.username);

                var elem = document.createElement('div');
                $(elem).addClass('masonry-item pin').html(moment);
                moments.push(elem);
            }

            // add items
            var $items = $(moments);
            $masonry.masonryImagesReveal($items);

            // reload viewer
            View($('a.view[href]'));
        }
    });

    // load new pages by clicking a link
    $('.masonry-loadmore a').click(function() {
        return $masonry.infinitescroll('retrieve'), !1;
    })
    .click();

    // show all comments
    $(document).on('click', '.js-pin-comments-showall', function(e) {
        var i=0, j=0;
        $(this).closest('.pin-comment-list').find('li').each(function() {
            i++;
            if (j<10 && $(this).css('display') == 'none') {
                j++;
                $(this).show();
            }
        });
        var countHidden = parseInt($(this).find('.count').html())-j;
        if (countHidden===0) $(this).remove();
        else $(this).find('.count').html(countHidden);
        $masonry.masonry();
        e.preventDefault();
    });

    /**
     * Delete moment
     *
     * @see \ChatApp\Controller\Api\MomentsController:deleteAction
     */
    $(document).on('click', '.js-moment-delete', function() {
        ChatApp._globalMoment = $(this);

        confirmDialog('doDeleteMoment','Are you sure you want to delete moment?');
        return false;
    });

    /**
     * Block moment
     *
     * @see \ChatApp\Controller\Api\MomentsController:blockAction
     */
    $(document).on('click', '.js-moment-block', function() {
        ChatApp._globalMoment = $(this);
        confirmDialog('doBlockMoment','Are you sure you want to delete moment?');
        return false;
    });



    /**
     * Like/Unlike moments
     *
     * @see \ChatApp\Controller\Api\MomentsController:likeAction
     * @see \ChatApp\Controller\Api\MomentsController:unlikeAction
     */
    $(document).on('click', '.js-moment-like', function() {
        var $this = $(this);
        var url = $this.data('is-like') ? $this.data('unlike-url') : $this.data('like-url');
        var $moment = $this.closest('.momentItem');
        var $loader = $moment.find('.ajax-loader');
        $loader.show();
        $.getJSON(url, {
            token: ChatApp.userToken
        }, function(json) {
            $moment.find('.js-moment-like').toggleClass('likedtrue');
            $loader.hide();
            var totalLikes = $moment.find('.pin-like-count');
            if ($this.data('is-like')) {
                $this.data('is-like', false);
                totalLikes.html(parseInt(totalLikes.html())-1);
            } else {
                $this.data('is-like', true);
                totalLikes.html(parseInt(totalLikes.html())+1);
            }
        });

        return false;
    });

    /**
     * Add moment comment
     *
     * @see \ChatApp\Controller\Api\MomentsController:addCommentAction
     */
    $(document).on('submit', '.js-form-moment-comments', function(e) {
        var $this = $(this),
            formData = {};
        var $message = $this.find('textarea[name=comment]');

        if(!$message.val()){
            $message.focus();
            e.preventDefault();
            return false;
        }

        _.each($this.serializeArray(), function(value) {
            formData[value.name] = value.value;
        });


        $this.find('.ajax-loader').show();

        $.getJSON($this.attr('action'), $.extend(formData, {
            token: ChatApp.userToken
        }), function(json) {
            $this.find('.ajax-loader').hide();
            $this.find('textarea[name=comment]').val('');

            var totalComments = $this.closest('.pin-wrapper').find('.pin-comment-count');
            totalComments.html(parseInt(totalComments.html()) +1);

            var comments = $this.closest('.pin-wrapper').find('.pin-comment-list ul');

            var template = _.template($('#moment-comment-template').html());
            comments.prepend(template($.extend(json.data, {index: 0})).replace(/USERNAME/g, json.data.user));

            $masonry.masonry();
        });
        return false;
    });
});

function doBlockMoment(){

    var $this = ChatApp._globalMoment
    var $loading = ChatApp._globalMoment.closest('.pin').find('.ajax-loader');
    $loading.show();

    $.getJSON($this.data('url'), {
        token: ChatApp.userToken
    }, function(json) {
        $loading.hide();
        if(json.success){
            $.iGrowl({
                type: 'success-sat',
                message: 'Thanks for your report!\nWe\'ll take a look at this moment and delete it if it goes against our Terms of Service.',
                icon: 'fa-check-circle-o',
                delay:3000
            });
            $this.closest('.pin').remove();
            ChatApp._masonry.masonry();
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

function doDeleteMoment(){

    var $this = ChatApp._globalMoment
    var $loading = ChatApp._globalMoment.closest('.pin').find('.ajax-loader');
    $loading.show();
    $.getJSON($this.data('url'), {
        token: ChatApp.userToken
    }, function(json) {
        $loading.hide();
        if(json.success){
            $.iGrowl({
                type: 'success-sat',
                message: 'The moment has been deleted.',
                icon: 'fa-check-circle-o',
                delay:3000
            });
            $this.closest('.pin').remove();
            ChatApp._masonry.masonry();
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
