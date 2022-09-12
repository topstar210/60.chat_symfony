const http = require('http'),
    faye = require('faye');
const request = require('request');

const server = http.createServer(),
    bayeux = new faye.NodeAdapter({mount: '/faye', timeout: 45});

bayeux.attach(server);
server.listen(3000);

setInterval(function(){
    request.post(
        'http://127.0.0.1/api/file/check',
        { json: { 'test': 'value' } },
        function (error, response, body) {
            if (!error && response.statusCode == 200) {
                console.log(body);
            }
            if(error){
                console.log(error);
            }
        }
    );
}, 60 * 60 * 1000);