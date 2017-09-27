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
    
    public function createCove() {
        // $payload = '{ "Image": "openagdata/cove", "ExposedPorts": { "8000/tcp": {} }, "HostConfig": { "PortBindings": { "8000/tcp": [ { "HostPort": "8000" } ] }, "RestartPolicy": { "Name": "always" } } }';
        $payload = '{ "Image": "openagdata/cove", "ExposedPorts": { "8000/tcp": {} }, "RestartPolicy": { "Name": "always" } } }';
        
        $data = $this->apiPost("http:/v1.30/containers/create?name=openag_cove", $payload);
        return $data;
    }
    
  public function createDportal() {
 // $payload = '{ "Image": "openagdata/dportal", "Tty": true, "ExposedPorts": { "8011/tcp": {}, "1408/tcp": {} }, "HostConfig": { "PortBindings": { "8011/tcp": [ { "HostPort": "8011" } ], "1408/tcp": [ { "HostPort": "1408" } ] }, "RestartPolicy": { "Name": "always" } } }';
 $payload = '{ "Image": "openagdata/dportal", "Tty": true, "ExposedPorts": { "8011/tcp": {}, "1408/tcp": {} }, "HostConfig": { "PortBindings": { "8011/tcp": [ { "HostPort": "8011" } ], "1408/tcp": [ { "HostPort": "1408" } ] }, "RestartPolicy": { "Name": "always" } } }';
        
        $data = $this->apiPost("http:/v1.30/containers/create?name=openag_dportal", $payload);
        return $data;
    }
    
    public function createNerserver() {
        // $payload = '{ "Image": "openagdata/nerserver", "ExposedPorts": { "9000/tcp": {} }, "HostConfig": { "PortBindings": { "9000/tcp": [ { "HostPort": "9000" } ] }, "RestartPolicy": { "Name": "always" } } }';
        $payload = '{ "Image": "openagdata/nerserver", "ExposedPorts": { "9000/tcp": {} }, "RestartPolicy": { "Name": "always" } } }';
        
        $data = $this->apiPost("http:/v1.30/containers/create?name=openag_nerserver", $payload);
        return $data;
    }
    
    public function createGeocode() {
        $payload = '{ "Image": "openagdata/geocoder", "Tty": true, "ExposedPorts": { "8010/tcp": {} }, "RestartPolicy": { "Name": "always" }, "Links": ["/openag_nerserver:/openag_geocoder/openag_nerserver"] }';
        
        $data = $this->apiPost("http:/v1.30/containers/create?name=openag_geocoder", $payload);
        return $data;
    }
    
    /**
     * Start a container.
     * 
     * @param string $name The name of the image
     * @return type
     */
    public function startContainer($name) {
        $uri = "http:/v1.30/containers/$name/start";
        $data = $this->apiPost($uri);
        return $data;
    }

    /**
     * Access the given path on the docker socket.
     * 
     * @param string $path
     * @param string $payload
     * 
     * @return array
     */
    private function apiPost($path, $payload = false) {
        $errno = 0;
        $errstr = '';
        $sock = stream_socket_client("unix:///var/run/docker.sock", $errno, $errstr);
        $msg = "POST " . $path . " HTTP/1.0\r\n";
        if ($payload) {
            $msg .= "Content-Type: application/json\r\n";
            $msg .= "Content-length: " . strlen($payload) . "\r\n";
        }
        $msg .= "HOST: 0.0.0.0\r\n";
        $msg .= $path;
        $msg .= "\r\n\r\n";
        
        $this->getContainer()->get('logger')->info(sprintf('Posting to %s with %s', $path, $payload));
        
        fwrite($sock, $msg);
        if ($payload) {
            fwrite($sock, $payload);
        }
        
        $data = fread($sock, 4096);
        fclose($sock);

        if ($errno) {
            $this->getContainer()->get('logger')->error('Docker API: ' . $errstr . ' [' . $errno . ']');
        }
        // $this->getContainer()->get('logger')->debug($data);

        list($this->header, $this->body) = explode("\r\n\r\n", $data, 2);
        
        $this->getContainer()->get('logger')->debug($this->body);
        return json_decode($this->body, true);
    }
    
    /**
     * List all containers.
     * 
     * @return array
     */
    public function listContainers() {
        $containerData = $this->containerData();

        $data = [];
//        if (!is_array($containerData)) {
//            $this->getContainer()->get('logger')->warning('No conatiner data available.');
//            return [];
//        }
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
        }

        return $data;
    }

    /**
     * Turn the array port info into a string.
     * 
     * @param array $data IP/Public port/Private port/Protocol
     * 
     * @return string
     */
    private function portsToString($data) {
        $ports = [];

        foreach ($data as $mapping) {
            $ports[] = sprintf('%s:%d->%d/%s', $mapping['IP'] ?? '', $mapping['PublicPort'] ?? '', $mapping['PrivatePort'] ?? '', $mapping['Type'] ?? '');
        }

        return implode(', ', $ports);
    }

    /**
     * Fetch raw container data.
     * 
     * @return array
     */
    public function containerData() {
        $data = $this->apiGet("http:/v1.30/containers/json?all=1");
        return $data;
    }

    /**
     * Access the given path on the docker socket.
     * 
     * @param string $path
     * 
     * @return array
     */
    private function apiGet($path) {
        $errno = 0;
        $errstr = '';
        $sock = stream_socket_client("unix:///var/run/docker.sock", $errno, $errstr);
        $msg = "GET " . $path . " HTTP/1.0\r\n";
        $msg .= "Content-Type: application/json\r\n";
        $msg .= "HOST: 0.0.0.0\r\n";
        $msg .= $path;
        $msg .= "\r\n\r\n";
        fwrite($sock, $msg);
        $data = fread($sock, 4096);
        fclose($sock);

        if ($errno) {
            $this->getContainer()->get('logger')->error('Docker API: ' . $errstr . ' [' . $errno . ']');
        }

        list($this->header, $this->body) = explode("\r\n\r\n", $data, 2);
        
        // $this->getContainer()->get('logger')->debug($this->body);
        return json_decode($this->body, true);
    }

    /**
     * Get service name
     * 
     * @return string
     */
    public function getName() {
        return 'docker';
    }

    /**
     * Turns a time stamp into xx ago
     * 
     * @param long $time_ago
     * 
     * @return string
     */
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

    /**
     * Create container.
     * 
     * $ curl --unix-socket /var/run/docker.sock -H "Content-Type: application/json" \
     *  -d '{"Image": "bfirsh/reticulate-splines"}' \
     *  -X POST http:/v1.24/containers/create
     *  {"Id":"1c6594faf5","Warnings":null}
     *  
     *  $ curl --unix-socket /var/run/docker.sock -X POST http:/v1.24/containers/1c6594faf5/start
     * 
     * @return array
     */
//    public function createContainer($name, $hostname, $ports = null) {
//        $exposedPorts = [];
//        foreach ($ports as $port) {
//            // $payload["ExposedPorts"][] = [$port . "/tcp" => []];
//            $exposedPorts[] = sprintf(' "%d/tcp": {} ', $port);
//        }
//        $payload = sprintf('{"Image":"%s","ExposedPorts":{%s}}', $name, implode(',', $exposedPorts));
//        $this->getContainer()->get('logger')->info($payload);
//        $data = $this->apiPost("http:/v1.30/containers/create?name=" . $hostname, $payload);
//        return $data;
//    }
