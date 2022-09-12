'use strict';
var fs = require('fs');
var express = require('express');
var http = require('http');
var https = require('https');
var app = express();
var serverPort = 3000;
var bodyParser = require('body-parser');
var path = require('path');
app.use(express.static('public'))
app.use(express.static('files'))
app.use('/static', express.static(path.join(__dirname, 'public')))
app.use(function (req, res, next) {
  res.header("Access-Control-Allow-Origin", "*"); // update to match the domain you will make the request from
  res.header("Access-Control-Allow-Headers", "Origin, X-Requested-With, Content-Type, Accept");
  if (req.method === 'OPTIONS') {
    res.header('Access-Control-Allow-Headers', 'Origin, X-Requested-With, Content-Type, Accept, Authorization');
    res.header('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, PATCH');
    return res.status(200).json({});
  };
  next();
});
app.use(bodyParser.json());
var options = {
	key: fs.readFileSync('./server.key'),
	cert: fs.readFileSync('./server.crt')
};
var server = https.createServer(options, app);
app.get('/', function (req, res) {
  res.sendFile(__dirname + '/public/index.html');
});
app.post('/create-code', function (req, res) {
  res.json({ status: 200 });
});
server.listen(serverPort, function () {
  console.log('server up and running at %s port', serverPort);
});