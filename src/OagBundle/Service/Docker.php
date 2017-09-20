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
    
    public function listContainers() {
        return $this->api("http:/v1.30/containers/json?all=1");
    }

    private function api($path) {
        $errno = 0;
        $errstr = '';
        $sock = stream_socket_client("unix:///var/run/docker.sock", $errno, $errstr);
        $msg = "GET " . $path . " HTTP/1.1\r\n";
        $msg .= "Content-Type: application/json\r\n";
        $msg .= "HOST: 0.0.0.0\r\n";
        $msg .= $path;
        $msg .= "\r\n\r\n";
        $this->getContainer()->get('logger')->debug($msg);
        fwrite($sock, $msg);
        $data = fread($sock, 4096);
        fclose($sock);
        
        if ($errno) {
            $this->getContainer()->get('logger')->error('Docker API: ' . $errstr. ' [' . $errno . ']');
        }
        $this->getContainer()->get('logger')->debug($data);
        
        return $data;
    }

    public function getName() {
        return 'docker';
    }

}
