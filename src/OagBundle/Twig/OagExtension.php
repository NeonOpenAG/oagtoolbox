<?php

namespace OagBundle\Twig;

class OagExtension extends \Twig_Extension
{

    public function getFilters()
    {
        return array(
            new \Twig_SimpleFilter('formatTagList', array($this, 'formatTagList')),
            new \Twig_SimpleFilter('suggestedTagList', array($this, 'suggestedTagList')),
        );
    }

    public function formatTagList($tags)
    {
        return array_reduce($tags, function ($reduction, $tag) {
            $activityList = &$reduction[$tag->getActivityId()];
            if (!$activityList)
                $activityList = array();
            array_push($activityList, $tag);
            return $reduction;
        }, array());
    }

    public function suggestedTagList($suggestedTag)
    {
        $lines = array();
        foreach ($suggestedTag as $tag) {
            $lines[] = $tag->getTag()->getDescription();
        }
        return implode(', ', $lines);
    }

}
