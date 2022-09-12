<!doctype html>
<html>

<head>
<link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.3.1/css/bootstrap.min.css" integrity="sha384-ggOyR0iXCbMQv3Xipma34MD+dH/1fQ784/j6cY/iJTQUOhcWr7x9JvoRxT2MZw1T" crossorigin="anonymous">
<style>
    #videos-container video{
        width:150px;
        height:120px;
        object-fit:fill;
        position:relative;
        border-radius:7px;
        float:right;
    }
    #videos-second-container video{
        width:100%;
        height:100%;
        object-fit:fill;
        position:relative;
        float:right;
    }
    #videos-remote-container video{
        width:150px;
        height:120px;
        object-fit:fill;
        position:relative;
        border-radius:7px;
        float:right;
    }
</style>
</head>
<body style="background:#262626;">
	
	
		
		<div class="container" style="margin-top:50px;">
			<div class="offset-md-2 col-md-8" style="height: 100vh; background: #262626;padding:20px;">
				<div class="row">
					<div class="col-md-12">
						<div class="row">
							<div class="col-lg-12 mb-3">
								<div class="d-flex align-items-center">
									<span style="border-radius:100%;background:#37b954;display:block;width:20px;height:20px;">
										&nbsp;
									</span><span style="margin-left:5px;color:#fff;"></span>
								</div>
							</div>
							<div class="col-lg-12">
								<div
                                    style="background:#37b954;background-size:cover;height:392px;width:100%;position:relative;display:flex;flex-direction:column;">
                                
									<div id="videos-second-container" class="my_camera"
				                			style="background:#37b954;width:100%;height:392px;">
				                			
				                	</div>
				                	<div id="videos-container" class="my_camera"
	                                    style="background:#f00;border-radius:7px;position:absolute;right:10px;bottom:10px;">
	                                    <!-- <video id="my-video" autoplay loop playsinline
	                                        style="width:150px;height:120px;object-fit:fill;position:relative;border-radius:7px;float:right;"></video> -->

	                                </div>
	                                <div class="my_camera"
	                                    style="border-radius:7px;position:absolute;left:10px;bottom:10px;">
											<div class="d-flex align-items-center justify-content-center">
												<span style="border-radius:100%;width: 50px;height: 50px;background:#fff;justify-content: center;align-items: center;display: flex;font-weight: 600;">
													0:<span class="span_counter_label">59</span>
												</span>
											</div>
	                                </div>
                                </div>
							</div>
						</div>
					</div>
				</div>
				<div class="row">
					<div>
						
					</div>
				</div>
			</div>
		</div>

	<form method="post" id="private_room_form" action="/php_video_chat/room.php">
		<input type="hidden" id="private_room_id" name="private_room_id" />
		<input type="hidden" id="private_room_host" name="private_room_host" />
	</form>
	<script src="https://cdnjs.cloudflare.com/ajax/libs/socket.io/2.3.0/socket.io.js"></script>
	<script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.5.1/jquery.js"></script>
	<script src="RTC.js"></script>
	<script>
			var socket = io.connect('https://chatapp.mobi:8443');
			function create_UUID(){
			    var dt = new Date().getTime();
			    var uuid = 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g, function(c) {
			        var r = (dt + Math.random()*16)%16 | 0;
			        dt = Math.floor(dt/16);
			        return (c=='x' ? r :(r&0x3|0x8)).toString(16);
			    });
			    return uuid;
			}
			var DEFAULT_CHANNEL = 'chatapp_<?php echo isset($_GET["room"])?$_GET["room"]:'';?>';
    		var remote_joiners = 0;
    		var connection = new RTCMultiConnection();
		    connection.socketURL = 'https://chatapp.mobi:2087/';
		    connection.enableFileSharing = false; // by default, it is "false".
		    connection.userid = create_UUID();
		    var my_id = create_UUID();
		    connection.session = {
		        audio: true,
		        video: true,
		    };
		    connection.sdpConstraints.mandatory = {
		        OfferToReceiveAudio: true,
		        OfferToReceiveVideo: true
		    };
		    connection.onstream = function (event) {
		        console.log(event);
		        console.log(event.type);
		        // document.getElementById('video_container').appendChild(event.mediaElement);

		        if (event.type == 'local') {
		            myLocalVideo = event.mediaElement;
		            document.getElementById('videos-container').appendChild(event.mediaElement);
		        }

		        if (event.type == 'remote' && remote_joiners == 0) {
		            document.getElementById('videos-second-container').appendChild(event.mediaElement);
		            start_countdown();
		            remote_joiners++;
		        }
		    };
		    connection.onstreamended = function (event) {
		        console.log('onstreamended');
		        console.log(event);
		        clearInterval(_interval);
		        counter = 59;
		        $('.span_counter_label').html(counter);
		    }

    		connection.openOrJoin(DEFAULT_CHANNEL);
    		
	    	var counter = 59;
	    	var _interval = null;
	    	function start_countdown(){
		    	_interval = setInterval(function(){
				  console.log(counter);
				  counter--
				  $('.span_counter_label').html(counter);
				  if (counter === 0) {
				    alert('time is up');
				  	connection.socket.disconnect();
				    clearInterval(_interval);
				    location.reload();
				  }
				}, 1000);
	    	}
			 
	</script>
</body>
			
</html>