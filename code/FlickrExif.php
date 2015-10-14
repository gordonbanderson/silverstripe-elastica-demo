<?php
/**
* Only show a page with login when not logged in
*/
class FlickrExif extends DataObject {

	static $db = array(
		'TagSpace' => 'Varchar',
		'Tag' => 'Varchar',
		'Label' => 'Varchar',
		'Raw' => 'Varchar',
		'TagSpaceID' => 'Int'
	);

	 static $belongs_many_many = array(
		'FlickrPhotos' => 'FlickrPhoto'
	 );

	 static $has_one = array(
		'FlickrPhoto' => 'FlickrPhoto'
	);

}
