<?php
class FlickrPhotoElasticaIndexingExtension extends IdentityElasticaIndexingHelper {

	/**
	 * Add a mapping for the location of the photograph
	 */
	public function updateElasticsearchMapping(\Elastica\Type\Mapping $mapping) {
    	// get the properties of the individual fields as an array
    	$properties = $mapping->getProperties();

    	// add a location with geo point
    	$precision1cm = array('format' => 'compressed', 'precision' => '1cm');
    	$properties['location'] =  array(
    		'type' => 'geo_point',
    		'fielddata' => $precision1cm,
    	);

    	$properties['ShutterSpeed'] = array(
    		'type' => 'string',
    		'index' => 'not_analyzed'
		);

    	$properties['Aperture'] = array(
    		// do not use float as the rounding makes facets impossible
    		'type' => 'double'
    	);

    	// by default casted as a string, we want a date 2015-07-25 18:15:33 y-M-d H:m:s
     	$properties['TakenAt'] = array('type' => 'date', 'format' => 'y-M-d H:m:s');

    	// set the new properties on the mapping
    	$mapping->setProperties($properties);

        return $mapping;
    }


	/**
	 * Populate elastica with the location of the photograph
	 * @param  \Elastica\Document $document Representation of an Elastic Search document
	 * @return \Elastica\Document modified version of the document
	 */
	public function updateElasticsearchDocument(\Elastica\Document $document)
	{
	//	self::$ctr++;
		$coors = array('lat' => $this->owner->Lat, 'lon' => $this->owner->Lon);
		$document->set('location',$coors);
		$sortable = $this->owner->ShutterSpeed;
		$sortable = explode('/', $sortable);
		if (sizeof($sortable) == 1) {
			$sortable = trim($sortable[0]);

			if ($this->owner->ShutterSpeed == null) {
				$sortable = null;
			}

			if ($sortable === '1') {
				$sortable = '1.000000';
			}

		} else if (sizeof($sortable) == 2) {
			$sortable = floatval($sortable[0])/intval($sortable[1]);
			$sortable = round($sortable,6);
		}
		$sortable = $sortable . '|' . $this->owner->ShutterSpeed;
		$document->set('ShutterSpeed', $sortable);
	    return $document;
	}

}
