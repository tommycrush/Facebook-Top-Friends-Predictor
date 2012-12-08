<?php
class TopFriends {
  private $facebook;
  private $user_id;

  private $friend_points = array(
    "posts"=> array(),
    "likes"=> array(),
    "comments"=> array(),
    "tags" => array(),
    "photos" => array(),
    "messages" => array(),
    "i_liked" => array()
  );


  private $friend_weighted_points = array();



  public function __construct($facebook){
    $this->facebook = $facebook;
    $user_profile = $facebook->api('/me');
    $this->user_id = $user_profile["id"];
  }


  public function getData(){

    //query to get timeline!
    $query = "SELECT actor_id ,message, type, target_id,likes, comments,post_id FROM stream WHERE source_id = me() LIMIT 100";
    $data = $this->facebook->api('/fql?q='.urlencode($query));


    foreach($data["data"] as $post_num => $post){
      if($post["actor_id"] == $this->user_id){
        //this is our own post
        $this->selfPost($post);
      }else{
        //a friend posted this on my wall
        $this->friendsPost($post);
      }
    }

    //now lets get who's tagging us.
    $data = $this->facebook->api('/me/tagged?limit=25');
    foreach($data["data"] as $tagged){
      $this->addPoint("tags",$tagged["from"]["id"]);
    }


    //now lets get who's tagging us in pictures
    $data = $this->facebook->api('/me/photos?limit=50&fields=from');
    foreach($data["data"] as $tagged){
      $this->addPoint("photos",$tagged["from"]["id"]);
    }

    //lets see who we're messaging
    $query = "SELECT recipients FROM thread WHERE folder_id = 0";
    $data = $this->facebook->api('/fql?q='.urlencode($query));
    foreach($data["data"] as $thread){
      foreach($thread["recipients"] as $friend_id){
        $this->addPoint("messages", $friend_id);
      }
    }


    // what objects are we liking
    $query = "SELECT object_id FROM like WHERE user_id=me() LIMIT 100";
    $data = $this->facebook->api('/fql?q='.urlencode($query));
    $get_ids = "";
    foreach($data["data"] as $like){
      if(!empty($get_ids)){
        $get_ids .= ",";
      }
      $get_ids .= $like["object_id"];
    }

    
    //lets get data of who owns those objects
    $data = $this->facebook->api('/?fields=from&ids='.$get_ids);
    foreach($data as $object_id => $object){
      $this->addPoint("i_liked",$object["from"]["id"]);
    }

  }


  public function rank(){

    //adjust weights of various actions here:
    $post_weight = 10;
    $tagged_weight = 10;
    $i_liked_weight = 7;
    $comment_weight = 5;
    $messages_weight = 3;
    $like_weight = 3;
    $photos_weight = 3;


    foreach($this->friend_points["posts"] as $friend_id => $points){
        $this->addRankedPoints($friend_id, $points * $post_weight);
    }

    foreach($this->friend_points["comments"] as $friend_id => $points){
        $this->addRankedPoints($friend_id, $points * $comment_weight);
    }

    foreach($this->friend_points["likes"] as $friend_id => $points){
        $this->addRankedPoints($friend_id, $points * $like_weight);
    }

    foreach($this->friend_points["tags"] as $friend_id => $points){
        $this->addRankedPoints($friend_id, $points * $tagged_weight);
    }

    foreach($this->friend_points["i_liked"] as $friend_id => $points){
        $this->addRankedPoints($friend_id, $points * $i_liked_weight);
    }


    foreach($this->friend_points["photos"] as $friend_id => $points){
        //cap max instances at 5
        if($points > 5){
          $points = 5;
        }
        $this->addRankedPoints($friend_id, $points * $photos_weight);
    }

    foreach($this->friend_points["messages"] as $friend_id => $points){
        //cap max instances at 5
        if($points > 5){
          $points = 5;
        }
        $this->addRankedPoints($friend_id, $points * $messages_weight);
    }

    arsort($this->friend_weighted_points);

    return;
  }//end rank


  public function printResults(){


    $last_points = -1;
    $place = 0;

    $ids_to_get = "".$this->user_id;

    foreach($this->friend_weighted_points as $friend_id => $points){
      if($place < 20 && $friend_id != $this->user_id){
        if($points != $last_points){
          $place++;
          $last_points = $points;
        }//end if not points same

        $ids_to_get .= ",".$friend_id;
      }//end lace < 15
    
    }//end foreach 

    $friends_names = $this->facebook->api('/?ids='.$ids_to_get);

    $last_points = -1;
    $place = 0;

    foreach($this->friend_weighted_points as $friend_id => $points){
      if($place < 20 && $friend_id != $this->user_id){
        if($points != $last_points){
          $place++;
          echo "<br/><b>#".$place.":</b><br/>";
          $last_points = $points;
        }//end if not points same

        echo "<a href='http://facebook.com/profile.php?id=".$friend_id."' target='_blank'>".$friends_names[$friend_id]["name"]."</a><br/>";
      }//end lace < 15
    
    }//end foreach 

  }//end function


  public function addRankedPoints($friend_id, $points){
    if(is_numeric($this->friend_weighted_points[$friend_id])){
      $this->friend_weighted_points[$friend_id] += $points;
    }else{
      $this->friend_weighted_points[$friend_id] = $points;
    }
  }//addRankedPoints


  public function friendsPost(&$page){
    //lets see who posted it!
    $this->addPoint("posts",$page["actor_id"]);
  }

  public function selfPost(&$post){
    //lets see who liked and commented on it

    if(is_array($post["likes"]["friends"])){
      if($post["likes"]["count"] > 5){
        $this->getLikes($post["post_id"]);
      }else{
        foreach($post["likes"]["friends"] as $friend_id){
          $this->addPoint("likes", $friend_id);
        }
      }//end less than 4
    }

    if(is_array($post["comments"]["comment_list"])){
      if($post["comments"]["count"] > 4){
        $this->getComments($post["post_id"]);
      }else{
        foreach($post["comments"]["comment_list"] as $comment){
          $friend_id = $comment["fromid"];
          $this->addPoint("comments", $friend_id);
        }
      }//end else less than 3
    }

  }

  public function getComments($post_id){
    $data = $this->facebook->api('/'.$post_id.'/comments?limit=500');
    foreach($data["data"] as $comment){
      $this->addPoint("comments",$comment["from"]["id"]);
    }
  }

  public function getLikes($post_id){
    $data = $this->facebook->api('/'.$post_id.'/likes?limit=500');
    foreach($data["data"] as $like){
      $this->addPoint("likes",$like["id"]);
    }
  }


  private function addPoint($action_type, $friend_id){
    if ( is_numeric($this->friend_points[$action_type][$friend_id]) ){
      $this->friend_points[$action_type][$friend_id]++;
    } else {
      $this->friend_points[$action_type][$friend_id] = 1;
    }
  }


}//end Class

?>