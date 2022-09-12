/* Setup Home page controller */
angular.module('BotApp').controller('PresenterController', function ($rootScope, $scope, $state, $http, $window, $cookies, $timeout, API_URL, $localStorage, socket) {
    var canvas = document.getElementById('canvas_self');
    var screen_canvas = document.getElementById('canvas_screen');
    var context = canvas.getContext('2d');
    var screen_context = screen_canvas.getContext('2d');
	
    $scope.recordingStarted = false;

    $scope.logined = false;
    $scope.participants = [];
    $scope.tracks = [];
    $scope.selected_tracks = [];
    $scope.selected_track_infos = [];
    $scope.track_id_arr = [];

    $scope.height = parseInt($(window).height() - 300);
    $scope.width = parseInt($scope.height * 1.77);
    $scope.$on('$viewContentLoaded', function () {
        // initialize core components
    });


    function create_UUID() {
        var dt = new Date().getTime();
        var uuid = 'user-xxxxxxxxxx'.replace(/[xy]/g, function (c) {
            var r = (dt + Math.random() * 16) % 16 | 0;
            dt = Math.floor(dt / 16);
            return (c == 'x' ? r : (r & 0x3 | 0x8)).toString(16);
        });
        return uuid;
    }
    $scope.presenter_name = create_UUID();
    var Video = Twilio.Video;
    var room;
    var enableVideo = true; //default. Set to false for broadcasting apps
    var enableAudio = true; //default. Set to false for avoiding echoes in demos
    var numVideoTracks = 0;

	socket.on('accessTokenFailed', (obj)=>{
		alert('password is not correct');
		setTimeout(() => {
			location.reload();
		}, 2000);
	});
    socket.on('accessToken', connectToRoom);
    const connectOptions = {
        // Available only in Small Group or Group Rooms only. Please set "Room Type"
        // to "Group" or "Small Group" in your Twilio Console:
        // https://www.twilio.com/console/video/configure
        bandwidthProfile: {
            video: {
                dominantSpeakerPriority: 'standard',
                mode: 'presentation',
                renderDimensions: {
                    high: { height: 1080, width: 1920 },
                    standard: { height: 720, width: 1280 },
                    low: { height: 176, width: 144 }
                }
            }
        },

        // Available only in Small Group or Group Rooms only. Please set "Room Type"
        // to "Group" or "Small Group" in your Twilio Console:
        // https://www.twilio.com/console/video/configure
        dominantSpeaker: true,

        // Comment this line to disable verbose logging.
        // logLevel: 'debug',

        // Comment this line if you are playing music.
        maxAudioBitrate: 16000,

        // VP8 simulcast enables the media server in a Small Group or Group Room
        // to adapt your encoded video quality for each RemoteParticipant based on
        // their individual bandwidth constraints. This has no utility if you are
        // using Peer-to-Peer Rooms, so you can comment this line.
        preferredVideoCodecs: [{ codec: 'VP8', simulcast: true }],

        // Capture 720p video @ 24 fps.
        video: { height: 720, frameRate: 24, width: 1280 },
        audio: true
    };




    $scope.screen_shared = false;
    $scope.local_audio_track = null;
    $scope.local_video_track = null;
    $scope.local_video_screen_track = null;

    $scope.get_local_audio = function () {
        navigator.mediaDevices.getUserMedia({ audio: true }).then(stream => {
            //$scope.local_audio_track = stream.getTracks()[0];
            //console.log($scope.local_audio_track);
        }).catch(() => {
            alert('Could not share the screen.')
        });

    }

    $scope.share_screen = function () {
        navigator.mediaDevices.getDisplayMedia().then(stream => {
            screenTrack = new Twilio.Video.LocalVideoTrack(stream.getTracks()[0], { name: $scope.presenter_name + '-screenshare' });
            $scope.room.localParticipant.publishTrack(screenTrack);
            $scope.screen_shared = true;

        }).catch(() => {
            alert('Could not share the screen.')
        });
    }
    $scope.leave_room = function () {
        location.reload(true);
    }
    $scope.muted = false;
    $scope.mic_control = function () {
        $scope.room.localParticipant.tracks.forEach(function (track) {
            if (track.kind == 'audio') {
                if (track.isEnabled) {
                    track.disable();
                    $scope.muted = true;
                    socket.emit('mute', { session_link: $scope.session_link, identity: $scope.presenter_name });
                } else {
                    track.enable();
                    $scope.muted = false;
                    socket.emit('unmute', { session_link: $scope.session_link, identity: $scope.presenter_name });
                }
            }
        });
    }
    socket.on('mute', (obj) => {
        if (obj.session_link == $scope.session_link) {
            if (obj.identity == $scope.presenter_name) {

                $scope.room.localParticipant.tracks.forEach(function (track) {
                    if (track.kind == 'audio') {
                        track.disable();
                        $scope.muted = true;
                    }
                });
            }
        }
    })
    socket.on('unmute', (obj) => {
        if (obj.session_link == $scope.session_link) {
            if (obj.identity == $scope.presenter_name) {
                $scope.room.localParticipant.tracks.forEach(function (track) {
                    if (track.kind == 'audio') {
                        track.enable();
                        $scope.muted = false;
                    }
                });
            }
        }
    })
    $scope.myVideoMediaElement = null;
    $scope.myScreenMediaElement = null;

    $scope.local_video_track_name = null;
    $scope.local_screen_track_name = null;
    $scope.local_audio_track_name = null;



    $scope.record_stream = null;
    $scope.record_screen_stream = null;


    let mediaRecorder = null;
    let mediaScreenRecorder = null;

    function draw_video(video, context, x, y, width, height) {
        context.drawImage(video, x, y, width, height);
        const h1 = setTimeout(draw_video, 10, video, context, x, y, width, height);
    }
    function draw_screen(video, context, x, y, width, height) {
        context.drawImage(video, x, y, width, height);
        const h2 = setTimeout(draw_screen, 10, video, context, x, y, width, height);
    }
    $scope.changed_layout = false;
    $scope.$watch('layout', (newValue, oldValue) => {
        console.log('newValue = ' + newValue);
        console.log('oldValue = ' + oldValue);
        if (newValue != oldValue) {
            $scope.changed_layout = true;
        }
    })
    let blobs = [];



    socket.on('record_started', (obj) => {
        if (obj.session_link == $scope.session_link) {
            $scope.$evalAsync(($scope) => {
                $scope.recordingStarted = true;
            })


            //$scope.record_stream = canvas.captureStream(30); // frames per second


            let new_stream = new MediaStream([$scope.local_audio_track, $scope.local_video_track]);

            mediaRecorder = new MediaRecorder(new_stream, {
                mimeType: 'video/webm;codecs=vp9',
                videoBitsPerSecond: 3000000
            });

            mediaRecorder.addEventListener('dataavailable', (e) => {
                console.log(e.data);
                socket.emit('send_file', { session_link: $scope.session_link, track_name: $scope.local_video_track_name, data: e.data });
            });

            mediaRecorder.start(1000); // Start recording, and dump data every second



            let new_screen_stream = new MediaStream([$scope.local_video_screen_track]);
            mediaScreenRecorder = new MediaRecorder(new_screen_stream, {
                mimeType: 'video/webm;codecs=vp9',
                videoBitsPerSecond: 3000000
            });

            mediaScreenRecorder.addEventListener('dataavailable', (e) => {
                console.log(e.data);
                socket.emit('send_file', { session_link: $scope.session_link, track_name: $scope.local_screen_track_name, data: e.data });
            });

            mediaScreenRecorder.start(1000); // Start recording, and dump data every second


        }
    })
    socket.on('record_stop', (obj) => {
        mediaRecorder.stop();
        mediaScreenRecorder.stop();
        // socket.emit('send_canvas', { session_link: $scope.session_link, data: blobs });

    })

    socket.on('recording_status', (obj) => {
        console.log('--------------------------------------');
        console.log(obj);
        if (obj.session_link == $scope.session_link) {

            if ($scope.recordingStarted) {
                return;
            }
            if (obj.recording_status) {
                $scope.recordingStarted = true;
                //$scope.record_stream = canvas.captureStream(30); // frames per second


                let new_stream = new MediaStream([$scope.local_audio_track, $scope.local_video_track]);

                mediaRecorder = new MediaRecorder(new_stream, {
                    mimeType: 'video/webm;codecs=vp9',
                    videoBitsPerSecond: 3000000
                });

                mediaRecorder.addEventListener('dataavailable', (e) => {
                    console.log(e.data);
                    socket.emit('send_file', { session_link: $scope.session_link, track_name: $scope.local_video_track_name, data: e.data });
                });

                mediaRecorder.start(1000); // Start recording, and dump data every second


                //$scope.record_screen_stream = screen_canvas.captureStream(30);
                let new_screen_stream = new MediaStream([$scope.local_video_screen_track]);
                mediaScreenRecorder = new MediaRecorder(new_screen_stream, {
                    mimeType: 'video/webm;codecs=vp9',
                    videoBitsPerSecond: 3000000
                });

                mediaScreenRecorder.addEventListener('dataavailable', (e) => {
                    console.log(e.data);
                    socket.emit('send_file', { session_link: $scope.session_link, track_name: $scope.local_screen_track_name, data: e.data });
                });

                mediaScreenRecorder.start(1000); // Start recording, and dump data every second
            }
        }
    })


    function connectToRoom(msg) {
        $scope.meeting_title = msg.roomName;
        console.log("Connecting to room " + msg.roomName + " with jwtToken: " + msg.jwtToken);
        Video.connect(msg.jwtToken, connectOptions).then((room) => {
            $scope.$evalAsync(($scope) => {
                socket.emit('recording_status', { session_link: $scope.session_link });
                room = room;
                $scope.room = room;
                console.log(room);
                //Display local tracks, if any
                console.log("Attaching local tracks");

                var obj = {
                    local: 1,
                    item: [{
                        type: 'camera',
                        tracks: [],
                        added: false
                    }],
                    identity: room.localParticipant.identity
                };

                room.localParticipant.tracks.forEach(function (track) {


                    if (track.kind == 'audio') {
                        //$scope.get_local_audio();
                        var _obj = {
                            type: 'audio',
                            track: track
                        };
                        $scope.local_audio_track_name = track.name;



                        $scope.local_audio_track = track.mediaStreamTrack;

                        console.log($scope.local_audio_track);

                        obj.item[0].tracks.push(_obj);
                        $scope.add_to_track(track.name, track, 'audio', room.localParticipant.identity);

                    } else if (track.kind == 'video') {
                        var _obj = {
                            type: 'video',
                            track: track
                        };
                        $scope.local_video_track_name = track.name;


                        $scope.local_video_track = track.mediaStreamTrack;

                        //$scope.myVideoMediaElement = track.attach();
                        //draw_video($scope.myVideoMediaElement, context, 0, 0, 320, 240);


                        obj.item[0].tracks.push(_obj);
                        $scope.add_to_track(track.name, track, 'video', room.localParticipant.identity);

                    } else {

                    }


                });
                $scope.participants.push(obj);


                room.localParticipant.on('trackAdded', attachLocalTrack => {

                    $scope.$evalAsync(($scope) => {

                        if (attachLocalTrack.name == room.localParticipant.identity + '-screenshare') {

                            var _item = {
                                type: 'screen',
                                tracks: [{ type: 'video', track: attachLocalTrack }],
                                added: false
                            };

                            $scope.local_screen_track_name = attachLocalTrack.name;

                            $scope.local_video_screen_track = attachLocalTrack.mediaStreamTrack;
                            //draw_screen($scope.myScreenMediaElement, screen_context, 0, 0, 320, 240);

                            _participant = $scope.participants.find(x => x.identity == room.localParticipant.identity);
                            _participant.item.push(_item);

                            $scope.add_to_track(attachLocalTrack.name, attachLocalTrack, 'screen', room.localParticipant.identity);
                        }
                    });


                });
                room.localParticipant.on('trackRemoved', detachLocalTrack);


                //Display currently connected participants' tracks, if any
                console.log("Managing pre-existing participants");
                room.participants.forEach((participant) => {

                    participant.tracks.forEach(attachTrack);
                    manageConnectedParticipant(participant);


                });

                //Add handlers for managing participants events
                room.on('participantConnected', manageConnectedParticipant);
                room.on('participantDisconnected', manageDisconnectedParticipant);
                room.on('disconnected', manageDisconnected)

                updateNumParticipants();

                setTimeout(() => {
                    socket.emit('get_selected_track', { session_link: $scope.session_link });
                    socket.emit('get_layout', { session_link: $scope.session_link });
                }, 5000);
            })

        });
    }
    function manageConnectedParticipant(participant) {
        participant.on('trackAdded', attachTrack => {
            $scope.$evalAsync(($scope) => {
                console.log(attachTrack);
                var screenShare = false;
                if (attachTrack.name == participant.identity + '-screenshare') {
                    screenShare = true;
                    if (attachTrack.kind == 'video') {
                        _track = { type: 'video', track: attachTrack };
                    }

                    $scope.add_to_track(attachTrack.name, attachTrack, 'screen', participant.identity);
                } else {
                    screenShare = false;

                    if (attachTrack.kind == 'video') {
                        _track = { type: 'video', track: attachTrack };
                        $scope.add_to_track(attachTrack.name, attachTrack, 'video', participant.identity);

                    } else if (attachTrack.kind == 'audio') {
                        _track = { type: 'audio', track: attachTrack };
                        $scope.add_to_track(attachTrack.name, attachTrack, 'audio', participant.identity);

                    } else {
                    }


                }

                console.log(_track);


                _participant = $scope.participants.find(x => x.identity == participant.identity);

                if (!_participant) {

                    if (screenShare) {
                        var obj = {
                            local: 0,
                            item: [{
                                type: 'screen',
                                tracks: [],
                                added: false
                            }],
                            identity: participant.identity
                        };
                        obj.item[0].tracks.push(_track);
                    } else {
                        var obj = {
                            local: 0,
                            item: [{
                                type: 'camera',
                                tracks: [],
                                added: false
                            }],
                            identity: participant.identity
                        };
                        obj.item[0].tracks.push(_track);
                    }


                    console.log('-----------1-----------');
                    $scope.participants.push(obj);
                    console.log($scope.participants);

                } else {
                    if (screenShare) {

                        if (exist_item = _participant.item.find(x => x.type == 'screen')) {

                        } else {
                            var obj = {
                                type: 'screen',
                                tracks: [],
                                added: false
                            };
                            obj.tracks.push(_track);
                            _participant.item.push(obj);
                        }
                    } else {
                        if (exist_item = _participant.item.find(x => x.type == 'camera')) {
                            exist_item.tracks.push(_track);
                        } else {
                            var obj = {
                                type: 'camera',
                                tracks: [],
                                added: false
                            };
                            obj.tracks.push(_track);
                            _participant.item.push(obj);

                        }
                        console.log('-----------2-----------');
                        console.log($scope.participants);
                    }
                }
            });

        });

    }

    function manageDisconnectedParticipant(participant) {


		

        $scope.$evalAsync(($scope) => {
            console.log(participant);
            console.log($scope.participants);
            var _part = $scope.participants.find(x => x.identity == participant.identity);
            if (_part) {

                for (var key in _part.item) {
                    $scope.remove_stream(_part.item[key].tracks, _part.item[key].type, participant.identity);
                }

                console.log($scope.participants.indexOf(_part));
                $scope.participants.splice($scope.participants.indexOf(_part), 1);
            }
        })
    }
    $scope.remove_stream = function (tracks, track_type, track_identity) {
        console.log($scope.selected_tracks);
        $scope.$evalAsync(($scope) => {
            if (track_type == 'screen') {
                var _track = $scope.selected_tracks.find(x => x.track.name == tracks[0].track.name);
                if (_track) {
                    $scope.selected_tracks.splice($scope.selected_tracks.indexOf(_track), 1);
                }
            } else {
                for (var key in tracks) {
                    var _track = $scope.selected_tracks.find(x => x.track.name == tracks[key].track.name);
                    if (_track) {
                        $scope.selected_tracks.splice($scope.selected_tracks.indexOf(_track), 1);
                    }
                }
            }
        });
        console.log($scope.selected_tracks);
    }
    function manageDisconnected(room, error) {
        if (error) {
            console.log("Disconnect error: " + error);
        }
        console.log(room.participants);

        room.participants.forEach(function (participant) {
            manageDisconnectedParticipant(participant);
        })

        room.localParticipant.tracks.forEach(function (track) {
            track.stop();
            detachTrack(track);
            updateNumParticipants()
        });
    }
    function attachLocalTrack(track) {
        $scope.$evalAsync(($scope) => {
            if (track.kind == 'audio') return;

            if (track.name == $scope.room.localParticipant.identity + '-screenshare') {
                var obj = {
                    local: 1,
                    type: 'screen',
                    track: track,
                    identity: $scope.room.localParticipant.identity
                }
                $scope.tracks.push(obj);
            }
        })


    }
    function detachLocalTrack(track) {
        console.log('detachTrack');
        console.log(track);
    }
    function attachTrack(track) {
        // var mediaElement = track.attach();
        // document.getElementById('divRemoteVideoContainer').appendChild(mediaElement);
        // if (track.kind == 'video') updateDisplay(1);
    }

    function detachTrack(track) {
        // track.detach().forEach(function (el) {
        //     el.remove();
        // });
        // if (track.kind == 'video') updateDisplay(-1)
    }

    function updateNumParticipants() {
        //var labelNumParticipants = document.getElementById('labelNumParticipants');
        //labelNumParticipants.innerHTML = room.participants.size + 1;
    }

    function updateDisplay(num) {
        numVideoTracks += num;
        var videoTagWidth = 100 / (1 + numVideoTracks);

        var remoteVideoTags = document.querySelectorAll("#divRemoteVideoContainer video")
        remoteVideoTags.forEach(function (videoTag) {
            videoTag.style.width = +videoTagWidth + '%';
        });
    }

    function getParameterByName(name, url) {
        if (!url) url = window.location.href;
        name = name.replace(/[\[\]]/g, "\\$&");
        var regex = new RegExp("[?&]" + name + "(=([^&#]*)|&|#|$)"),
            results = regex.exec(url);
        if (!results) return null;
        if (!results[2]) return '';
        return decodeURIComponent(results[2].replace(/\+/g, " "));
    }

    $scope.join = function () {
        if ($scope.session_id == '' || $scope.session_password == '') {
            return;
        }
        $scope.logined = true;
        $scope.session_link = $scope.session_id;
        
        socket.emit('checkRoomIfExist', {
            userName: $scope.presenter_name,
            session_link: $scope.session_link,
            session_password : $scope.session_password
        });
        
        /*socket.emit('getAccessToken', {
            userName: $scope.presenter_name,
            session_link: $scope.session_link,
            session_password : $scope.session_password
        });*/
    }
    $scope.meeting_status = 0;
    socket.on('meeting_status', (obj) => {
        if (obj.meeting_status) {
            $scope.$evalAsync(($scope) => {
                $scope.meeting_status = obj.meeting_status;
            })
        }
    })
    socket.on('update_track_info', (obj) => {
        $scope.$evalAsync(($scope) => {
            if (obj.session_link == $scope.session_link) {
                $scope.selected_track_infos = obj.selected_track_infos;
                console.log($scope.selected_track_infos);
            }
        })

    })
    $scope.add_to_track = function (track_name, track, type, identity) {
        var obj = {
            active: 0,
            track: track,
            type: type,
            identity: identity
        }
        $scope.selected_tracks.push(obj);

        var _obj = {
            label: 1,
            track_name: track_name
        }
        $scope.selected_track_infos.push(_obj);
    }
    $scope.layout = 4;

    socket.on('get_selected_track', (obj) => {
        $scope.$evalAsync(($scope) => {
            if (obj.session_link == $scope.session_link) {
                $scope.track_id_arr = obj.tracks;
            }
        })
    })
    socket.on('get_layout', (obj) => {
        $scope.$evalAsync(($scope) => {
            if (obj.session_link == $scope.session_link) {
                $scope.layout = obj.layout;
            }
        })
    })
    socket.on('set_layout', (obj) => {
        $scope.$evalAsync(($scope) => {
            if (obj.session_link == $scope.session_link) {
                $scope.layout = obj.layout;
            }
        });
    })
    socket.on('selected_track', (obj) => {
        $scope.$evalAsync(($scope) => {
            if (obj.session_link == $scope.session_link) {
                $scope.track_id_arr = obj.tracks;
            }
        })
    })

    $scope.video_length = 0;
    $scope.local_video_track_num = -1;
    $scope.local_screen_track_num = -1;
    $scope.setVideoLength = function (_length) {
        $scope.video_length = _length;
    }
    $scope.setScreenLength = function (_length) {
    }

    $scope.set_local_track_num = function (_num) {
        $scope.local_video_track_num = _num;
    }
    $scope.set_local_screen_track_num = function (_num) {
        console.log('set_local_screen_track_num');
        console.log(_num);
        $scope.local_screen_track_num = _num;
    }
    function getScreenPosition() {

        if ($scope.layout == 1) {

        } else if ($scope.layout == 2) {

        } else if ($scope.layout == 3) {

        } else if ($scope.layout == 4) {


        } else if ($scope.layout == 5) {
            if ($scope.local_screen_track_num == 0) {
                return { top: 0, left: 0, width: 80, height: 100, opacity: 1 };
            }
        } else if ($scope.layout == 6) {
            if ($scope.local_screen_track_num == 0) {
                return { top: 0, left: 0, width: 80, height: 100, opacity: 1 };
            }
        } else if ($scope.layout == 7) {
            if ($scope.local_screen_track_num == 0) {
                return { top: 0, left: 0, width: 80, height: 100, opacity: 1 };
            }
        } else if ($scope.layout == 8) {
            if ($scope.local_screen_track_num == 0) {
                return { top: 0, left: 0, width: 100, height: 100, opacity: 1 };
            }
        } else if ($scope.layout == 9) {

        } else if ($scope.layout == 10) {

        } else {

        }
        return { top: 0, left: 0, width: 100, height: 100, opacity: 0 };
    }
    function getCameraPosition() {

        if ($scope.layout == 1) {
            if ($scope.local_video_track_num == 0) {
                return { top: 0, left: 0, width: 100, height: 100, opacity: 1 };
            }
        } else if ($scope.layout == 2) {
            return { top: 10, left: 5 + $scope.local_video_track_num * 90 / $scope.video_length, width: 90 / $scope.video_length, height: 80, opacity: 1 };
        } else if ($scope.layout == 3) {
            return { top: 10, left: 5 + $scope.local_video_track_num * 90 / $scope.video_length, width: 90 / $scope.video_length, height: 80, opacity: 1 };
        } else if ($scope.layout == 4) {

            var half = Math.round($scope.video_length / 2);
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

            var _top = 0;
            var _left = 0;
            if ($scope.local_video_track_num <= half - 1) {
                _top = 0;
                _left = (100 - item_width * half) / (half + 1) + $scope.local_video_track_num * item_width + (100 - item_width * half) / (half + 1) * $scope.local_video_track_num;

            } else {
                _top = 51;
                _left = (100 - item_width * half) / (half + 1) + (Math.abs(half - $scope.local_video_track_num)) * item_width + (100 - item_width * half) / (half + 1) * Math.abs(half - $scope.local_video_track_num);
            }
            var _width = item_width;
            var _height = item_height;

            return { top: _top, left: _left, width: _width, height: _height, opacity: 1 };

        } else if ($scope.layout == 5) {
            if ($scope.local_video_track_num == 0) {
                return { top: 35, left: 82, width: 18, height: 30, opacity: 1 };
            }
        } else if ($scope.layout == 6) {
            return { top: 5 + $scope.local_video_track_num * 90 / $scope.video_length, left: 82, width: 18, height: 90 / $scope.video_length, opacity: 1 };
        } else if ($scope.layout == 7) {
            return { top: 5 + $scope.local_video_track_num * 90 / $scope.video_length, left: 82, width: 18, height: 90 / $scope.video_length, opacity: 1 };
        } else if ($scope.layout == 8) {

        } else if ($scope.layout == 9) {

        } else if ($scope.layout == 10) {

        } else {

        }
        return { top: 0, left: 0, width: 100, height: 100, opacity: 0 };



    }

    $scope.chat_contents = [];

    $scope.press_entered = function () {
        if ($scope.input_chat == '') {
            return;
        }
        var _obj = {
            message_type: 1,
            text: $scope.input_chat,
            name: $scope.presenter_name
        };

        $scope.chat_contents.push(_obj);
        setTimeout(() => {
            $('.chat-content').scrollTop(parseInt($('.chat-content .message-container').css('height')));
        }, 500);
        send_text();
        $scope.input_chat = '';

    }
    $scope.send = function () {
        if ($scope.input_chat == '') {
            return;
        }
        send_text();
        $scope.input_chat = '';
    }
    function send_text() {
        socket.emit('chat_text', { name: $scope.presenter_name, text: $scope.input_chat, session_link: $scope.session_link });
    }
    socket.on('chat_text', (obj) => {
        if ($scope.session_link == obj.session_link) {
            $scope.$evalAsync(($scope) => {
                var _obj = {
                    name: obj.name,
                    message_type: 0,
                    text: obj.text,
                }
                $scope.chat_contents.push(_obj);
                setTimeout(() => {
                    $('.chat-content').scrollTop(parseInt($('.chat-content .message-container').css('height')));
                }, 500);
            });
        }
    });
});
