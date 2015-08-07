<?php


	// YOUR CERTIFICATE	
	$certificate = 'ck.pem';
	$certificate_password = ''; 

	// APPLE SERVER
	$APPLE_SERVER = 'ssl://gateway.sandbox.push.apple.com:2195';  	// The app is not in Appstore
	$APPLE_SERVER_APPSTORE = 'ssl://gateway.push.apple.com:2195';   // The app is in Appstore
	
	////////////////////////////////////////// DataBase Connection ////////////////////////////////////////////////////////////

	// YOUR MYSQL DATABASE CONNECTION
	$hostname = 'localhost';
	$username = '';
	$password = '';

	//  SETUP THIS Table to Your DataBase:
	/*
	CREATE TABLE  `apresschapter`.`apressdevices` (
		`device_token` VARCHAR( 64 ) NOT NULL ,
		`username` VARCHAR( 100 ) NOT NULL
		)
	*/

	$database = 'test';
	$table    = 'apressdevices';
		
	$db_link = mysql_connect($hostname, $username, $password);

	mysql_select_db( $database ) or die('ConnectToMySQL: Could not select database: ' . $database );

	$result  = ini_set ( 'mysql.connect_timeout' , '60' );
	

	////////////////////////////////////////////How to use this Service.php ///////////////////////////////////////////////////////
	if( ! isset( $_GET['token'] ) ) {

		$q = "SELECT DISTINCT(device_token),username FROM $database.$table";

		$result = mysql_query($q);
		$count = mysql_num_rows($result);
		if( $count > 0 ) {
			$rec = mysql_fetch_assoc($result);
			$example_token = $rec['device_token']; // the first device registered, presumably the developer's
		}
	
		$infotext = <<<INFOTEXT
			<font face="monospace">
			<blockquote>
			<br/>
			Resgister DeviceID and UserName to Server:<br/>
		    --> http://localhost/PushTesting/service.php?token=DEVICE_TOKEN&cmd=reg&name=USERNAME<br/><br/>

			Send message to DeviceID :<br/>
			--> http://localhost/PushTesting/service.php?token=DEVICE_TOKEN&cmd=msg&msg=YOURMESSAGE<br/><br/><br/><br/>
		
			///////////////////////////////////////////////<br/>
			////   You have $count devices in your database.   /////<br/>
			///////////////////////////////////////////////<br/>
			</blockquote>
			</font>			
INFOTEXT;
		print( $infotext );

	} else {

	////////////////////////////////////////////Processing of Resgister and Send ////////////////////////////////////////////////////
		if( isset( $_GET['cmd'] ) && ( $_GET['cmd'] == 'reg' || $_GET['cmd'] == 'msg' ) ) {

			////============= For Resgister Message to Server
			if( $_GET['cmd'] == 'reg' ) {
	
				if( isset($_GET['name']) ) {
					
					$token = mysql_escape_string($_GET['token']);
					$name  = mysql_escape_string($_GET['name']);
					
						$q = "INSERT INTO $database.$table (`device_token`,`username`) VALUES ('$token','$name')";
						
						$result = mysql_query($q);
						
						if( mysql_affected_rows() > 0 ) {
							print( "success registering" );
						} else {
							print( "error while registering man" );
						}
				} else {
					echo "Please input your User_name";
					
				}

			////============= For Send Message to Device
			} else if( $_GET['cmd'] == 'msg' ) {
				
				if( isset($_GET['msg']) ) {

					$q = "SELECT DISTINCT(device_token),username FROM $database.$table";
					
					$result           = mysql_query($q);
					$devices          = array();
					$validated_sender = FALSE;
					$sender_name      = 'Unknown';

					while( $rec = mysql_fetch_assoc($result) ) {
						
						if( $_GET['token'] == $rec['device_token'] ) {
							$validated_sender = TRUE;
							$sender_name      = $rec['username'];
						}
						print( "UserName + DeviceID : " . $rec['username'] . '  +  ' . $rec['device_token'] . "<br/>" );
						
						// add the device list to be sent to here
						array_push($devices,$rec['device_token']);
						
					}
					
					if( $validated_sender ) {
						// send them in one batch here
						// check the name here? - future development: is the submitted name the same as the registered name?
						if( isset( $_GET['name'] ) && strlen($_GET['name']) ) {
							$sender_name = $_GET['name'];
						}
						$alert = $sender_name . ' says ' . $_GET['msg'];
						$sound = 'sub.caf';
						$badge = '1';
						$custom = $sender_name;
						
						send_apns_to_devices($devices,$alert,$sound,$badge,$custom);
						
					} else {
						// we don't know the sender token
					}			
				} else {
					// message command requires a message
				
				}
			} else {
				// unknown command - shouldn't get here, but for future expansion
			}
		} else {
			// invalid command, either command not present, or not valid command
		}
	}

	////////////////////////////////////////////Request to APNs Server  /////////////////////////////////////////////////////////
	function send_apns_to_devices($device_list,$alert,$sound,$badge,$custom){
		
		global $APPLE_SERVER;
		global $certificate;
		global $certificate_password;
		
		// The actual notification payload
		$notification = array();
		if( strlen($alert) > 0 ) {
			$notification['alert'] = $alert;
		}
		
		if( strlen($sound) > 0 ) {
			$notification['sound'] = $sound;
		}
		
		if( is_numeric($badge) ) {
			$notification['badge'] = $badge;
		}
				
		$body['aps'] = $notification;

		// presumes $custom is string only
		if( strlen($custom) != 0 ) {
			$body['custom'] = $custom;
		}

		// CONNECT		
		$ctx = stream_context_create();

		stream_context_set_option($ctx, 'ssl', 'local_cert', $certificate );
		stream_context_set_option($ctx, 'ssl', 'passphrase', $certificate_password );

		$fp = stream_socket_client( $APPLE_SERVER, $err, $errstr, 15, STREAM_CLIENT_CONNECT, $ctx);
		
		if (!$fp) {
			print "Failed to connect $err $errstr\n";
			return;
		}
		// SEND
		foreach( $device_list as $device ) {
			$payload = json_encode($body);
			// format the message 
			$msg = chr(0) . chr(0) . chr(32) . pack('H*', $device) . chr(0) . chr(strlen($payload)) . $payload;
			
			fwrite($fp, $msg);
		}
		
		// CLOSE CONNECTION
		fclose($fp);
		
	}
?>