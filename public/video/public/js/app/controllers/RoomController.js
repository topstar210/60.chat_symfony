/* Setup Home page controller */
angular.module('BotApp').controller('RoomController', function ($rootScope, $scope, $state, $http, $window, $cookies, $timeout, API_URL, $localStorage, socket, ngDialog) {
    socket.emit('get_all_room', {});

    $scope.rooms = [];
    socket.on('get_all_room', (obj) => {
        $scope.rooms = obj.rooms;
    });
    socket.on('room_error', (obj) => {
        alert('room create error');
    })

    $scope.create_room = function () {
        var newClassDialog = ngDialog.open({
            template: 'js/app/dialogs/create_room.html',
            closeByDocument: false,
            closeByEscape: false,
            controller: 'CreateRoomCtrl',
            width: '700px',
            background: '#2b2b2b',
            scope: $scope
        });
        newClassDialog.closePromise.then(function (data) {
            if (data && data.value && data.value.sent) {

                console.log(data.value.session_link);
                console.log(data.value.meeting_title);

                socket.emit('create_room', { 
	                session_title: data.value.session_title, 
	                session_link: data.value.session_link, 
	                session_password : data.value.session_password 
                });
                setTimeout(() => {
                    socket.emit('get_all_room', {});
                }, 2000);

            }
        });
    }
}).controller('CreateRoomCtrl', ($scope, ngDialog) => {
    function create_link() {
        var dt = new Date().getTime();
        var uuid = 'xxxxxxxxxx'.replace(/[xy]/g, function (c) {
            var r = (dt + Math.random() * 16) % 16 | 0;
            dt = Math.floor(dt / 16);
            return (c == 'x' ? r : (r & 0x3 | 0x8)).toString(16);
        });
        return uuid;
    }
    var id = ngDialog.getOpenDialogs()[0];
    $scope.session_link = create_link();

    $scope.sent = false;
    $scope.create = () => {
        $scope.sent = true;
        ngDialog.close(id, {
            sent: $scope.sent,
            session_link: $scope.session_link,
            session_title: $scope.session_title,
            session_password : $scope.session_password
        });
    }
})
