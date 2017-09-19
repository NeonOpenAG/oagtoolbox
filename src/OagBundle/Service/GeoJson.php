<?php

namespace OagBundle\Service;

use OagBundle\Entity\Geolocation;

class GeoJson extends AbstractService {

    /**
     * Get a GeoJson feature as an associative array from one of our Geolocation
     * entities.
     *
     * @param Geolocation $Geoloc
     * @return array
     */
    public function featureFromGeoloc(Geolocation $geoloc) {
        return array(
            'id' => $geoloc->getLocationIdCode(), // TODO take into account vocab
            'type' => 'Feature',
            'geometry' => array(
                'type' => 'Point',
                'coordinates' => array(
                    $geoloc->getPointPosLong(),
                    $geoloc->getPointPosLat()
                )
            ),
            'properties' => array(
                'title' => $geoloc->getName(),
                'nid' => $geoloc->getLocationIdCode() // TODO take into account vocab
            )
        );
    }

    /**
     * Get a GeoJson feature from some basic properties.
     *
     * @param Geolocation $Geoloc
     * @return array
     */
    public function featureFromCoords($long, $lat) {
        return array(
            'type' => 'Feature',
            'geometry' => array(
                'type' => 'Point',
                'coordinates' => array($long, $lat)
            ),
            'properties' => array()
        );
    }

    /**
     * Get a GeoJson feature collection from a list of GeoJson features.
     * 
     * @param array[] $features
     * @return array
     */
    public function featureCollection($features) {
        return array(
            'type' => 'FeatureCollection',
            'features' => $features
        );
    }

}
