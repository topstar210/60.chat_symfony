/* Setup Home page controller */
angular.module('BotApp').controller('RecordController', function ($rootScope, $scope, $state, $http, $window, $cookies, $timeout, API_URL, $localStorage, socket) {
    $scope.track_id_arr = [];
    $scope.layout = 0;

    $scope.session_link = $state.params.room_id;
    // var canvas = document.getElementById('canvas');
    // var context = canvas.getContext('2d');

    // var interval_slide = setInterval(() => {
    //     var img = document.getElementById("slide");
    //     context.drawImage(img, 10, 10, 1900, 1060);
    // }, 1000);
    $scope.height = 500;
    $scope.width = parseInt($scope.height * 1.77);
    $scope.$on('$viewContentLoaded', function () {
        // initialize core components
    });

    angular.element($window).bind('resize', function () {
        $scope.$evalAsync(($scope) => {
            $scope.height = 500;
            $scope.width = parseInt($scope.height * 1.77);
        })

    });

    function binaryToDataURL(data) {
        const arrayBuffer = new ArrayBuffer(1920 * 1080 * 4);

        const pixels = new Uint8ClampedArray(arrayBuffer);
        for (let y = 0; y < 1080; y++) {
            for (let x = 0; x < 1920; x++) {
                const i = (y * 1920 + x) * 4;
                pixels[i] = x;   // red
                pixels[i + 1] = y;   // green
                pixels[i + 2] = 0;   // blue
                pixels[i + 3] = 255; // alpha
            }
        }
        const imageData = new ImageData(pixels, 1920, 1080);

        return imageData;
    }

    setTimeout(() => {
        socket.emit('emulate', { session_link: $scope.session_link });
    }, 2000);

    socket.on('send_emulate', (obj) => {
        $scope.$evalAsync(($scope)=>{
            $scope.track_id_arr = obj.item.track_id_arr;
            $scope.layout = obj.item.layout;
        })
    })
});
