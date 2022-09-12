
/* Adult App */
var BotApp = angular.module("BotApp", [
    "ui.router",
    "ui.bootstrap",
    "oc.lazyLoad",
    "ngCookies",
    'ngStorage',
    'ngDialog',
    'ui.mask'
]);
BotApp.constant('API_URL', '');
/* Configure ocLazyLoader(refer: https://github.com/ocombe/ocLazyLoad) */
BotApp.config(['$ocLazyLoadProvider', function ($ocLazyLoadProvider) {
    $ocLazyLoadProvider.config({
        // global configs go here
    });
}]);

//AngularJS v1.3.x workaround for old style controller declarition in HTML
BotApp.config(['$controllerProvider', function ($controllerProvider) {
    // this option might be handy for migrating old apps, but please don't use it
    // in new ones!
    $controllerProvider.allowGlobals();
}]);

BotApp.factory('Auth', ['$http', '$localStorage', 'API_URL', '$cookies', '$rootScope', function ($http, $localStorage, API_URL, $cookies, $rootScope) {
    function urlBase64Decode(str) {
        var output = str.replace('-', '+').replace('_', '/');
        switch (output.length % 4) {
            case 0:
                break;
            case 2:
                output += '==';
                break;
            case 3:
                output += '=';
                break;
            default:
                throw 'Illegal base64url string!';
        }
        return window.atob(output);
    }
    function getClaimsFromToken() {
        var token = $localStorage.token;
        var user = {};
        if (typeof token !== 'undefined') {
            var encoded = token.split('.')[1];
            user = JSON.parse(urlBase64Decode(encoded));
        }
        return user;
    }
    var tokenClaims = getClaimsFromToken();
    return {
        signup: function (data, success, error) {
            // $http.post(urls.BASE + '/signup', data).success(success).error(error)
        },
        signin: function (data, success, error) {
            $http.post(API_URL + '/api/main_login', data).success(success).error(error)
        },
        can_signin: function (data, success, error) {
            $http.post(API_URL + '/api/candidate_login', data).success(success).error(error)
        },
        logout: function (success) {
            tokenClaims = {};
            delete $localStorage.token;
            $rootScope.globals = {};
            $cookies.remove('globals');
            location.href = '/';
        },
        getTokenClaims: function () {
            return tokenClaims;
        }
    };


}]);

/* Setup App Main Controller */
BotApp.controller('AppController', ['$scope', '$rootScope', '$http', '$location', 'Auth', function ($scope, $rootScope, $http, $location, Auth) {

    $scope.authed = false;

    $scope.logout = function () {
    }
    $scope.$on('$viewContentLoaded', () => {
    });


    $rootScope.$on('$stateChangeSuccess', function (event) {
        // Layout.setAngularJsSidebarMenuActiveLink('match', null, event.currentScope.$state); // activate selected link in the sidebar menu
    });



}]);

BotApp.controller('FooterController', ['$scope', '$location', '$rootScope', '$state', function ($scope, $location, $rootScope, $state) {
    $scope.$on('$includeContentLoaded', function () {
    });

}]);
/* Setup Rounting For All Pages */
BotApp.config(['$stateProvider', '$urlRouterProvider', '$locationProvider', '$ocLazyLoadProvider', '$httpProvider', function ($stateProvider, $urlRouterProvider, $locationProvider, $ocLazyLoadProvider, $httpProvider) {
    // Redirect any unmatched url

    $urlRouterProvider.otherwise("/");
    $ocLazyLoadProvider.config({
        debug: true
    });

    $stateProvider
        // Dashboard
        .state('presenter', {
            url: "/presenter",
            templateUrl: "js/app/views/presenter.html",
            data: { pageTitle: 'presenter', pageType: 'guest' },
            controller: "PresenterController",
            resolve: {
                deps: ['$ocLazyLoad', function ($ocLazyLoad) {
                    return $ocLazyLoad.load({
                        name: 'BotApp',
                        files: [
                            'js/app/controllers/PresenterController.js'
                        ]
                    });
                }]
            }
        })
        .state('record', {
            url: "/record/:room_id",
            templateUrl: "js/app/views/record.html",
            data: { pageTitle: 'presenter', pageType: 'guest' },
            controller: "RecordController",
            resolve: {
                deps: ['$ocLazyLoad', function ($ocLazyLoad) {
                    return $ocLazyLoad.load({
                        name: 'BotApp',
                        files: [
                            'js/app/controllers/RecordController.js'
                        ]
                    });
                }]
            }
        })
        .state('admin', {
            url: "/admin",
            templateUrl: "js/app/views/admin.html",
            data: { pageTitle: 'Dashboard', pageType: 'guest' },
            controller: "AdminController",
            resolve: {
                deps: ['$ocLazyLoad', function ($ocLazyLoad) {
                    return $ocLazyLoad.load({
                        name: 'BotApp',
                        files: [
                            'js/app/controllers/AdminController.js'
                        ]
                    });
                }]
            }
        })
        .state('room', {
            url: "/room",
            templateUrl: "js/app/views/room.html",
            data: { pageTitle: 'Room', pageType: 'guest' },
            controller: "RoomController",
            resolve: {
                deps: ['$ocLazyLoad', function ($ocLazyLoad) {
                    return $ocLazyLoad.load({
                        name: 'BotApp',
                        files: [
                            'js/app/controllers/RoomController.js'
                        ]
                    });
                }]
            }
        })

}]);
BotApp.factory('socket', function ($rootScope) {
    var socket = io.connect("https://chatapp.mobi:3001");
    return {
        on: function (eventName, callback) {
            socket.on(eventName, function () {
                var args = arguments;
                $rootScope.$apply(function () {
                    callback.apply(socket, args);
                });
            });
        },
        emit: function (eventName, data, callback) {
            socket.emit(eventName, data, function () {
                var args = arguments;
                $rootScope.$apply(function () {
                    if (callback) {
                        callback.apply(socket, args);
                    }
                });
            })
        }
    };
});
BotApp.directive('pauseOnClose', function () {
    return {
        restrict: 'A',
        link: function (scope, element, attrs) {
            element.on('hidden.bs.modal', function (e) {
                // Find elements by video tag
                var nodesArray = [].slice.call(document.querySelectorAll("video"));
                // Loop through each video element 
                angular.forEach(nodesArray, function (obj) {
                    // Apply pause to the object
                    obj.pause();
                });
            });
        }
    }
});
/* Init global settings and run the app */
BotApp.run(function ($rootScope, $state, $cookies, $location, $http, $injector, $localStorage) {

    $rootScope.$state = $state; // state to be accessed from view

    $rootScope.globals = $cookies.getObject('globals') || {};

    $rootScope.$on('$stateChangeStart', function (event, toState, toParams) {
        var currentState = toState;

    });

    $rootScope.$on('$locationChangeSuccess', (event, newUrl, oldUrl) => {

    });

});
BotApp.directive('partCamItem', function (API_URL, $state, $rootScope) {
    return {
        restrict: 'E',
        replace: true,
        templateUrl: 'js/app/directives/part-cam-item.html',
        scope: {
            track: '=track',
            itemStyle: '=itemStyle',
            layout: '=layout',
            type: '=type',
            label: '=label',
            localAudioTrackName: '=localAudioTrackName'
        },
        link: function ($scope, element) {

            $scope.name = $scope.track.identity;
            var mediaElement = $scope.track.attach();
            if ($scope.type == 'screen' || $scope.type == 'video') {
                mediaElement.style.objectFit = 'fill';
            }

            if ($scope.track.name == $scope.localAudioTrackName) {
                mediaElement.muted = true;
            }
            element[0].prepend(mediaElement);


            $scope.$watch('type', (newValue, oldValue) => {
                console.log($scope.type);
                if ($scope.type == 'screen') {
                    mediaElement.style.transform = 'scaleX(1)';
                } else if ($scope.type == 'video') {
                    mediaElement.style.transform = 'scaleX(-1)';
                } else {

                }
            }, true);

            $scope.$watch('layout', (newValue, oldValue) => {
                if ($scope.layout == 3) {
                    mediaElement.style.objectFit = 'cover';
                } else {
                    mediaElement.style.objectFit = 'fill';
                }
            }, true);

        }
    }
});
BotApp.directive('recordCamItem', function (API_URL, $state, $rootScope) {
    return {
        restrict: 'E',
        replace: true,
        templateUrl: 'js/app/directives/record-cam-item.html',
        scope: {
            track: '=track',
            itemStyle: '=itemStyle',
            layout: '=layout',
            type: '=type',
            session: '=session'
        },
        link: function ($scope, element) {
            var handles = [];

            $scope.name = 'test';
            var mediaElement = document.createElement('video');
            mediaElement.src = '/' + $scope.session + '/' + $scope.track + '.mp4';
            mediaElement.autoplay = true;
            
            mediaElement.playsInline = true;
            

            
			mediaElement.play();

            if ($scope.type == 'screen' || $scope.type == 'video') {
                mediaElement.style.objectFit = 'fill';
            }

            element[0].prepend(mediaElement);

            $scope.$watch('type', (newValue, oldValue) => {
                console.log($scope.type);
                if ($scope.type == 'screen') {
                    mediaElement.style.transform = 'scaleX(1)';
                } else if ($scope.type == 'video') {
                    mediaElement.style.transform = 'scaleX(-1)';
                } else {

                }
            }, true);

            $scope.$watch('layout', (newValue, oldValue) => {
                if ($scope.layout == 3) {
                    mediaElement.style.objectFit = 'cover';
                } else {
                    mediaElement.style.objectFit = 'fill';
                }
            }, true);

        }
    }
});
BotApp.directive('camItem', function (API_URL, $state, $rootScope) {
    return {
        restrict: 'E',
        replace: true,
        templateUrl: 'js/app/directives/cam-item.html',
        scope: {
            track: '=track',
            itemStyle: '=itemStyle',
            layout: '=layout',
            type: '=type',
            label: '=label'
        },
        link: function ($scope, element) {
            var handles = [];

            $scope.name = $scope.track.identity;
            var mediaElement = $scope.track.track.attach();

            console.log($scope.track);
            if ($scope.type == 'screen' || $scope.type == 'video') {
                mediaElement.style.objectFit = 'fill';
            }
            
            element[0].prepend(mediaElement);


            $scope.$watch('type', (newValue, oldValue) => {
                console.log($scope.type);
                if ($scope.type == 'screen') {
                    mediaElement.style.transform = 'scaleX(1)';
                } else if ($scope.type == 'video') {
                    mediaElement.style.transform = 'scaleX(-1)';
                } else {

                }
            }, true);

            $scope.$watch('layout', (newValue, oldValue) => {
                if ($scope.layout == 3) {
                    mediaElement.style.objectFit = 'cover';
                } else {
                    mediaElement.style.objectFit = 'fill';
                }
            }, true);

        }
    }
});
BotApp.directive('broadcastScreen', function (API_URL, $state, $rootScope) {
    return {
        restrict: 'E',
        replace: true,
        templateUrl: 'js/app/directives/broadcast-screen.html',
        scope: {
            layout: '=layout',
            tracks: '=tracks',
            status: '=status',
        },
        link: function ($scope, element) {
            $scope.broadcast_tracks = [];
            $scope.show_loading = true;

     
            $scope.$watch('tracks', (newValue, oldValue) => {

                $scope.$evalAsync(($scope) => {
                    $scope.broadcast_tracks = [];
                    console.log($scope.tracks);
                    for (var key in $scope.tracks) {
                    	if(!$scope.broadcast_tracks.find(x => x.track_name == $scope.tracks[key].track.name)){
                    		var _obj = {
	                            track: $scope.tracks[key].track,
	                            style: {
	                                top: '0%',
	                                left: '0%',
	                                width: '100%',
	                                height: '100%',
	                                opacity: '1'
	                            },
	                            type: $scope.tracks[key].type,
	                            track_name: $scope.tracks[key].track.name
	                        }
	                        $scope.broadcast_tracks.push(_obj);
                    	}
                        
                    }
                    console.log($scope.broadcast_tracks);
                    setTimeout(() => {
                    	if($scope.layout){
                    		set_layout($scope.layout);
                    	}
                    }, true);
                    $scope.status = 1;
                })

            }, true);


            $scope.$watch('layout', (newValue, oldValue) => {
                console.log(newValue);
                console.log(oldValue);
                if (!newValue) {
                    return;
                } else {
                	set_layout(newValue);
                }
                
            }, true);
            function set_layout(layout) {
                $scope.$evalAsync(($scope) => {

                    var canvas = document.getElementById('canvas');
                    var context = canvas.getContext('2d');
                    context.clearRect(0, 0, 1920, 1080);


                    console.log('selected_layout = ' + layout);
                    var _length = 0;
                    var _screen_length = 0;
                    $scope.selected_layout = true;

                    for (var idx = 0; idx < $scope.broadcast_tracks.length; idx++) {
                        if ($scope.broadcast_tracks[idx].type == 'video') {
                            _length++;
                        }
                        if ($scope.broadcast_tracks[idx].type == 'screen') {
                            _screen_length++;
                        }

                        $scope.broadcast_tracks[idx].style.top = '0%';
                        $scope.broadcast_tracks[idx].style.left = '0%';
                        $scope.broadcast_tracks[idx].style.width = '100%';
                        $scope.broadcast_tracks[idx].style.height = '100%';
                        $scope.broadcast_tracks[idx].style.opacity = '0';
                    }

                    console.log('----length---' + _length);

                    if (_length == 0) {
                        if (_screen_length == 0) {
                            $scope.selected_layout = false;
                            $scope.show_loading = false;
                            return;
                        } else {
                            $scope.selected_layout = false;
                        }
                    }

                    switch (layout) {
                        case 1:
                            for (var idx = 0; idx < $scope.broadcast_tracks.length; idx++) {
                                if ($scope.broadcast_tracks[idx].type == 'video') {
                                    $scope.broadcast_tracks[idx].style.top = '0%';
                                    $scope.broadcast_tracks[idx].style.left = '0%';
                                    $scope.broadcast_tracks[idx].style.width = '100%';
                                    $scope.broadcast_tracks[idx].style.height = '100%';
                                    $scope.broadcast_tracks[idx].style.opacity = '1';
                                    break;
                                }
                            }
                            break;
                        case 2:
                            var _index = 0;
                            for (var idx = 0; idx < $scope.broadcast_tracks.length; idx++) {
                                if ($scope.broadcast_tracks[idx].type == 'video') {
                                    $scope.broadcast_tracks[idx].style.top = '10%';
                                    $scope.broadcast_tracks[idx].style.left = 5 + _index * 90 / _length + '%';
                                    $scope.broadcast_tracks[idx].style.width = 90 / _length + '%';
                                    $scope.broadcast_tracks[idx].style.height = '80%';
                                    $scope.broadcast_tracks[idx].style.opacity = '1';
                                    _index++;
                                }
                            }
                            break;
                        case 3:
                            var _index = 0;
                            for (var idx = 0; idx < $scope.broadcast_tracks.length; idx++) {
                                if ($scope.broadcast_tracks[idx].type == 'video') {
                                    $scope.broadcast_tracks[idx].style.top = '10%';
                                    $scope.broadcast_tracks[idx].style.left = 5 + _index * 90 / _length + '%';
                                    $scope.broadcast_tracks[idx].style.width = 90 / _length + '%';
                                    $scope.broadcast_tracks[idx].style.height = '80%';
                                    $scope.broadcast_tracks[idx].style.opacity = '1';
                                    _index++;
                                }
                            }
                            break;
                        case 4:
                            var width = $('#videos-container').width();
                            var half = Math.round(_length / 2);
                            var item_width = 0;
                            var item_height = 0;
                            if (half == 1) {
                                item_width = 49;
                                item_height = 49;
                            } else if (half == 2) {
                                item_width = 49;
                                item_height = 49;
                            } else if (half == 3) {
                                item_width = 32;
                                item_height = 49;
                            }
                            var _index = 0;
                            for (var idx = 0; idx < $scope.broadcast_tracks.length; idx++) {
                                if ($scope.broadcast_tracks[idx].type == 'video') {
                                    if (_index <= half - 1) {
                                        $scope.broadcast_tracks[idx].style.top = '0%';
                                        $scope.broadcast_tracks[idx].style.left = (100 - item_width * half) / (half + 1) + _index * item_width + (100 - item_width * half) / (half + 1) * _index + '%';
                                    } else {
                                        $scope.broadcast_tracks[idx].style.top = '51%';
                                        $scope.broadcast_tracks[idx].style.left = (100 - item_width * half) / (half + 1) + (Math.abs(half - _index)) * item_width + (100 - item_width * half) / (half + 1) * Math.abs(half - _index) + '%';
                                    }
                                    $scope.broadcast_tracks[idx].style.width = item_width + '%';
                                    $scope.broadcast_tracks[idx].style.height = item_height + '%';
                                    $scope.broadcast_tracks[idx].style.opacity = '1';
                                    _index++;
                                }
                            }
                            break;
                        case 5:
                            for (var idx = 0; idx < $scope.broadcast_tracks.length; idx++) {
                                if ($scope.broadcast_tracks[idx].type == 'video') {
                                    $scope.broadcast_tracks[idx].style.top = '35%';
                                    $scope.broadcast_tracks[idx].style.left = '82%';
                                    $scope.broadcast_tracks[idx].style.width = '18%';
                                    $scope.broadcast_tracks[idx].style.height = '30%';
                                    $scope.broadcast_tracks[idx].style.opacity = '1';
                                    break;
                                }
                            }
                            for (var idx = 0; idx < $scope.broadcast_tracks.length; idx++) {
                                if ($scope.broadcast_tracks[idx].type == 'screen') {
                                    $scope.broadcast_tracks[idx].style.top = '0%';
                                    $scope.broadcast_tracks[idx].style.left = '0%';
                                    $scope.broadcast_tracks[idx].style.width = '80%';
                                    $scope.broadcast_tracks[idx].style.height = '100%';
                                    $scope.broadcast_tracks[idx].style.opacity = '1';
                                    break;
                                }
                            }
                            break;
                        case 6:
                            var _index = 0;
                            for (var idx = 0; idx < $scope.broadcast_tracks.length; idx++) {
                                if ($scope.broadcast_tracks[idx].type == 'video') {
                                    $scope.broadcast_tracks[idx].style.top = 5 + _index * 90 / _length + '%';
                                    $scope.broadcast_tracks[idx].style.left = '82%';
                                    $scope.broadcast_tracks[idx].style.width = '18%';
                                    $scope.broadcast_tracks[idx].style.height = 90 / _length + '%';
                                    $scope.broadcast_tracks[idx].style.opacity = '1';
                                    _index++;
                                }
                            }
                            for (var idx = 0; idx < $scope.broadcast_tracks.length; idx++) {
                                if ($scope.broadcast_tracks[idx].type == 'screen') {
                                    $scope.broadcast_tracks[idx].style.top = '0%';
                                    $scope.broadcast_tracks[idx].style.left = '0%';
                                    $scope.broadcast_tracks[idx].style.width = '80%';
                                    $scope.broadcast_tracks[idx].style.height = '100%';
                                    $scope.broadcast_tracks[idx].style.opacity = '1';
                                    break;
                                }
                            }
                            break;
                        case 7:
                            var _index = 0;
                            for (var idx = 0; idx < $scope.broadcast_tracks.length; idx++) {
                                if ($scope.broadcast_tracks[idx].type == 'video') {
                                    $scope.broadcast_tracks[idx].style.top = 5 + _index * 90 / _length + '%';
                                    $scope.broadcast_tracks[idx].style.left = '82%';
                                    $scope.broadcast_tracks[idx].style.width = '18%';
                                    $scope.broadcast_tracks[idx].style.height = 90 / _length + '%';
                                    $scope.broadcast_tracks[idx].style.opacity = '1';
                                    _index++;
                                }
                            }
                            for (var idx = 0; idx < $scope.broadcast_tracks.length; idx++) {
                                if ($scope.broadcast_tracks[idx].type == 'screen') {
                                    $scope.broadcast_tracks[idx].style.top = '0%';
                                    $scope.broadcast_tracks[idx].style.left = '0%';
                                    $scope.broadcast_tracks[idx].style.width = '80%';
                                    $scope.broadcast_tracks[idx].style.height = '100%';
                                    $scope.broadcast_tracks[idx].style.opacity = '1';
                                    break;
                                }
                            }
                            break;
                        case 8:
                            for (var idx = 0; idx < $scope.broadcast_tracks.length; idx++) {
                                if ($scope.broadcast_tracks[idx].type == 'screen') {
                                    $scope.broadcast_tracks[idx].style.top = '0%';
                                    $scope.broadcast_tracks[idx].style.left = '0%';
                                    $scope.broadcast_tracks[idx].style.width = '100%';
                                    $scope.broadcast_tracks[idx].style.height = '100%';
                                    $scope.broadcast_tracks[idx].style.opacity = '1';
                                    break;
                                }
                            }
                            break;
                        case 9:
                            $scope.selected_layout = false;
                            break;
                        case 10:
                            $scope.selected_layout = false;
                            break;

                    }
                    $scope.show_loading = false;
                })

            }


        }
    }
});

BotApp.directive('participantBroadcastScreen', function (API_URL, $state, $rootScope) {
    return {
        restrict: 'E',
        replace: true,
        templateUrl: 'js/app/directives/participant-broadcast-screen.html',
        scope: {
            layout: '=layout',
            tracks: '=tracks',
            status: '=status',
            localVideoTrackName: '=localVideoTrackName',
            localScreenTrackName: '=localScreenTrackName',
            localAudioTrackName: '=localAudioTrackName'
        },
        link: function ($scope, element) {
            $scope.selected_layout = false;
            $scope.broadcast_tracks = [];
            $scope.show_loading = true;


            $scope.$watch('tracks', (newValue, oldValue) => {
				if (newValue !=oldValue){
					$scope.$evalAsync(($scope) => {
	                    $scope.broadcast_tracks = [];
	                    console.log($scope.tracks);
	                    for (var key in $scope.tracks) {
	                    	if(!$scope.broadcast_tracks.find(x => x.track_name == $scope.tracks[key].track.name)){
	                    		var _obj = {
		                            track: $scope.tracks[key].track,
		                            style: {
		                                top: '0%',
		                                left: '0%',
		                                width: '100%',
		                                height: '100%',
		                                opacity: '1'
		                            },
		                            type: $scope.tracks[key].type,
		                            track_name: $scope.tracks[key].track.name
		                        }
		                        $scope.broadcast_tracks.push(_obj);
	                    	}
	                        
	                    }
	                    console.log($scope.broadcast_tracks);
	                    setTimeout(() => {
	                    	if($scope.layout){
	                    		set_layout($scope.layout);
	                    	}
	                        
	                    }, true);
	                    $scope.status = 1;
	                })
				}
                

            }, true);


            $scope.$watch('layout', (newValue, oldValue) => {
                console.log(newValue);
                console.log(oldValue);
                if (!newValue) {
                    return;
                } else {
                	set_layout(newValue);
                }
                
            }, true);
            function set_layout(layout) {
                $scope.$evalAsync(($scope) => {
                    console.log('selected_layout = ' + layout);
                    var _length = 0;
                    var _screen_length = 0;
                    $scope.selected_layout = true;

                    for (var idx = 0; idx < $scope.broadcast_tracks.length; idx++) {
                        if ($scope.broadcast_tracks[idx].type == 'video') {
                            if ($scope.localVideoTrackName == $scope.broadcast_tracks[idx].track_name) {
                                $scope.$parent.set_local_track_num(_length);
                            }
                            _length++;
                        }
                        if ($scope.broadcast_tracks[idx].type == 'screen') {
                            if ($scope.localScreenTrackName == $scope.broadcast_tracks[idx].track_name) {
                                $scope.$parent.set_local_screen_track_num(_screen_length);
                            }
                            _screen_length++;
                        }

                        $scope.broadcast_tracks[idx].style.top = '0%';
                        $scope.broadcast_tracks[idx].style.left = '0%';
                        $scope.broadcast_tracks[idx].style.width = '100%';
                        $scope.broadcast_tracks[idx].style.height = '100%';
                        $scope.broadcast_tracks[idx].style.opacity = '0';
                    }

                    $scope.$parent.setVideoLength(_length);
                    $scope.$parent.setScreenLength(_screen_length);



                    console.log('----length---' + _length);

                    if (_length == 0) {
                        if (_screen_length == 0) {
                            $scope.selected_layout = false;
                            $scope.show_loading = false;
                            return;
                        } else {
                            $scope.selected_layout = false;
                        }
                    }

                    switch (layout) {
                        case 1:
                            for (var idx = 0; idx < $scope.broadcast_tracks.length; idx++) {
                                if ($scope.broadcast_tracks[idx].type == 'video') {
                                    $scope.broadcast_tracks[idx].style.top = '0%';
                                    $scope.broadcast_tracks[idx].style.left = '0%';
                                    $scope.broadcast_tracks[idx].style.width = '100%';
                                    $scope.broadcast_tracks[idx].style.height = '100%';
                                    $scope.broadcast_tracks[idx].style.opacity = '1';
                                    break;
                                }
                            }
                            break;
                        case 2:
                            var _index = 0;
                            for (var idx = 0; idx < $scope.broadcast_tracks.length; idx++) {
                                if ($scope.broadcast_tracks[idx].type == 'video') {
                                    $scope.broadcast_tracks[idx].style.top = '10%';
                                    $scope.broadcast_tracks[idx].style.left = 5 + _index * 90 / _length + '%';
                                    $scope.broadcast_tracks[idx].style.width = 90 / _length + '%';
                                    $scope.broadcast_tracks[idx].style.height = '80%';
                                    $scope.broadcast_tracks[idx].style.opacity = '1';
                                    _index++;
                                }
                            }
                            break;
                        case 3:
                            var _index = 0;
                            for (var idx = 0; idx < $scope.broadcast_tracks.length; idx++) {
                                if ($scope.broadcast_tracks[idx].type == 'video') {
                                    $scope.broadcast_tracks[idx].style.top = '10%';
                                    $scope.broadcast_tracks[idx].style.left = 5 + _index * 90 / _length + '%';
                                    $scope.broadcast_tracks[idx].style.width = 90 / _length + '%';
                                    $scope.broadcast_tracks[idx].style.height = '80%';
                                    $scope.broadcast_tracks[idx].style.opacity = '1';
                                    _index++;
                                }
                            }
                            break;
                        case 4:
                            var width = $('#videos-container').width();
                            var half = Math.round(_length / 2);
                            var item_width = 0;
                            var item_height = 0;
                            if (half == 1) {
                                item_width = 49;
                                item_height = 49;
                            } else if (half == 2) {
                                item_width = 49;
                                item_height = 49;
                            } else if (half == 3) {
                                item_width = 32;
                                item_height = 49;
                            }
                            var _index = 0;
                            for (var idx = 0; idx < $scope.broadcast_tracks.length; idx++) {
                                if ($scope.broadcast_tracks[idx].type == 'video') {
                                    if (_index <= half - 1) {
                                        $scope.broadcast_tracks[idx].style.top = '0%';
                                        $scope.broadcast_tracks[idx].style.left = (100 - item_width * half) / (half + 1) + _index * item_width + (100 - item_width * half) / (half + 1) * _index + '%';
                                    } else {
                                        $scope.broadcast_tracks[idx].style.top = '51%';
                                        $scope.broadcast_tracks[idx].style.left = (100 - item_width * half) / (half + 1) + (Math.abs(half - _index)) * item_width + (100 - item_width * half) / (half + 1) * Math.abs(half - _index) + '%';
                                    }
                                    $scope.broadcast_tracks[idx].style.width = item_width + '%';
                                    $scope.broadcast_tracks[idx].style.height = item_height + '%';
                                    $scope.broadcast_tracks[idx].style.opacity = '1';
                                    _index++;
                                }
                            }
                            break;
                        case 5:
                            for (var idx = 0; idx < $scope.broadcast_tracks.length; idx++) {
                                if ($scope.broadcast_tracks[idx].type == 'video') {
                                    $scope.broadcast_tracks[idx].style.top = '35%';
                                    $scope.broadcast_tracks[idx].style.left = '82%';
                                    $scope.broadcast_tracks[idx].style.width = '18%';
                                    $scope.broadcast_tracks[idx].style.height = '30%';
                                    $scope.broadcast_tracks[idx].style.opacity = '1';
                                    break;
                                }
                            }
                            for (var idx = 0; idx < $scope.broadcast_tracks.length; idx++) {
                                if ($scope.broadcast_tracks[idx].type == 'screen') {
                                    $scope.broadcast_tracks[idx].style.top = '0%';
                                    $scope.broadcast_tracks[idx].style.left = '0%';
                                    $scope.broadcast_tracks[idx].style.width = '80%';
                                    $scope.broadcast_tracks[idx].style.height = '100%';
                                    $scope.broadcast_tracks[idx].style.opacity = '1';
                                    break;
                                }
                            }
                            break;
                        case 6:
                            var _index = 0;
                            for (var idx = 0; idx < $scope.broadcast_tracks.length; idx++) {
                                if ($scope.broadcast_tracks[idx].type == 'video') {
                                    $scope.broadcast_tracks[idx].style.top = 5 + _index * 90 / _length + '%';
                                    $scope.broadcast_tracks[idx].style.left = '82%';
                                    $scope.broadcast_tracks[idx].style.width = '18%';
                                    $scope.broadcast_tracks[idx].style.height = 90 / _length + '%';
                                    $scope.broadcast_tracks[idx].style.opacity = '1';
                                    _index++;
                                }
                            }
                            for (var idx = 0; idx < $scope.broadcast_tracks.length; idx++) {
                                if ($scope.broadcast_tracks[idx].type == 'screen') {
                                    $scope.broadcast_tracks[idx].style.top = '0%';
                                    $scope.broadcast_tracks[idx].style.left = '0%';
                                    $scope.broadcast_tracks[idx].style.width = '80%';
                                    $scope.broadcast_tracks[idx].style.height = '100%';
                                    $scope.broadcast_tracks[idx].style.opacity = '1';
                                    break;
                                }
                            }
                            break;
                        case 7:
                            var _index = 0;
                            for (var idx = 0; idx < $scope.broadcast_tracks.length; idx++) {
                                if ($scope.broadcast_tracks[idx].type == 'video') {
                                    $scope.broadcast_tracks[idx].style.top = 5 + _index * 90 / _length + '%';
                                    $scope.broadcast_tracks[idx].style.left = '82%';
                                    $scope.broadcast_tracks[idx].style.width = '18%';
                                    $scope.broadcast_tracks[idx].style.height = 90 / _length + '%';
                                    $scope.broadcast_tracks[idx].style.opacity = '1';
                                    _index++;
                                }
                            }
                            for (var idx = 0; idx < $scope.broadcast_tracks.length; idx++) {
                                if ($scope.broadcast_tracks[idx].type == 'screen') {
                                    $scope.broadcast_tracks[idx].style.top = '0%';
                                    $scope.broadcast_tracks[idx].style.left = '0%';
                                    $scope.broadcast_tracks[idx].style.width = '80%';
                                    $scope.broadcast_tracks[idx].style.height = '100%';
                                    $scope.broadcast_tracks[idx].style.opacity = '1';
                                    break;
                                }
                            }
                            break;
                        case 8:
                            for (var idx = 0; idx < $scope.broadcast_tracks.length; idx++) {
                                if ($scope.broadcast_tracks[idx].type == 'screen') {
                                    $scope.broadcast_tracks[idx].style.top = '0%';
                                    $scope.broadcast_tracks[idx].style.left = '0%';
                                    $scope.broadcast_tracks[idx].style.width = '100%';
                                    $scope.broadcast_tracks[idx].style.height = '100%';
                                    $scope.broadcast_tracks[idx].style.opacity = '1';
                                    break;
                                }
                            }
                            break;
                        case 9:
                            $scope.selected_layout = false;
                            break;
                        case 10:
                            $scope.selected_layout = false;
                            break;

                    }
                    $scope.show_loading = false;
                })

            }


        }
    }
});
BotApp.directive('participantCam', function (API_URL, $state, $rootScope) {
    return {
        restrict: 'E',
        replace: true,
        templateUrl: 'js/app/directives/participant-cam.html',
        scope: {
            participant: '=participant'
        },
        link: function ($scope, element, $http) {


        }
    }
});
BotApp.directive('adminParticipantCam', function (API_URL, $state, $rootScope) {
    return {
        restrict: 'E',
        replace: true,
        templateUrl: 'js/app/directives/admin-participant-cam.html',
        scope: {
            participant: '=participant'
        },
        link: function ($scope, element, $http) {

            var videoTrack = null;
            $scope.show_name = function (item, identity) {
                item.names_shown = true;
                $scope.$parent.show_name(item.tracks, identity);
            }
            $scope.unshow_name = function (item, identity) {
                item.names_shown = false;
                $scope.$parent.unshow_name(item.tracks, identity);
            }
            $scope.mute_audio = function (item, identity) {
                item.muted = true;
                var audio_track = item.tracks.find(x => x.type == 'audio');
                if (audio_track) {
                    $scope.$parent.mute_participant_audio(identity);
                }
            }
            $scope.unmute_audio = function (item, identity) {
                item.muted = false;
                var audio_track = item.tracks.find(x => x.type == 'audio');
                if (audio_track) {
                    $scope.$parent.unmute_participant_audio(identity);
                }
            }
            $scope.add_to_stream = function (item, tracks, track_type, track_identity) {
                if (track_type == 'screen') {
                    $scope.$parent.remove_screen_stream();
                }
                item.added = true;
                $scope.$parent.add_to_stream(tracks, track_type, track_identity);
            }
            $scope.remove_stream = function (item, tracks, track_type, track_identity) {
                item.added = false;
                $scope.$parent.remove_stream(tracks, track_type, track_identity);
            }
            if ($scope.participant.local) {

                // var mediaElement = $scope.participant.track.attach();
                // element[0].prepend(mediaElement);

            } else {
                // $scope.participant.participant.on('trackAdded', attachTrack);
                // $scope.participant.participant.on('trackRemoved', detachTrack);


                // function attachTrack(track) {
                //     console.log(track);
                //     if (track.kind == 'video') {
                //         videoTrack = track;
                //         $scope.$parent.add_to_track(track, $scope.participant.participant.identity);
                //     }

                //     var mediaElement = track.attach();

                //     console.log(mediaElement);
                //     element[0].prepend(mediaElement);
                //     // if (track.kind == 'video') updateDisplay(1);
                // }

                // function detachTrack(track) {
                // }
            }

        }
    }
});
BotApp.directive('trackItem', function (API_URL, $state, $rootScope) {
    return {
        restrict: 'E',
        replace: true,
        templateUrl: 'js/app/directives/track-item.html',
        scope: {
            track: '=track',
            local: '=local'
        },
        link: function ($scope, element) {

        }
    }
});
BotApp.directive('preCam', function (API_URL, $state, $rootScope) {
    return {
        restrict: 'E',
        replace: true,
        templateUrl: 'js/app/directives/pre-cam.html',
        scope: {
            track: '=track',
            local: '=local'
        },
        link: function ($scope, element) {
            var mediaElement = $scope.track.attach();
            element[0].append(mediaElement);

            if ($scope.local) {
                // var canvas = document.getElementById('canvas_self');
                // var context = canvas.getContext('2d');
                // draw_video(mediaElement, context, 0, 0, 320, 240);

                // function draw_video(video, context, x, y, width, height) {
                //     context.drawImage(video, x, y, width, height);
                //     const h = setTimeout(draw_video, 10, video, context, x, y, width, height);
                //     handles.push(h);
                //     $rootScope.handles.push(h);
                // }
            }
        }
    }
});
BotApp.directive('preAudio', function (API_URL, $state, $rootScope) {
    return {
        restrict: 'E',
        replace: true,
        templateUrl: 'js/app/directives/pre-audio.html',
        scope: {
            track: '=track',
            local: '=local'
        },
        link: function ($scope, element) {
            var mediaElement = $scope.track.attach();
            if ($scope.local) {
                mediaElement.muted = true;
            }
            element[0].append(mediaElement);
        }
    }
});
BotApp.directive('ngEnter', function () {
    return function (scope, element, attrs) {
        element.bind("keydown keypress", function (event) {
            if (event.which === 13) {
                scope.$apply(function () {
                    scope.$eval(attrs.ngEnter, { 'event': event });
                });

                event.preventDefault();
            }
        });
    };
});

BotApp.filter('characters', function ($filter) {
    return function (input, chars, breakOnWord) {
        if (isNaN(chars)) return input;
        if (chars <= 0) return "";
        if (input && input.length > chars) {
            if (input = input.substring(0, chars), breakOnWord)
                for (;
                    " " === input.charAt(input.length - 1);) input = input.substr(0, input.length - 1);
            else {
                var lastspace = input.lastIndexOf(" ");
                lastspace !== -1 && (input = input.substr(0, lastspace))
            }
            return input + "…"
        }
        return input
    }
});

