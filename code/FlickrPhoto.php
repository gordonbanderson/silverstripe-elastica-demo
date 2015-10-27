<?php

require_once "phpFlickr.php";

class FlickrPhoto extends DataObject {


	static $db = array(
		'Title' => 'Varchar(255)',
		'FlickrID' => 'Varchar',
		'Description' => 'HTMLText',
		'OriginalDescription' => 'HTMLText',
		'TakenAt' => 'Datetime',
		'DateGranularity' => 'Int',
		'FlickrLicenseID' => 'Int',
		/*
		0	Y-m-d H:i:s
		4	Y-m
		6	Y
		8	Circa...
		 */
		'FlickrLastUpdated' => 'Date',
		'GeoIsPublic' => 'Boolean',
		'FlickrWoeID' => 'Int',
		'FlickrPlaceID' => 'Varchar(255)',
		'GeoIsPublic' => 'Boolean',

		// flag to indicate requiring a flickr API update
		'IsDirty' => 'Boolean',

		'Orientation' => 'Int',
		'FlickrWoeID' => 'Int',
		'Accuracy' => 'Int',
		'FlickrPlaceID' => 'Varchar(255)',
		'Rotation' => 'Int',
		'IsPublic' => 'Boolean',
		'Aperture' => 'Float',
		'ShutterSpeed' => 'Varchar',
		'ImageUniqueID' => 'Varchar',
		'FocalLength35mm' => 'Int',
		'ISO' => 'Int',

		'AspectRatio' => 'Float',
		'Media' => 'Varchar',

		'SmallURL' => 'Varchar(255)',
		'SmallHeight' => 'Int',
		'SmallWidth' => 'Int',

		'MediumURL' => 'Varchar(255)',
		'MediumHeight' => 'Int',
		'MediumWidth' => 'Int',

		'SquareURL' => 'Varchar(255)',
		'SquareHeight' => 'Int',
		'SquareWidth' => 'Int',

		'LargeURL' => 'Varchar(255)',
		'LargeHeight' => 'Int',
		'LargeWidth' => 'Int',

		'ThumbnailURL' => 'Varchar(255)',
		'ThumbnailHeight' => 'Int',
		'ThumbnailWidth' => 'Int',

		'OriginalURL' => 'Varchar(255)',
		'OriginalHeight' => 'Int',
		'OriginalWidth' => 'Int',
		'TimeShiftHours' => 'Int',
		'PromoteToHomePage' => 'Boolean',

		'IgnoreExif' => 'Boolean',
		'Processed' => 'Boolean'
		//TODO - place id
	);


	static $belongs_many_many = array(
		'FlickrSets' => 'FlickrSet'
	);


	// this one is what created the database FlickrPhoto_FlickrTagss
	static $many_many = array(
		'FlickrTags' => 'FlickrTag'
	);


	static $has_many = array(
		'Exifs' => 'FlickrExif'
	);


	static $has_one = array(
		'LocalCopyOfImage' => 'Image',
		'Photographer' => 'FlickrAuthor'
	);


	public static $summary_fields = array(
		'Thumbnail' => 'Thumbnail',
		'Title' => 'Title',
		'TakenAt' => 'TakenAt',
		'HasGeoEng' => 'Geolocated?'
	);


	// -- helper methods to ensure that URLs are of the form //path/to/image so that http and https work with console warnings
	public function ProtocolAgnosticLargeURL() {
		return $this->stripProtocol($this->LargeURL);
	}

	public function ProtocolAgnosticSmallURL() {
		return $this->stripProtocol($this->SmallURL);
	}


	public function ProtocolAgnosticMediumURL() {
		return $this->stripProtocol($this->MediumURL);
	}

	public function ProtocolAgnosticThumbnailURL() {
		return $this->stripProtocol($this->ThumbnailURL);
	}

	public function ProtocolAgnosticOriginalURL() {
		return $this->stripProtocol($this->OriginalURL);
	}


	private function stripProtocol($url) {
		$url = str_replace('http:', '', $url);
		$url = str_replace('https:', '', $url);
		return $url;
	}


	// thumbnail related

	function HorizontalMargin( $intendedWidth ) {
		//FIXME - is there a way to avoid a database call here?
		$fp = DataObject::get_by_id( 'FlickrPhoto', $this->ID );
		return ( $intendedWidth-$fp->ThumbnailWidth )/2;
	}


	function InfoWindow() {
		return GoogleMapUtil::sanitize( $this->renderWith( 'FlickrPhotoInfoWindow' ) );
	}


	function VerticalMargin( $intendedHeight ) {
		//FIXME - is there a way to avoid a database call here?
		$fp = DataObject::get_by_id( 'FlickrPhoto', $this->ID );
		return ( $intendedHeight-$fp->ThumbnailHeight )/2;
	}


	public function Link() {
		$link = "http://www.flickr.com/photos/{$this->Photographer()->PathAlias}/{$this->FlickrID}/";
		return $link;
	}


	public function AbsoluteLink() {
		return $this->Link();
	}


	/*
	Mark image as dirty upon a save
	*/
	function onBeforeWrite() {
		parent::onBeforeWrite();

		$quickTags = FlickrTag::CreateOrFindTags($this->QuickTags);
		$this->FlickrTags()->addMany($quickTags);
		if ($this->LargeWidth > 0) {
			$this->AspectRatio = ($this->LargeHeight) / ($this->LargeWidth);
		}


		if (!$this->KeepClean) {
			$this->IsDirty = true;
		} else {
			$this->IsDirty = false;
		}
	}


	function getCMSFields() {
		//Requirements::css( FLICKR_EDIT_TOOLS_PATH . '/css/flickredit.js' );

		$flickrSetID = Controller::curr()->request->param( 'ID' );
		$params = Controller::curr()->request->params();
		$url = $_GET['url'];
		$splits = explode('/FlickrSet/item/', $url);
		$setid = null;
		if (sizeof($splits) == 2) {
			$splits = explode('/', $splits[1]);
			$setid = $splits[0];
		}


		//$fields = new FieldList();
		$fields = parent::getCMSFields();

		$fields->push( new TabSet( "Root", $mainTab = new Tab( "Main" ) ) );
		$mainTab->setTitle( _t( 'SiteTree.TABMAIN', "Main" ) );

		$forTemplate = new ArrayData( array(
				'FlickrPhoto' => $this,
				'FlickrSetID' => $setid
		));
		$imageHtml = $forTemplate->renderWith( 'FlickrImageEditing' );


		$lfImage = new LiteralField( 'FlickrImage', $imageHtml );
		$fields->addFieldToTab( 'Root.Main', $lfImage );
		$fields->addFieldToTab( 'Root.Main',  new TextField( 'Title', 'Title') );
				$fields->addFieldToTab( 'Root.Main', new TextAreaField( 'Description', 'Description' )  );

		// only show a map for editing if no sets have geolock on them
		$lockgeo = false;
		foreach ($this->FlickrSets() as $set) {
			if ($set->LockGeo == true) {
				$lockgeo = true;
				break;
			}
		}

		if (!$lockgeo) {
			 $fields->addFieldToTab( "Root.Location", $mapField = new LatLongField( array(
					new TextField( 'Lat', 'Latitude' ),
					new TextField( 'Lon', 'Longitude' ),
					new TextField( 'ZoomLevel', 'Zoom' )
				),
					array( 'Address' )
					)
			 );


			$guidePoints = array();

			foreach ($this->FlickrSets() as $set) {

				foreach ($set->FlickrPhotos()->where('Lat != 0 and Lon != 0') as $fp) {
					if (($fp->Lat != 0) && ($fp->Lon != 0)) {
						array_push($guidePoints, array(
							'latitude' => $fp->Lat,
							'longitude' => $fp->Lon
						));
					}
				}
			}

			if (count($guidePoints) > 0) {
				$mapField->setGuidePoints($guidePoints);
			}
		}

		// quick tags, faster than the grid editor - these are processed prior to save to create/assign tags
		$fields->addFieldToTab( 'Root.Main',  new TextField( 'QuickTags', 'Enter tags here separated by commas') );

		$gridConfig = GridFieldConfig_RelationEditor::create();//->addComponent( new GridFieldSortableRows( 'Value' ) );
		$gridConfig->getComponentByType( 'GridFieldAddExistingAutocompleter' )->setSearchFields( array( 'Value','RawValue' ) );
		$gridField = new GridField( "Tags", "List of Tags", $this->FlickrTags(), $gridConfig );
		$fields->addFieldToTab( "Root.Main", $gridField );

		$fields->addFieldToTab("Root.Main", new CheckboxField('PromoteToHomePage', 'Promote to Home Page'));

		$fields->removeByName('OriginalDescription');
		$fields->removeByName('TakenAt');
		$fields->removeByName('DateGranularity');
		$fields->removeByName('FlickrLicenseID');
		$fields->removeByName('FlickrLastUpdated');
		$fields->removeByName('GeoIsPublic');
		$fields->removeByName('FlickrWoeID');
		$fields->removeByName('FlickrPlaceID');
		$fields->removeByName('IsDirty');
		$fields->removeByName('Accuracy');
		$fields->removeByName('Rotation');
		$fields->removeByName('IsPublic');
		$fields->removeByName('Aperture');
		$fields->removeByName('ImageUniqueID');
		$fields->removeByName('FocalLength35mm');
		$fields->removeByName('ISO');
		$fields->removeByName('Media');
		$fields->removeByName('Orientation');
		$fields->removeByName('AspectRatio');
		$fields->removeByName('SmallHeight');
		$fields->removeByName('SmallWidth');
		$fields->removeByName('MediumURL');
		$fields->removeByName('MediumHeight');
		$fields->removeByName('MediumWidth');
		$fields->removeByName('MediumWidth');
		$fields->removeByName('SquareHeight');
		$fields->removeByName('SquareWidth');
		$fields->removeByName('LargeURL');
		$fields->removeByName('LargeWidth');
		$fields->removeByName('LargeHeight');
		$fields->removeByName('ThumbnailURL');
		$fields->removeByName('ThumbnailWidth');
		$fields->removeByName('ThumbnailHeight');
		$fields->removeByName('OriginalURL');
		$fields->removeByName('OriginalWidth');
		$fields->removeByName('OriginalHeight');
		$fields->removeByName('TimeShiftHours');
		$fields->removeByName('IgnoreExif');
		$fields->removeByName('Processed');
		$fields->removeByName('LocalCopyOfImage');
		$fields->removeByName('PhotographerID');
		$fields->removeByName('PromoteToHomePage');
		$fields->removeByName('Exifs');
		$fields->removeByName('FlickrSets');
		$fields->removeByName('FlickrTags');
		$fields->removeByName('FlickrID');
		$fields->removeByName('SquareURL');
		$fields->removeByName('SmallURL');


		return $fields;
	}


	public function AdjustedTime() {
		return 'FP ADJ TIME '.$this->TimeShiftHours;
	}


	public function getThumbnail() {
		return DBField::create_field( 'HTMLVarchar',
			'<img class="flickrThumbnail" data-flickr-medium-url="'.$this->MediumURL.'" src="'.$this->ThumbnailURL.'"  data-flickr-thumbnail-url="'.$this->ThumbnailURL.'"/>' );
	}


	private function initialiseFlickr() {
		if (!isset($this->f)) {
			// get flickr details from config
			$key = Config::inst()->get( 'FlickrController', 'api_key' );
			$secret = Config::inst()->get('FlickrController', 'secret' );
			$access_token = Config::inst()->get( 'FlickrController', 'access_token' );

			$this->f = new phpFlickr( $key, $secret );

			//Fleakr.auth_token    = ''
			$this->f->setToken( $access_token );
		}
	}


	public function HasGeo() {
		return $this->Lat != 0 || $this->Lon != 0;
	}


	public function HasGeoEng() {
		return $this->HasGeo() ? 'Yes': 'No';
	}


	private function endsWith($haystack,$needle) {
	    return (strcmp(substr($haystack, strlen($haystack) - strlen($needle)),$needle)===0);
	}


	public function checkExifRequired() {
		$exifRequired = true;

		// If no accurate time assume no EXIF data of uses
		if ($this->DateGranularity > 0) {
			$exifRequired = false;
		}

		//01-01 00:00:00
		if ($this->endsWith("{$this->TakenAt}", "01-01 00:00:00")) {
			$exifRequired = false;
		};

		$this->IgnoreExif = !$exifRequired;

		return $exifRequired;
	}


	public function loadExif() {
		echo "Loading exif\n";
		$this->initialiseFlickr();
		$exifData = $this->f->photos_getExif( $this->FlickrID );

		// delete any old exif data
		$sql = "DELETE from FlickrExif where FlickrPhotoID=".$this->ID;
		DB::query( $sql );

		// conversion factor or fixed legnth depending on model of camera
		$focallength = -1;
		$fixFocalLength = 0;
		$focalConversionFactor = 1;

		echo "Storing exif data for ".$this->Title."\n";
		foreach ( $exifData['exif'] as $key => $exifInfo ) {
			DB::query('begin;');
			$exif = new FlickrExif();
			$exif->TagSpace = $exifInfo['tagspace'];
			$exif->TagSpaceID = $exifInfo['tagspaceid'];
			$exif->Tag = $exifInfo['tag'];
			$exif->Label = $exifInfo['label'];
			$exif->Raw = $exifInfo['raw']['_content'];
			$exif->FlickrPhotoID = $this->ID;
			//NO NEED TO SAVE HERE
			//$exif->write();

			echo "- {$exif->Tag} = {$exif->Raw}\n";

			if ( $exif->Tag == 'FocalLength' ) {
				$raw = str_replace(' mm', '', $exif->Raw);
				$focallength = $raw; // model focal length
			}
			else if ( $exif->Tag == 'ImageUniqueID' ) {
					$this->ImageUniqueID = $exif->Raw;
			} else
				if ( $exif->Tag == 'ISO' ) {
						$this->ISO = $exif->Raw;
				} else
				if ( $exif->Tag == 'ExposureTime' ) {
						$this->ShutterSpeed = $exif->Raw;
				} else
				if ( $exif->Tag == 'FocalLengthIn35mmFormat' ) {
						$raw35 = $exif->Raw;
						$fl35 = str_replace( ' mm', '', $raw35 );
						$fl35 = (int) $fl35;
						$this->FocalLength35mm = $fl35;
				} else
				if ( $exif->Tag == 'FNumber' ) {
						$this->Aperture = $exif->Raw;
				}
				// FIXME, make configurable
				// Hardwire phone focal length
				else if ($exif->Tag == 'Model') {
					$name = $exif->Raw;
					if ($name === 'C6602') {
						$this->FocalLength35mm = 28;
						$fixFocalLength = 28;
					}

					if ($name === 'Canon IXUS 220 HS') {
						$focalConversionFactor = 5.58139534884;
					}

					if ($name === 'Canon EOS 450D') {
						$focalConversionFactor = 1.61428571429;
					}
				}

			$exif = NULL;
			gc_collect_cycles();
		}

		// try and fix the 35mm focal length
		if ((int)($this->FocalLength35mm) === 0) {
			if ($fixFocalLength) {
				$this->FocalLength35mm = 28;
			} else if ($focalConversionFactor !== 1) {
				$f = $focalConversionFactor*$focallength;
				$this->FocalLength35mm = round($f);
			}
		}

		echo "/storing exif";
		DB::query('commit;');
	}


	/*
	Update Flickr with details held in SilverStripe
	@param $descriptionSuffix The suffix to be appended to the photographic description
	*/
	public function writeToFlickr($descriptionSuffix) {
		$this->initialiseFlickr();

		$fullDesc = $this->Description."\n\n".$descriptionSuffix;
		$fullDesc = trim($fullDesc);

		$year = substr($this->TakenAt,0,4);
		$fullDesc = str_replace('$Year', $year, $fullDesc);
		$this->f->photos_setMeta($this->FlickrID, $this->Title, $fullDesc);

		$tagString = '';
		foreach ($this->FlickrTags() as $tag) {
			$tagString .= '"'.$tag->Value.'" ';
		}

		$this->f->photos_setTags($this->FlickrID, $tagString);

		if ($this->HasGeo()) {
			$this->f->photos_geo_setLocation ($this->FlickrID, $this->getMappableLatitude(), $this->getMappableLongitude());
		}

		$this->KeepClean = true;
		$this->write();
	}

}
