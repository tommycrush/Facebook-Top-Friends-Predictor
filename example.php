<?php
require 'src/facebook.php';

// Create our Application instance (replace this with your appId and secret).
$facebook = new Facebook(array(
  'appId'  => 'YOUR_APP_ID',
  'secret' => 'YOUR_APP_SECRET',
));


// Get User ID
$user = $facebook->getUser();

//Check for logged in user
if ($user) {
  try {
  	//lets find the top friends!
  	require 'TopFriends.class.php';

    $TopFriends = new TopFriends($facebook);

    $TopFriends->getData();
    $TopFriends->rank();
    $TopFriends->printResults();

  } catch (FacebookApiException $e) {
    error_log($e);
    $user = null;
  }

}else{
	//not logged in

	$loginUrl = $facebook->getLoginUrl(array('scope' => 'user_groups, user_location,user_hometown,user_education_history,user_website,user_work_history,read_insights,read_stream,read_mailbox,user_photos,user_checkins,user_groups,read_stream'));


	echo "Click <a href='".$loginUrl."'>here</a> to login";
}

?>