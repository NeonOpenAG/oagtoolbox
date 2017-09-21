<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

namespace OagBundle\Service;

/**
 * Description of Docker
 *
 * @author tobias
 */
class Docker extends AbstractOagService {

    protected $header;
    protected $body;

    public function listContainers() {
        $containerData = $this->containerData();

        $data = [];
        foreach ($containerData as $container) {
            $name = ltrim($container['Names'][0], '/');
            $data[$name] = [
                'container_id' => substr($container['Id'], 0, 12),
                'image' => $container['Image'] ?? substr($container['ImageID'], 0, 12),
                'command' => $container['Command'],
                'created' => $this->timeAgo($container['Created']),
                'status' => $container['State'],
                'ports' => $this->portsToString($container['Ports']),
                'names' => implode(', ', $container['Names']),
            ];
            dump($container);
        }
        dump($data);

        return $data;
    }

    private function portsToString($data) {
        $ports = [];

        foreach ($data as $mapping) {
            $ports[] = sprintf('%s:%d->%d/%s', $mapping['IP'], $mapping['PublicPort'], $mapping['PrivatePort'], $mapping['Type']);
        }

        return implode(', ', $ports);
    }

    public function containerData() {
        $data = $this->api("http:/v1.30/containers/json?all=1");
        return $data;
    }

    private function api($path) {
        $errno = 0;
        $errstr = '';
        $sock = stream_socket_client("unix:///var/run/docker.sock", $errno, $errstr);
        $msg = "GET " . $path . " HTTP/1.0\r\n";
        $msg .= "Content-Type: application/json\r\n";
        $msg .= "HOST: 0.0.0.0\r\n";
        $msg .= $path;
        $msg .= "\r\n\r\n";
        $this->getContainer()->get('logger')->debug($msg);
        fwrite($sock, $msg);
        $data = fread($sock, 4096);
        fclose($sock);

        if ($errno) {
            $this->getContainer()->get('logger')->error('Docker API: ' . $errstr . ' [' . $errno . ']');
        }
        $this->getContainer()->get('logger')->debug($data);

        list($this->header, $this->body) = explode("\r\n\r\n", $data, 2);
        return json_decode($this->body, true);
    }

    public function getName() {
        return 'docker';
    }

    private function timeAgo($time_ago) {
        $cur_time = time();
        $time_elapsed = $cur_time - $time_ago;
        $seconds = $time_elapsed;
        $minutes = round($time_elapsed / 60);
        $hours = round($time_elapsed / 3600);
        $days = round($time_elapsed / 86400);
        $weeks = round($time_elapsed / 604800);
        $months = round($time_elapsed / 2600640);
        $years = round($time_elapsed / 31207680);
        // Seconds
        if ($seconds <= 60) {
            return "just now";
        }
        //Minutes
        else if ($minutes <= 60) {
            if ($minutes == 1) {
                return "one minute ago";
            } else {
                return "$minutes minutes ago";
            }
        }
        //Hours
        else if ($hours <= 24) {
            if ($hours == 1) {
                return "an hour ago";
            } else {
                return "$hours hrs ago";
            }
        }
        //Days
        else if ($days <= 7) {
            if ($days == 1) {
                return "yesterday";
            } else {
                return "$days days ago";
            }
        }
        //Weeks
        else if ($weeks <= 4.3) {
            if ($weeks == 1) {
                return "a week ago";
            } else {
                return "$weeks weeks ago";
            }
        }
        //Months
        else if ($months <= 12) {
            if ($months == 1) {
                return "a month ago";
            } else {
                return "$months months ago";
            }
        }
        //Years
        else {
            if ($years == 1) {
                return "one year ago";
            } else {
                return "$years years ago";
            }
        }
    }

}
