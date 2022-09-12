/* Setup Home page controller */
angular.module('BotApp').controller('AdminController', function ($rootScope, $scope, $state, $http, $window, $cookies, $timeout, API_URL, $localStorage, socket) {

    $rootScope.handles = [];

    $scope.logined = false;
    $scope.participants = [];
    $scope.cams = [];
    $scope.layout = 0;
    $scope.screens = [];
    $scope.selected_tracks = [];
    $scope.selected_track_infos = [];
    $scope.track_id_arr = [];


    $scope.height = parseInt($(window).height() - 330);
    $scope.width = parseInt($scope.height * 1.77);
    $scope.$on('$viewContentLoaded', function () {
        // initialize core components
    });

    angular.element($window).bind('resize', function () {
        $scope.$evalAsync(($scope) => {
            $scope.height = parseInt($(window).height() - 330);
            $scope.width = parseInt($scope.height * 1.77);
        })

    });
    function create_UUID() {
        var dt = new Date().getTime();
        var uuid = 'user-xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g, function (c) {
            var r = (dt + Math.random() * 16) % 16 | 0;
            dt = Math.floor(dt / 16);
            return (c == 'x' ? r : (r & 0x3 | 0x8)).toString(16);
        });
        return uuid;
    }
    var Video = Twilio.Video;
    var room;
    var enableVideo = true; //default. Set to false for broadcasting apps
    var enableAudio = true; //default. Set to false for avoiding echoes in demos
    var numVideoTracks = 0;

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
        // video: { height: 720, frameRate: 24, width: 1280 },
        // audio: true
    };
    var canvas = document.getElementById('canvas');
    var context = canvas.getContext('2d');
    var record_stream = null;
    let mediaRecorder;
    let recordedBlobs;
    let interval_slide;
    $scope.recordStarted = false;


    $scope.muted = false;
    $scope.mic_control = function () {
        $scope.room.localParticipant.tracks.forEach(function (track) {
            if (track.kind == 'audio') {
                if (track.isEnabled) {
                    track.disable();
                    $scope.muted = true;
                } else {
                    track.enable();
                    $scope.muted = false;
                }
            }
        });
    }
    function connectToRoom(msg) {
        $scope.meeting_title = msg.roomName;
        console.log("Connecting to room " + msg.roomName + " with jwtToken: " + msg.jwtToken);
        Video.connect(msg.jwtToken, connectOptions).then((room) => {
            $scope.$evalAsync(($scope) => {
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
                        added: false,
                        muted: false,
                        names_shown: true
                    }],
                    identity: room.localParticipant.identity
                };
                room.localParticipant.tracks.forEach((track) => {
                    // if (track.kind == 'audio') return;




                    if (track.kind == 'audio') {
                        var _obj = {
                            type: 'audio',
                            track: track
                        };

                        obj.item[0].tracks.push(_obj);
                    } else if (track.kind == 'video') {
                        var _obj = {
                            type: 'video',
                            track: track
                        };
                        obj.item[0].tracks.push(_obj);
                    } else {

                    }


                });
                $scope.participants.push(obj);


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

                setTimeout(() => {
                    socket.emit('get_selected_track', { session_link: $scope.session_link });
                    socket.emit('get_layout', { session_link: $scope.session_link });
                }, 5000);

            })

        });
    }
    $scope.currentParticipant = null;
    function manageConnectedParticipant(participant) {

        console.log("Participant " + participant.identity + " connected");
        console.log(participant);

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
                                added: false,
                                muted: false,
                                names_shown: true
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
                                added: false,
                                muted: false,
                                names_shown: true
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
                                added: false,
                                muted: false,
                                names_shown: true
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
                                added: false,
                                muted: false,
                                names_shown: true
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

        participant.on('trackRemoved', detachTrack);

    }

    function manageDisconnectedParticipant(participant) {
        $scope.$evalAsync(($scope) => {
            console.log(participant);
            console.log($scope.participants);
            var _part = $scope.participants.find(x => x.identity == participant.identity);
            if (_part) {

                console.log(_part);

                for (var key in _part.item) {
                    $scope.remove_stream(_part.item[key].tracks, _part.item[key].type, participant.identity);
                }
                console.log($scope.participants.indexOf(_part));
                $scope.participants.splice($scope.participants.indexOf(_part), 1);
                $rootScope.handles.forEach((h) => { clearTimeout(h) });
                $rootScope.handles = [];
                context.clearRect(0, 0, 1920, 1080);
            }
        })
    }

    function manageDisconnected(room, error) {

    }

    function attachTrack(track) {

    }

    function detachTrack(track) {

    }

    function updateNumParticipants() {

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
        if ($scope.admin_name == '' || $scope.session_link == '') {
            return;
        }
        $scope.logined = true;
        $scope.session_link = $scope.session_link[0] + '-' + $scope.session_link[1] + $scope.session_link[2] + $scope.session_link[3] + '-' + $scope.session_link[4] + $scope.session_link[5] + $scope.session_link[6] + '-' + $scope.session_link[7] + $scope.session_link[8] + $scope.session_link[9];
        socket.emit('getAccessToken', {
            userName: $scope.admin_name,
            session_link: $scope.session_link
        });
        socket.emit('meeting_status', {});
    }
    $scope.meeting_status = 0;
    socket.on('meeting_status', (obj) => {
        if (obj.meeting_status) {
            $scope.$evalAsync(($scope) => {
                $scope.meeting_status = obj.meeting_status;
            })
        }
    })
    $scope.remove_screen_stream = function () {


        var g_track_name = '';
        var tracks = $scope.track_id_arr.filter(x => x.track_type == 'screen');
        console.log(tracks);
        for (var key in tracks) {
            g_track_name = tracks[key].track_name;
            var _track = $scope.track_id_arr.find(x => x.track_name == tracks[key].track_name);
            if (_track) {
                $scope.track_id_arr.splice($scope.track_id_arr.indexOf(_track), 1);
            }
        }

        for (var idx = 0; idx < $scope.participants.length; idx++) {
            for (var idx1 = 0; idx1 < $scope.participants[idx].item.length; idx1++) {
                if ($scope.participants[idx].item[idx1].type == 'screen') {
                    $scope.participants[idx].item[idx1].added = false;
                }
            }
        }

        context.clearRect(0, 0, 1920, 1080);


    }
    $scope.mute_participant_audio = function (identity) {
        socket.emit('mute', { identity: identity, session_link: $scope.session_link });
    }
    $scope.unmute_participant_audio = function (identity) {
        socket.emit('unmute', { identity: identity, session_link: $scope.session_link });
    }
    socket.on('mute', (obj) => {
        if (obj.session_link == $scope.session_link) {

            for (var idx = 0; idx < $scope.participants.length; idx++) {
                if ($scope.participants[idx].identity == obj.identity) {
                    for (var _idx = 0; _idx < $scope.participants[idx].item.length; _idx++) {
                        if ($scope.participants[idx].item[_idx].type == 'camera') {
                            $scope.participants[idx].item[_idx].muted = true;
                            break;
                        }
                    }
                }
            }
        }
    })
    socket.on('unmute', (obj) => {
        if (obj.session_link == $scope.session_link) {
            for (var idx = 0; idx < $scope.participants.length; idx++) {
                if ($scope.participants[idx].identity == obj.identity) {
                    for (var _idx = 0; _idx < $scope.participants[idx].item.length; _idx++) {
                        if ($scope.participants[idx].item[_idx].type == 'camera') {
                            $scope.participants[idx].item[_idx].muted = false;
                            break;
                        }
                    }
                }
            }
        }
    })
    $scope.add_to_stream = function (tracks, track_type, track_identity) {
        if (interval_slide) {
            clearInterval(interval_slide);
        }
        if (track_type == 'screen') {

            var _track = $scope.track_id_arr.find(x => x.track_name == tracks[0].track.name);
            if (!_track) {
                var _obj = {
                    track_name: tracks[0].track.name,
                    track_type: track_type,
                    track_identity: track_identity
                }
                $scope.track_id_arr.push(_obj);
            }
        } else {
            for (var key in tracks) {
                var _track = $scope.track_id_arr.find(x => x.track_name == tracks[key].track.name);
                if (!_track) {
                    var _obj = {
                        track_name: tracks[key].track.name,
                        track_type: track_type,
                        track_identity: track_identity
                    }
                    $scope.track_id_arr.push(_obj);
                }
            }

        }
        console.log($scope.track_id_arr);




        socket.emit('selected_track', { session_link: $scope.session_link, track_id_arr: $scope.track_id_arr });
    }
    function handleStop(event) {
        console.log('Recorder stopped: ', event);
        const superBuffer = new Blob(recordedBlobs, { type: 'video/webm' });
        var link = document.createElement('a');
        link.download = 'record.webm';
        link.href = URL.createObjectURL(superBuffer);
        link.click();
    }
    function handleDataAvailable(event) {
        if (event.data && event.data.size > 0) {
            recordedBlobs.push(event.data);
        }
    }
    $scope.stop_record_room = function () {

        isStoppedRecording = true;
        isRecordingStarted = false;
        mediaRecorder.stop();
        $scope.recordStarted = false;
        record_stream = null;
    }
    $scope.record_room = function () {
        record_stream = canvas.captureStream(30); // frames per second
        $scope.recordStarted = true;
        if ($scope.track_id_arr.length == 0) {
            interval_slide = setInterval(() => {
                var img = document.getElementById("slide");
                context.drawImage(img, 20, 20, 1880, 1040);
            }, 1000);
        }
        let options = { mimeType: 'video/webm;codecs=vp9', videoBitsPerSecond: 3000000 };
        recordedBlobs = [];
        try {
            mediaRecorder = new MediaRecorder(record_stream, options);
        } catch (e0) {
            console.log('Unable to create MediaRecorder with options Object: ', e0);
            try {
                options = { mimeType: 'video/webm,codecs=vp9' };
                mediaRecorder = new MediaRecorder(record_stream, options);
            } catch (e1) {
                console.log('Unable to create MediaRecorder with options Object: ', e1);
                try {
                    options = 'video/vp8'; // Chrome 47
                    mediaRecorder = new MediaRecorder(record_stream, options);
                } catch (e2) {
                    alert('MediaRecorder is not supported by this browser.\n\n' +
                        'Try Firefox 29 or later, or Chrome 47 or later, ' +
                        'with Enable experimental Web Platform features enabled from chrome://flags.');
                    console.error('Exception while creating MediaRecorder:', e2);
                    return;
                }
            }
        }
        console.log('Created MediaRecorder', mediaRecorder, 'with options', options);
        mediaRecorder.onstop = handleStop;
        mediaRecorder.ondataavailable = handleDataAvailable;
        mediaRecorder.start(1000); // collect 100ms of data
    }
    $scope.remove_stream = function (tracks, track_type, track_identity) {
        $scope.$evalAsync(($scope) => {
            if (track_type == 'screen') {
                var _track = $scope.track_id_arr.find(x => x.track_name == tracks[0].track.name);
                if (_track) {
                    $scope.track_id_arr.splice($scope.track_id_arr.indexOf(_track), 1);
                }
            } else {
                for (var key in tracks) {
                    var _track = $scope.track_id_arr.find(x => x.track_name == tracks[key].track.name);
                    if (_track) {
                        $scope.track_id_arr.splice($scope.track_id_arr.indexOf(_track), 1);
                    }
                }
            }
            socket.emit('selected_track', { session_link: $scope.session_link, track_id_arr: $scope.track_id_arr });
        });
    }
    $scope.show_name = function (tracks, identity) {
        $scope.$evalAsync(($scope) => {
            let track_infos = angular.copy($scope.selected_track_infos);
            for (var key in tracks) {
                for (var idx = 0; idx < track_infos.length; idx++) {
                    if (track_infos[idx].track_name == tracks[key].track.name) {
                        track_infos[idx].label = 1;
                    }
                }
            }
            $scope.selected_track_infos = track_infos;
            socket.emit('update_track_info', { session_link: $scope.session_link, selected_track_infos: $scope.selected_track_infos });
        });
    }
    $scope.unshow_name = function (tracks, identity) {
        $scope.$evalAsync(($scope) => {
            let track_infos = angular.copy($scope.selected_track_infos);
            for (var key in tracks) {
                for (var idx = 0; idx < track_infos.length; idx++) {
                    if (track_infos[idx].track_name == tracks[key].track.name) {
                        track_infos[idx].label = 0;
                    }
                }
            }
            $scope.selected_track_infos = track_infos;
            socket.emit('update_track_info', { session_link: $scope.session_link, selected_track_infos: $scope.selected_track_infos });
        });
    }
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
    $scope.layout = 0;
    $scope.set_layout = function (_layout) {
        $scope.$evalAsync(($scope) => {

            console.log('---------**************-----------');
            $scope.layout = _layout;
            socket.emit('set_layout', { session_link: $scope.session_link, layout: _layout });
        });
    }
    socket.on('get_selected_track', (obj) => {
        $scope.$evalAsync(($scope) => {
            if (obj.session_link == $scope.session_link) {
                $scope.track_id_arr = obj.tracks;
                console.log($scope.track_id_arr);
                console.log($scope.participants);

                for (var idx = 0; idx < $scope.track_id_arr.length; idx++) {
                    for (var _idx = 0; _idx < $scope.participants.length; _idx++) {
                        for (var item_id = 0; item_id < $scope.participants[_idx].item.length; item_id++) {
                            for (var track_id = 0; track_id < $scope.participants[_idx].item[item_id].tracks.length; track_id++) {
                                if ($scope.participants[_idx].item[item_id].tracks[track_id].track.name == $scope.track_id_arr[idx].track_name) {
                                    $scope.participants[_idx].item[item_id].added = true;
                                    break;
                                }
                            }

                        }
                    }
                }
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
    $scope.chat_contents = [];

    $scope.press_entered = function () {
        if ($scope.input_chat == '') {
            return;
        }
        var _obj = {
            message_type: 1,
            text: $scope.input_chat,
            name: $scope.admin_name
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
        socket.emit('chat_text', { name: $scope.admin_name, text: $scope.input_chat, session_link: $scope.session_link });
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
