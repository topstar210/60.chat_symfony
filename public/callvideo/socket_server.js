'use strict';
var fs = require('fs');
var https = require('https');
var express = require('express');
var dateFormat = require('dateformat');

var app = express();

var options = {
  key: fs.readFileSync('./server.key'),
  cert: fs.readFileSync('./server.crt')
  
};
var serverPort = 8443;
var server = https.createServer(options, app);

var io = require('socket.io')(server, {
	cors: {
			origin: "https://chatapp.mobi"
	}
});


app.get('/', function(req, res) {
    
    console.log('----------------');
  res.sendFile(__dirname + '/public/index.html');
});

var online_users = [];
var g_sockets = [];
var online_rooms = [];

var channels = {};
var sockets = {};

function create_UUID(){
    var dt = new Date().getTime();
    var uuid = 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g, function(c) {
        var r = (dt + Math.random()*16)%16 | 0;
        dt = Math.floor(dt/16);
        return (c=='x' ? r :(r&0x3|0x8)).toString(16);
    });
    return uuid;
}

function makeid(length) {
   var result           = '';
   var characters       = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789';
   var charactersLength = characters.length;
   for ( var i = 0; i < length; i++ ) {
      result += characters.charAt(Math.floor(Math.random() * charactersLength));
   }
   return result;
}
io.on('connection', function(socket) {
  
    socket.channels = {};
	sockets[socket.id] = socket;
	socket.on('remove_room', function (config) {
		var room_id = config.room_id;
		var online_room = online_rooms.find(x =>x.room_id == room_id);
		if(online_room){
			var index = online_rooms.indexOf(online_room);
			online_rooms.splice(index, 1);
		}
	});
	socket.on('room-join', function (config) {
		var room_id = config.room_id;
		var online_room = online_rooms.find(x =>x.room_id == room_id);
		if(online_room){
			var user = online_users.find(x => x.user_id == config.user_id);
			if(user){
				online_room.client = user;
				online_room.status = 1;
				socket.broadcast.emit('room-join-callback', {'room' : online_room});
				socket.emit('room-join-callback', {'room' : online_room});
			}
		}
	});
	socket.on('create-room', function (config) {
		var user = online_users.find(x => x.user_id == config.user_id);
		if(user){
			var gender = user.gender;
			var _data = {
				room_id : create_UUID(),
				attend : 0,
				status : 0,
				gender : gender,
				host : user,
				client : null
			}
			online_rooms.push(_data);
			
			socket.emit('create-room-callback', {'result' : 0, 'room_id' : _data.room_id});
		} else {
			socket.emit('create-room-callback', {'result' : 1, 'room_id' : 0});
		}
	});
	socket.on('check-available-room', function (config) {
		console.log(online_rooms);
		var user = online_users.find(x => x.user_id == config.user_id);
		if(user){
			
			if(online_rooms.length == 0){
				socket.emit('available-room-callback', {'result' : 1});
				return;
			}
			var online_room = online_rooms.find( x => x.status == 0 );
			if(!online_room){
				socket.emit('available-room-callback', {'result' : 1});
				return;
			}
			
			var gender = user.gender;
			var rooms = online_rooms.filter(function(room){
				return room.gender !=gender && room.status == 0;
			});
			if(rooms.length > 0){
				socket.emit('available-room-callback', {'result' : 0, 'room_id' : rooms[0].room_id});
			} else {
				socket.emit('available-room-callback', {'result' : 1});
				return;
			}
		}
		
		
	});
	
	socket.on('user_register', function (config) {
		console.log(config);
		
		var user = online_users.find(x => x.email == config.email);
		if(user){
			socket.emit('user_register_callback', {'result' : 1});
			return;
		}
		
		var _data = {
			user_id: makeid(10),
			email : config.email,
			username : config.username,
			age : config.age,
			gender : config.gender
		}
		online_users.push(_data);
		socket.emit('user_register_callback', {'result' : 0, 'user_id' : _data.user_id});
	});
	socket.on('user_authentication', function (config) {
		var user = online_users.find(x => x.email == config.email)
		if(user){
			socket.emit('user_authentication_callback', {'result' : 0, 'user_id' : user.user_id});
		} else {
			socket.emit('user_authentication_callback', {'result' : 1, 'user_id' : 0});
		}
	});
    socket.on('join', function (config) {
		console.log('-----------join-----------');
		//console.log("[" + socket.id + "] join ", config);
		var channel = config.channel;
		var userdata = config.userdata;

		if (channel in socket.channels) {
			console.log("[" + socket.id + "] ERROR: already joined ", channel);
			return;
		}

		if (!(channel in channels)) {
			channels[channel] = {};
		}

		for (var id in channels[channel]) {
			channels[channel][id].socket.emit('addPeer', { 'peer_id': socket.id, 'should_create_offer': false, 'userdata': userdata });
			socket.emit('addPeer', { 'peer_id': id, 'should_create_offer': true, 'userdata': channels[channel][id].userdata });
		}

		var _data = {
			socket: socket,
			userdata: userdata
		}
		channels[channel][socket.id] = _data;
		socket.channels[channel] = channel;
	});
	function part(channel) {
		//console.log("[" + socket.id + "] part ");

		if (!(channel in socket.channels)) {
			//console.log("[" + socket.id + "] ERROR: not in ", channel);
			return;
		}
        
        var user_data = channels[channel][socket.id].userdata;

		delete socket.channels[channel];
		delete channels[channel][socket.id];
		
		

		for (var id in channels[channel]) {
			channels[channel][id].socket.emit('removePeer', { 'peer_id': socket.id, 'userdata' : user_data });
			socket.emit('removePeer', { 'peer_id': id, 'userdata' : user_data });
		}
	}
	socket.on('part', part);

	socket.on('relayICECandidate', function (config) {
		var peer_id = config.peer_id;
		var ice_candidate = config.ice_candidate;
		//console.log("[" + socket.id + "] relaying ICE candidate to [" + peer_id + "] ", ice_candidate);

		if (peer_id in sockets) {
			sockets[peer_id].emit('iceCandidate', { 'peer_id': socket.id, 'ice_candidate': ice_candidate });
		}
	});

	socket.on('relaySessionDescription', function (config) {
		var peer_id = config.peer_id;
		var session_description = config.session_description;
		//console.log("[" + socket.id + "] relaying session description to [" + peer_id + "] ", session_description);

		if (peer_id in sockets) {
			sockets[peer_id].emit('sessionDescription', { 'peer_id': socket.id, 'session_description': session_description });
		}
	});
	socket.on('joined', (obj) => {
		io.sockets.emit("user-joined", socket.id, io.engine.clientsCount, online_users);
	})
	socket.on('signal', (toId, message) => {
		io.to(toId).emit('signal', socket.id, message);
	});
	socket.on('init', (obj) => {


	})
	socket.on('chk_online_response', (obj) => {

	});
	socket.on('disconnect', () => {
		for (var channel in socket.channels) {
			part(channel);
		}
		//console.log("[" + socket.id + "] disconnected");
		delete sockets[socket.id];



	})
	g_sockets.push(socket);
	
	
    socket.on('login_user', function(data) {
		
	    // if (users.indexOf(data) == -1){
	    // 	users.push(data);
        //     console.log('log in users = ' + users);
	    // }

		// socket.emit('own_all_user', users);
		// socket.broadcast.emit('all_user', users);
    })
	socket.on('logout_user', function(data) {
        // Decrease the socket count on a disconnect, emit
	    // users.splice(users.indexOf(data), 1);
	    // console.log('log out users = ' + users);
    	// socket.emit('own_all_user', users);
		// socket.broadcast.emit('all_user', users);
    })
	socket.on('new_message', function(obj) {
       
	})
	


	socket.on('new_video_request', function(obj){
		
	})

	socket.on('video_accept_request', function(obj){

	})
	socket.on('typing_notification', (obj) => {
		socket.broadcast.emit('typing_notification', obj);
	});
  	socket.on('message', function(obj){
  	    socket.broadcast.emit('message', obj);
  	})
  	socket.on('endCall', function(obj){
  	    socket.broadcast.emit('endCall', obj);
  	})
  	socket.on('interview_box_add', function(obj){
  	    
  	    console.log(obj);
  	    socket.broadcast.emit('interview_box_add', obj);
  	})
  	
  	socket.on('end_call_from_seeker', function(obj){
  	    socket.broadcast.emit('end_call_from_seeker', obj);
  	});
  	
  
});

server.listen(serverPort, function() {
  console.log('server up and running at %s port', serverPort);
});