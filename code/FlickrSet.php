<?php
class FlickrSet extends DataObject {

	static $db = array(
		'Title' => 'Varchar(255)',
		'FlickrID' => 'Varchar',
		'Description' => 'HTMLText',
		'FirstPictureTakenAt' => 'Datetime',
		// flag to indicate requiring a flickr API update
		'IsDirty' => 'Boolean',
		'LockGeo' => 'Boolean',
		'BatchTags' => 'Varchar',
		'BatchTitle' => 'Varchar',
		'BatchDescription' => 'HTMLText',
		'ImageFooter' => 'Text'
	);

	private static $defaults = array(
		'LockGeo' => true
	);

	static $many_many = array(
		'FlickrPhotos' => 'FlickrPhoto'
	);

	// this is the assets folder
	static $has_one = array (
		'AssetFolder' => 'Folder',
		'PrimaryFlickrPhoto' => 'FlickrPhoto'
	);


	/// model admin
	static $searchable_fields = array(
		'Title',
		'Description',
		'FlickrID'
	);


  	public static $default_sort = 'FirstPictureTakenAt DESC';



	/*
	Mark image as dirty upon a save
	*/
	function onBeforeWrite() {
		parent::onBeforeWrite();
		if (!$this->KeepClean) {
			$this->IsDirty = true;
		}
	}


	/*
	Count the number of non zero lat and lon points - if > 0 then we can draw a map
	*/
	public function HasGeo() {
		$ct = $this->FlickrPhotos()->where('Lat != 0 OR Lon != 0')->count();
		return $ct > 0;
	}


  	/*
	Render a map at the provided lat,lon, zoom from the editing functions,
	*/
	public function BasicMap() {

		$photosWithLocation = $this->FlickrPhotos()->where('Lat != 0 AND Lon !=0');
		if ($photosWithLocation->count() == 0) {
		  return ''; // don't render a map
		}

		//$photosWithLocation->setRenderMarkers(false);
		$map = $photosWithLocation->getRenderableMap();

		$map->setZoom($this->owner->ZoomLevel);
		$map->setAdditionalCSSClasses('fullWidthMap');
		$map->setShowInlineMapDivStyle(true);

		//$map->setInfoWindowWidth(500);


		// add any KML map layers
		if (Object::has_extension($this->owner->ClassName, 'MapLayerExtension')) {
		  foreach($this->owner->MapLayers() as $layer) {
			$map->addKML($layer->KmlFile()->getAbsoluteURL());
		  }
			$map->setEnableAutomaticCenterZoom(true);
		}


		// add points of interest taking into account the default icon of the layer as an override
		if (Object::has_extension($this->owner->ClassName, 'PointsOfInterestLayerExtension')) {
			$markercache = SS_Cache::factory('mappable');

			$ck = $this->getPoiMarkersCacheKey();
			$map->MarkersCacheKey = $ck;

			// If we have JSON already do not load the objects
			if (!($jsonMarkers = $markercache->test($ck)))	{
				foreach($this->owner->PointsOfInterestLayers() as $layer) {
					$layericon = $layer->DefaultIcon();
					if ($layericon->ID === 0) {
						$layericon = null;
					}
					foreach ($layer->PointsOfInterest() as $poi) {
						if ($poi->MapPinEdited) {
							if ($poi->MapPinIconID == 0) {
								$poi->CachedMapPin = $layericon;
							}
							$map->addMarkerAsObject($poi);
						}
					}
				}
			}
		}

		$map->setClusterer(true);
		$map->setEnableAutomaticCenterZoom(true);

		$map->setZoom(10);
		$map->setAdditionalCSSClasses('fullWidthMap');
		$map->setShowInlineMapDivStyle(true);
		$map->setClusterer(true);

		return $map;
	}



	public function writeToFlickr() {
		$suffix = $this->ImageFooter ."\n\n".Controller::curr()->SiteConfig()->ImageFooter;
		$imagesToUpdate = $this->FlickrPhotos()->where('IsDirty = 1');
		$ctr = 1;
		$amount = $imagesToUpdate->count();

		foreach ($imagesToUpdate as $fp) {
			error_log('UPDATING:'.$fp->Title);
		  	$fp->writeToFlickr($suffix);
		  	$ctr++;
		}
	}

}
