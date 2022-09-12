/*jslint nomen:true*/
/*global define, requirejs*/
define([
    'underscore',
    'jquery',
    'igrowl',
    'faye'
], function (_, $) {
    'use strict';

    var FayeClient = {

        /**
         * @property client
         * @type object
         * @default null
         */
        client: null,

        /**
         * The `init` initializes the component, by setting up the Faye Client.
         *
         * @method init
         */
        init: function(protocol, hostname, port, params) {
            port   = port || 3000;
            params = params || {timeout: 20};

            this.client = new Faye.Client(protocol + '//' + hostname + ':' + port + '/faye', params);
        },

        /**
         * 'client' Returns a FayeClient singleton.
         *
         * @attribute client
         * @type object
         * @default undefined
         */
        getClient: function() {
            return this.client;
        }
    };

    // make global
    ChatApp.fayeClient = FayeClient;
    ChatApp.fayeClient.init(window.location.protocol, window.location.hostname);

    /**
     * Show incoming message
     */
    ChatApp.fayeClient.getClient().subscribe('/' + ChatApp.userToken + '/alert', function(response) {
        var data = {
            type: 'info-sat',
            title: response.alert.title || 'Alert',
            message: response.alert.message
        };
        if (response.alert.image || false) {
            data.image = {
                src: response.alert.image
            }
        }
        $.iGrowl(data);
    });

    /**
     * Show incoming new moment comment
     */
    ChatApp.fayeClient.getClient().subscribe('/' + ChatApp.userToken + '/new_moment_comment', function(response) {
        $.iGrowl({
            type: 'info-sat',
            title: 'New Comment' + (response.moment.name ? ' for ' + response.moment.name : ''),
            message: response.moment.comments[0].comment.user + ' said: ' + response.moment.comments[0].comment,
            image: {
                src: response.moment.images[0]
            }
        });
    });

});
