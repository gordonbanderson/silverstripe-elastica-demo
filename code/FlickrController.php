<?php

require_once "phpFlickr.php";

class FlickrController extends Page_Controller implements PermissionProvider {

	static $allowed_actions = array(
		'index',
		'importSet',
		'importFromSearch'
	);


	/*
	Cache the tags in memory to try and avoid DB calls
	 */
	private static $tagCache = array();


	public function providePermissions() {
		return array(
			"FLICKR_EDIT" => "Able to import and edit flickr data"
		);
	}


	public function init() {
		parent::init();

		// get flickr details from config
		$key = Config::inst()->get( $this->class, 'api_key' );
		$secret = Config::inst()->get( $this->class, 'secret' );
		$access_token = Config::inst()->get( $this->class, 'access_token' );

		$this->f = new phpFlickr( $key, $secret );

		$this->f->setToken( $access_token );

		if (!$key) {
			echo "In order to import photographs Flickr key, secret and access token must be provided";
			die;
		}
	}


	public function index() {
		// Code for the index action here
		return array();
	}


	public function importFromSearch() {
		$canAccess = ( Director::isDev() || Director::is_cli() || Permission::check( "ADMIN" ) );
		if ( !$canAccess ) {
			return Security::permissionFailure( $this );
		}

		$searchParams = array();

		//any high number
		$nPages = 1e7;

		$page = 1;
		$ctr = 1;

		$start=time();


		while ($page <= $nPages) {
			echo "\n\n\n\n----------- LOADING PICS -----------\n";
			$query = $_GET['q'];

			if ($nPages == 1e7) {
				echo "Starting search for $query\n";
			} else {
				echo "\n\nLoading $page / $nPages\n";
			}

			$searchParams['text'] = $query;
			$searchParams['license'] = 7;
			$searchParams['per_page'] = 500;
			$searchParams['page'] = $page;
			$searchParams['extras'] = 'description, license, date_upload, date_taken, owner_name, icon_server, original_format, last_update, geo, tags, machine_tags, o_dims, views, media, path_alias, url_sq, url_t, url_s, url_q, url_m, url_n, url_z, url_c, url_l, url_o';
			$searchParams['sort'] = 'relevance'; // 'interestingness-desc'; // also try relevance

			$data = $this->f->photos_search($searchParams);
			$nPages = $data['pages'];
			$totalImages = $data['total'];

			echo "Found $nPages pages\n";
			echo "n photos returned ".sizeof($data['photo']);

			foreach($data['photo'] as $photo) {
				echo "[Import photo $ctr / $totalImages, page $page / $nPages]\n";
				echo "Loading photo:".$photo['title']."\n";

				$elapsed = time()-$start;
				$perPhoto = $elapsed/$ctr;
				$remaining = ($totalImages-$ctr)*$perPhoto;
				$hours = floor($remaining / 3600);
				$minutes = floor(($remaining / 60) % 60);
				$seconds = $remaining % 60;
				echo "Estimated time remaning - $hours:$minutes:$seconds\n";

				$flickrPhoto = $this->createOrUpdateFromFlickrArray($photo);
				echo "\tLoading exif data\n";
				if (!$flickrPhoto->Processed) {
					if ($flickrPhoto->checkExifRequired()) {
						$flickrPhoto->loadExif();
					}
					$flickrPhoto->Processed = true;
					$flickrPhoto->write();
				}
				$ctr++;
			}
			$page++;
		}
	}


	public function importSet() {
		$page= 1;
		static $only_new_photos = false;

		$canAccess = ( Director::isDev() || Director::is_cli() || Permission::check( "ADMIN" ) );
		if ( !$canAccess ) return Security::permissionFailure( $this );

		// Code for the register action here
		$flickrSetID = $this->request->param( 'ID' );
		$path = $_GET['path'];
		$parentNode = SiteTree::get_by_link($path);
		if ($parentNode == null) {
			echo "ERROR: Path ".$path." cannot be found in this site\n";
			die;
		}

		$this->FlickrSetId = $flickrSetID;

		$photos = $this->f->photosets_getPhotos( $flickrSetID,
			'license, date_upload, date_taken, owner_name, icon_server, original_format, last_update, geo, tags, machine_tags, o_dims, views, media, path_alias, url_sq, url_t, url_s, url_m, url_o, url_l,description',
			null,
		500);

		$photoset = $photos['photoset'];

		$flickrSet = $this->getFlickrSet( $flickrSetID );

		// reload from DB with date - note the use of quotes as flickr set id is a string
		$flickrSet = DataObject::get_one( 'FlickrSet', 'FlickrID=\''.$flickrSetID."'" );
		$flickrSet->FirstPictureTakenAt = $photoset['photo'][0]['datetaken'];
		$flickrSet->KeepClean = true;
		$flickrSet->Title = $photoset['title'];
		$flickrSet->write();

		echo "Title set to : ".$flickrSet->Title;

		if ( $flickrSet->Title == null ) {
			echo( "ABORTING DUE TO NULL TITLE FOUND IN SET - ARE YOU AUTHORISED TO READ SET INFO?" );
			die;
		}

		$datetime = explode( ' ', $flickrSet->FirstPictureTakenAt );
		$datetime = $datetime[0];

		list( $year, $month, $day ) = explode( '-', $datetime );
		echo "Month: $month; Day: $day; Year: $year<br />\n";

		// now try and find a flickr set page
		$flickrSetPage = DataObject::get_one( 'FlickrSetPage', 'FlickrSetForPageID='.$flickrSet->ID );
		if ( !$flickrSetPage ) {
			$flickrSetPage = new FlickrSetPage();
			$flickrSetPage->Title = $photoset['title'];
			$flickrSetPage->Description = $flickrSet->Description;

			//update FlickrSetPage set Description = (select Description from FlickrSet where FlickrSet.ID = FlickrSetPage.FlickrSetForPageID);

			$flickrSetPage->FlickrSetForPageID = $flickrSet->ID;
			$flickrSetPage->write();
			// create a stage version also

		}
		$flickrSetPage->Title = $photoset['title'];

		$flickrSetPage->ParentID = $parentNode->ID;
		$flickrSetPage->write();
		$flickrSetPage->publish( "Live", "Stage" );

		$flickrSetPageID = $flickrSetPage->ID;
		gc_enable();

		$f1 = Folder::find_or_make( "flickr/$year" );
		$f1->Title = $year;
		$f1->write();

		$f1 = Folder::find_or_make( "flickr/$year/$month" );
		$f1->Title = $month;
		$f1->write();

		$f1 = Folder::find_or_make( "flickr/$year/$month/$day" );
		$f1->Title = $day;
		$f1->write();

		exec( "chmod 775 ../assets/flickr/$year" );
		exec( "chmod 775 ../assets/flickr/$year/$month" );
		exec( "chmod 775 ../assets/flickr/$year/$month/$day" );
		exec( "chown gordon:www-data ../assets/flickr/$year" );;
		exec( "chown gordon:www-data ../assets/flickr/$year/$month" );;
		exec( "chown gordon:www-data ../assets/flickr/$year/$month/$day" );;


		$folder = Folder::find_or_make( "flickr/$year/$month/$day/" . $flickrSetID );

		$cmd = "chown gordon:www-data ../assets/flickr";
		exec( $cmd );

		exec( 'chmod 775 ../assets/flickr' );


		// new folder case
		if ( $flickrSet->AssetFolderID == 0 ) {
			$flickrSet->AssetFolderID = $folder->ID;
			$folder->Title = $flickrSet->Title;
			$folder->write();

			$cmd = "chown gordon:www-data ../assets/flickr/$year/$month/$day/".$flickrSetID;
			exec( $cmd );

			$cmd = "chmod 775 ../assets/flickr/$year/$month/$day/".$flickrSetID;
			exec( $cmd );
		}

		$flickrSetAssetFolderID = $flickrSet->AssetFolderID;

		$flickrSetPageDatabaseID = $flickrSetPage->ID;


		//$flickrSet = NULL;
		$flickrSetPage = NULL;

		$numberOfPics = count($photoset['photo']);
		$ctr = 1;
		foreach ( $photoset['photo'] as $key => $value ) {
			echo "Importing photo {$ctr}/${numberOfPics}\n";

			$flickrPhoto = $this->createOrUpdateFromFlickrArray($value);

			if ($value['isprimary'] == 1) {
				$flickrSet->MainImage = $flickrPhoto;
			}

			$flickrPhoto->write();
			$flickrSet->FlickrPhotos()->add( $flickrPhoto );

			$flickrPhoto->write();


			$ctr++;

			$flickrPhoto = NULL;
		}

		 //update orientation
		$sql = 'update FlickrPhoto set Orientation = 90 where ThumbnailHeight > ThumbnailWidth;';
		DB::query( $sql );


		// now download exifs
		$ctr = 0;
		foreach ( $photoset['photo'] as $key => $value ) {
			echo "IMPORTING EXIF {$ctr}/$numberOfPics\n";
			$flickrPhotoID = $value['id'];
			$flickrPhoto = FlickrPhoto::get()->filter('FlickrID',$flickrPhotoID)->first();
			$flickrPhoto->loadExif();
			$flickrPhoto->write();
			$ctr++;
		}

		$this->fixSetMainImages();
		$this->fixDateSetTaken();

		die(); // abort rendering
	}


	private function createOrUpdateFromFlickrArray($value, $only_new_photos = false) {
		gc_collect_cycles();

		$flickrPhotoID = $value['id'];

		// the author, e.g. gordonbanderson
		$pathalias = $value['pathalias'];

		// do we have a set object or not
		$flickrPhoto = DataObject::get_one( 'FlickrPhoto', 'FlickrID='.$flickrPhotoID );

		// if a set exists update data, otherwise create
		if ( !$flickrPhoto ) {
			$flickrPhoto = new FlickrPhoto();
		}

		// if we are in the mode of only importing new then skip to the next iteration if this pic already exists
		else if ( $only_new_photos ) {
			continue;
		}

		$flickrPhoto->Title = $value['title'];

		$flickrPhoto->FlickrID = $flickrPhotoID;
		$flickrPhoto->KeepClean = true;

		$flickrPhoto->TakenAt = $value['datetaken'];
		$flickrPhoto->DateGranularity = $value['datetakengranularity'];


		$flickrPhoto->MediumURL = $value['url_m'];
		$flickrPhoto->MediumHeight = $value['height_m'];
		$flickrPhoto->MediumWidth = $value['width_m'];

		$flickrPhoto->SquareURL = $value['url_s'];
		$flickrPhoto->SquareHeight = $value['height_s'];
		$flickrPhoto->SquareWidth = $value['width_s'];


		$flickrPhoto->ThumbnailURL = $value['url_t'];
		$flickrPhoto->ThumbnailHeight = $value['height_t'];
		$flickrPhoto->ThumbnailWidth = $value['width_t'];

		$flickrPhoto->SmallURL = $value['url_s'];
		$flickrPhoto->SmallHeight = $value['height_s'];
		$flickrPhoto->SmallWidth = $value['width_s'];

		// If the image is too small, large size will not be set
		if (isset($value['url_l'])) {
			$flickrPhoto->LargeURL = $value['url_l'];
			$flickrPhoto->LargeHeight = $value['height_l'];
			$flickrPhoto->LargeWidth = $value['width_l'];
		}


		$flickrPhoto->OriginalURL = $value['url_o'];
		$flickrPhoto->OriginalHeight = $value['height_o'];
		$flickrPhoto->OriginalWidth = $value['width_o'];

		$flickrPhoto->Description = 'test';// $value['description']['_content'];




		$lat = number_format( $value['latitude'], 15 );
		$lon = number_format( $value['longitude'], 15 );

		if (isset($value['place_id'])) {
			$flickrPhoto->FlickrPlaceID = $value['place_id'];
		}

		if (isset($value['woeid'])) {
			$flickrPhoto->WoeID = $value['woeid'];
		}

		$flickrPhoto->Media = $value['media'];


		if ( $value['latitude'] ) {
			$flickrPhoto->Lat = $lat;
			$flickrPhoto->ZoomLevel = 15;
		}
		if ( $value['longitude'] ) {
			$flickrPhoto->Lon = $lon;
		}

		if ( $value['accuracy'] ) {
			$flickrPhoto->Accuracy = $value['accuracy'];
		}

		if ( isset( $value['geo_is_public'] ) ) {
			$flickrPhoto->GeoIsPublic = $value['geo_is_public'];
		}

		if ( isset( $value['woeid'] ) ) {
			$flickrPhoto->WoeID = $value['woeid'];
		}



		$singlePhotoInfo = $this->f->photos_getInfo( $flickrPhotoID );

		if (isset($singlePhotoInfo['photo']['license'])) {
			$flickrPhoto->FlickrLicenseID = $singlePhotoInfo['photo']['license'];
		}

		$owner = $singlePhotoInfo['photo']['owner'];
		$pathalias = $owner['path_alias'];

		$author = FlickrAuthor::get()->filter('PathAlias', $pathalias)->first();
		if (!$author) {
			$author = new FlickrAuthor();
			$author->PathAlias = $pathalias;
			$author->RealName = $owner['realname'];
			$author->Username = $owner['username'];
			$author->NSID = $owner['nsid'];
			$author->write();
		} elseif (!$author->RealName) {
			// Fix missing values
			$author->PathAlias = $pathalias;
			$author->RealName = $owner['realname'];
			$author->Username = $owner['username'];
			$author->NSID = $owner['nsid'];
			$author->write();
		}

		$flickrPhoto->PhotographerID = $author->ID;


		$flickrPhoto->Description = $singlePhotoInfo['photo']['description']['_content'];
		$flickrPhoto->Rotation = $singlePhotoInfo['photo']['rotation'];

		if ( isset( $singlePhotoInfo['photo']['visibility'] ) ) {
			$flickrPhoto->IsPublic = $singlePhotoInfo['photo']['visibility']['ispublic'];
		}

		$flickrPhoto->write();

		foreach ( $singlePhotoInfo['photo']['tags']['tag'] as $key => $taginfo ) {
			$content = $taginfo['_content'];

			$tags = FlickrTag::get()->filter('Value', $content);
			if ($tags->count() > 1) {
				throw new Exception("The tag $content has multiple instances");
			}
			$tag = $tags->first();
			if ( !$tag ) {
				$tag = new FlickrTag();
				$tag->FlickrID = $taginfo['id'];
				$tag->Value = $taginfo['_content'];
				$tag->RawValue = $taginfo['raw'];
				$tag->write();
			}



			$ftags= $flickrPhoto->FlickrTags();
			$ftags->add( $tag );

			$flickrPhoto->write();

			$tag = NULL;
			$ftags = NULL;
		}

		return $flickrPhoto;
	}


	/*
	Either get the set from the database, or if it does not exist get the details from flickr and add it to the database
	*/
	private function getFlickrSet( $flickrSetID ) {
		// do we have a set object or not
		$flickrSet = DataObject::get_one( 'FlickrSet', 'FlickrID=\''.$flickrSetID."'" );

		// if a set exists update data, otherwise create
		if ( !$flickrSet ) {
			$flickrSet = new FlickrSet();
			$setInfo = $this->f->photosets_getInfo( $flickrSetID );
			$setTitle = $setInfo['title']['_content'];
			$setDescription = $setInfo['description']['_content'];
			$flickrSet->Title = $setTitle;
			$flickrSet->Description = $setDescription;
			$flickrSet->FlickrID = $flickrSetID;
			$flickrSet->KeepClean = true;
			$flickrSet->write();
		}

		return $flickrSet;
	}

}
