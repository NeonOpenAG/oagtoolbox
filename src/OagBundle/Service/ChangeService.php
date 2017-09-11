<?php

namespace OagBundle\Service;

use OagBundle\Entity\Change;

/**
 * A service for dealing with Change entities. Generally, this involves applying
 * them to an IATI file.
 */
class ChangeService extends AbstractService {

    /**
     * Summarise the history of an activity in the application and present it
     * as an applicable change for the current state of the activity given.
     *
     * This is useful for working out what past changes can be reapplied to
     * activities in an untouched IATI file, for example.
     *
     * @param \SimpleXMLElement &$activity the activity to find the history of
     * @return Change
     */
    public function summariseHistory(\SimpleXMLElement &$activity) {
        $srvIATI = $this->getContainer()->get(IATI::class);
        $changeRepo = $this->getContainer()->get('doctrine')->getRepository(Change::class);

        // get past changes that have been done on the activity
        $activityId = $srvIATI->getActivityId($activity);
        $pastChanges = $changeRepo->findBy(array('activityId' => $activityId));

        // flatten them all into one big change
        $flattened = $this->flatten($pastChanges);

        // compare this change to the current state of the activity, keeping
        // only applicable changes
        $applicable = $this->applicable($flattened, $activity);

        return $applicable;
    }

    /**
     * Compile a series of changes that an IATI file has undergone into one
     * summary of the net change that would have been had following all the
     * individual changes.
     *
     * Changes are applied in the order in which they are documented to have
     * happened through their timestamp.
     *
     * @param Change[] $pastChanges the changes the IATI file has undergone
     * @return Change an array summarising the net additions/removals
     */
    public function flatten($pastChanges) {
        // sort chronologically
        usort($pastChanges, function ($a, $b) {
            if ($a->getTimestamp() < $b->getTimestamp()) {
                // $a happened before $b
                return -1;
            } elseif ($a->getTimestamp() > $b->getTimestamp()) {
                // $b happened before $a
                return 1;
            }

            return 0;
        });

        // work out the net effect
        $netAdded = array();
        $netRemoved = array();
        foreach ($pastChanges as $change) {
            // tags being added that were previously removed cancel out in both
            $toAdd = array_udiff($change->getAddedTags()->toArray(), $netRemoved, array($this, 'doctrineEquals'));
            $netRemoved = array_udiff($netRemoved, $change->getAddedTags()->toArray(), array($this, 'doctrineEquals'));

            // tags being removed that were previously added cancel out in both
            $toRemove = array_udiff($change->getRemovedTags()->toArray(), $netAdded, array($this, 'doctrineEquals'));
            $netAdded = array_udiff($netAdded, $change->getRemovedTags()->toArray(), array($this, 'doctrineEquals'));

            // add what is still left to be added or removed
            $netAdded = array_merge($netAdded, $toAdd);
            $netRemoved = array_merge($netRemoved, $toRemove);
        }

        // get rid of any duplicates
        $netAdded = array_unique($netAdded, SORT_REGULAR);
        $netRemoved = array_unique($netRemoved, SORT_REGULAR);

        // TODO is it correct to use an entity like this that is neither complete nor persisted?
        $netChange = new Change();
        $netChange->setAddedTags($netAdded);
        $netChange->setRemovedTags($netRemoved);
        return $netChange;
    }

    /**
     * Compares a change against an activity to see what can actually be applied.
     *
     * Only tags to be added that aren't already added are included and only
     * tags to be removed that are there to remove are included in the summary
     * change.
     *
     * @param Change $toApply
     * @param \SimpleXMLElement &$activity
     * @return Change
     */
    public function applicable(Change $toApply, \SimpleXMLElement &$activity) {
        $srvIATI = $this->getContainer()->get(IATI::class);

        $contained = $srvIATI->getActivityTags($activity);

        $yetToAdd = array_udiff($toApply->getAddedTags()->toArray(), $contained, array($this, 'doctrineEquals'));
        $removable = array_uintersect($toApply->getRemovedTags()->toArray(), $contained, array($this, 'doctrineEquals'));

        $applicableChange = new Change();
        $applicableChange->setAddedTags($yetToAdd);
        $applicableChange->setRemovedTags($removable);
        return $applicableChange;
    }

    /**
     * Apply a change to an activity. Modifications are made by reference.
     *
     * @param Change $toApply
     * @param \SimpleXMLElement &$activity
     */
    public function apply(Change $toApply, \SimpleXMLElement &$activity) {
        $srvIATI = $this->getContainer()->get(IATI::class);

        foreach ($toApply->getAddedTags() as $tag) {
            $srvIATI->addActivityTag($activity, $tag);   
        }

        foreach ($toApply->getRemovedTags() as $tag) {
            $srvIATI->removeActivityTag($activity, $tag);   
        }
    }

    /**
     * Just a tiny callback utility function to compare two doctrine events. For
     * reasons unknown, PHP's build-in array_diff casts objects to strings
     * before comparing them, which breaks with a string-casting error whenever
     * any objects that have no __toString() method implemented.
     */
    private function doctrineEquals($a, $b) {
        // we have to sort (array_udiff requires it), so sort by Id
        if ($a->getId() == $b->getId()) {
            return 0;
        } else if ($a->getId() < $b->getId()) {
            return -1;
        }

        // non-zero, although we don't know if it's bigger or smaller
        return 1;
    }

}
