<?php
/**
 * Class used to retrieve, create or update a Venue.
 */
class EO_Venue{

	//DB Fields
	var $id = '';
	var $slug = '';
	var $name = '';
	var $address = '';
	var $postcode = '';
	var $country = '';
	var $latitude = '';
	var $longitude = '';
	var $description = '';
	var $owner = '';
	var $isfound = false;

	//Other Vars
	static public $fields = array( 
		'venue_id' => array('name'=>'id','type'=>'intval'), 
		'venue_slug' => array('name'=>'slug','type'=>'esc_attr'), 
		'venue_name' => array('name'=>'name','type'=>'esc_attr'), 
		'venue_address' => array('name'=>'address','type'=>'esc_attr'),
		'venue_postal' => array('name'=>'postcode','type'=>'esc_attr'),
		'venue_country' => array('name'=>'country','type'=>'esc_attr'),
		'venue_lat' =>  array('name'=>'latitude','type'=>'floatval'),
		'venue_lng' => array('name'=>'longitude','type'=>'floatval'),
		'venue_description' => array('name'=>'description','type'=>'stripslashes'),
	);

	static $defaults = array(
		'venue_id'=>0,
		'venue_name'=>'',
		'venue_slug'=>'',
		'venue_address' => '',
		'venue_postal' => '',
		'venue_country' => '',
		'venue_lng' => 0,
		'venue_lat' => 0,
		'venue_description' => ''
	);

	function sanitize($input){
		$clean = array();
		foreach ($input as $key=>$value):
			if(isset(self::$fields[$key]))
				$clean[$key]= call_user_func(self::$fields[$key]['type'], $value);
		endforeach;
		return $clean;
	}


	/*
	* Function which updates existing venue term
	* NOTICE: This function does not check permissions or nonces have been checked.
	*
	* @Since 1.3
	*/
	function update($venue,$input){
		global$eventorganiser_venue_table,$wpdb,$EO_Errors;

		$venue = get_term_by('slug',esc_attr($venue),'event-venue');

		//Provide defaults
		$input = wp_parse_args($input,self::$defaults);

		//Sanitize and whitelist
		$clean = self::sanitize($input);

		//Change action to edit
		$_REQUEST['action']='edit';

		if($clean['venue_name']==''|| $clean['venue_slug']==''){
			$EO_Errors->add('eo_error', __("Venue name or slug is empty",'eventorganiser'));	

		}else{
			//Update taxonomy table
			$return = wp_update_term($venue->term_id,'event-venue', array(
				'name'=>$clean['venue_name'],
				'slug'=>$clean['venue_slug'],
				'description'=>$clean['venue_description'],
			));
			if(is_wp_error($return)){
				$EO_Errors->add('eo_error', __("Venue <strong>was not </strong> updated",'eventorganiser'));	
			}else{
				$inserted = get_term_by('id',$return['term_id'],'event-venue');
				$clean['venue_slug']= $inserted->slug;
				unset($clean['venue_id']);
				$update = $wpdb->update( $eventorganiser_venue_table,$clean, array( 'venue_slug' => $venue->slug));
				$EO_Errors->add('eo_notice', __("Venue <strong>updated</strong>",'eventorganiser'));
				$_REQUEST['event-venue'] = $clean['venue_slug'];
			}
		}
	}

	function insert($input){
		global$eventorganiser_venue_table,$wpdb,$EO_Errors;

		//Provide defaults
		$input = wp_parse_args($input,self::$defaults);

		//Sanitize and whitelist
		$clean = self::sanitize($input);

		//Change action to edit
		$_REQUEST['action']='edit';

		$return = wp_insert_term($clean['venue_name'],'event-venue',array(
				 	'description'=> $clean['venue_description'],
		    			'slug' => $clean['venue_slug'],
		  		));
					
		if(!is_wp_error($return)){
			//This will eventually be depreciated - replaced with just ID.
			$inserted = get_term_by('id',$return['term_id'],'event-venue');
			$clean['venue_slug']= $inserted->slug;
			unset($clean['venue_id']);
			$wpdb->insert($eventorganiser_venue_table,$clean);
			$EO_Errors->add('eo_notice', __("Venue <strong>created</strong>",'eventorganiser'));
			return $return;
		}
			$EO_Errors->add('eo_notice', __("Venue <strong>was not</strong> created",'eventorganiser'));
		return false;
	}


	function delete($venues){
		global$eventorganiser_venue_table,$wpdb,$EO_Errors;
		$venues = (array) $venues;

		//Count the number of deleted venues
		$deleted=0;

		foreach($venues as $venue):
			$venue =  get_term_by('slug',esc_attr($venue),'event-venue');
			$del =wp_delete_term( $venue->term_id, 'event-venue');
			if(!is_wp_error($del)){
				$delmeta = $wpdb->query($wpdb->prepare("DELETE FROM $eventorganiser_venue_table WHERE {$eventorganiser_venue_table}.venue_slug=%s", $venue->slug));
				$deleted += (int) $del;
			}
		endforeach;

		if($deleted>0){
			$EO_Errors = new WP_Error('eo_notice', __("Venue(s) <strong>deleted</strong>",'eventorganiser'));
			return true;
		}else{
			$EO_Errors = new WP_Error('eo_error', __("Venue(s) <strong>were not </strong> deleted",'eventorganiser'));
			return false;
		}
	}




	/**
	 * Gets data from POST (default), supplied array, or from the database if an ID is supplied
	 * @param $location_data
	 * @return null
	 */


	function generate_LatLng($address=''){
		global $EO_Errors;
		$lat=0;
		$lng=0;
		$address = trim($address);

		if(empty($address))
			return array('lat'=>$lat,'lng'=>$lng); //No address - no point showing error.

		$geocode=file_get_contents('http://maps.googleapis.com/maps/api/geocode/json?address='.$address.'&sensor=false');
		$LatLng=false;
		$LatLng= json_decode($geocode);
	
		if(!$LatLng || empty($LatLng->results)){	
			$EO_Errors->add('eo_error', __("There was a problem with locating the latitude and longitude co-ordinates of the venue.",'eventorganiser'));
		}else{
			$lat = esc_html($LatLng->results[0]->geometry->location->lat);
			$lng = esc_html($LatLng->results[0]->geometry->location->lng);
		}
		return array('lat'=>$lat,'lng'=>$lng);
	}



	function get_the_link(){
		global $wp_rewrite;
		$venue_link = $wp_rewrite->get_extra_permastruct('event');

		if ( !empty($venue_link)) {
			 $eventorganiser_option_array = get_option('eventorganiser_options'); 
			$venue_slug = trim($eventorganiser_option_array['url_venue'], "/");

			$venue_link = $venue_slug.'/'.esc_attr($this->slug);
			$venue_link = home_url( user_trailingslashit($venue_link) );
		} else {
			$venue_link = add_query_arg(array('venue' =>$this->slug), '');
			$venue_link = home_url($venue_link);
		}
		return $venue_link;
	}

	function the_link(){
		echo $this->get_the_link();
	}

	function get_the_structure(){
		global $wp_rewrite;
		$venue_link = $wp_rewrite->get_extra_permastruct('event');

		if ( !empty($venue_link)) {
			 $eventorganiser_option_array = get_option('eventorganiser_options'); 
			$venue_link = trim($eventorganiser_option_array['url_venue'], "/");
			$venue_link = home_url( user_trailingslashit($venue_link) );
		} else {
			$venue_link = add_query_arg(array('venue' =>'='), '');
			$venue_link = home_url($venue_link);
		}
		return $venue_link;
	}

	function the_structure(){
		echo $this->get_the_structure();
	}


	function slugify($slug, $venue){
		global $wpdb,$eventorganiser_venue_table;

		$slug =sanitize_title($slug);
	
		//does slug exist?
		$check_sql = "SELECT venue_slug FROM $eventorganiser_venue_table WHERE venue_slug = %s AND  venue_slug != %s LIMIT 1";
		$post_name_check = $wpdb->get_var( $wpdb->prepare( $check_sql, $slug, $venue) );

		if ( $post_name_check) {
			//slug already exist, append suffix until unique.
			$suffix = 2;
			do {
				 $alt_slug = substr( $slug, 0, 200 - ( strlen( $suffix ) + 1 ) ) . "-$suffix";
				$post_name_check = $wpdb->get_var( $wpdb->prepare( $check_sql, $alt_slug, $venue) );
				$suffix++;
			} while ( $post_name_check);
				$slug =  $alt_slug;
			}
		return $slug;
	}

}
?>