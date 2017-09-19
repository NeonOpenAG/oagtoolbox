<?php

namespace OagBundle\Service;

class CSV extends AbstractService {

    /**
     * Converts a headered CSV file to a list of associative arrays with key-
     * value properties based on the headings.
     *
     * Assums that there is at least one line of data and that the number of
     * columns is constant throughout the set.
     *
     * TODO more error checking
     *
     * @param string $rawText
     * @param string $delimiter can be changed to a tab to work with TSV files
     * @return array[]
     */
    public function toArray($rawText, $delimiter=',') {
        $rows = str_getcsv($rawText, "\n");
        $headers = str_getcsv($rows[0], $delimiter);
        $arrays = array();
        foreach ($rows as $rowIndex => $row) {
            if ($rowIndex === 0) continue;
            $row = str_getcsv($row, $delimiter);
            $object = array();
            foreach ($headers as $index => $header) {
                $object[$header] = $row[$index];
            }
            $arrays[] = $object;
        }
        return $arrays;
    }

}
