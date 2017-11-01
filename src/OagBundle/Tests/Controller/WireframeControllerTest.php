<?php

namespace OagBundle\Tests\Controller;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\RedirectResponse;

class WireframeControllerTest extends WebTestCase
 {

    /**
     * Uploads a sample XML and the previews it it in D-Portal
     */
    public function testUploadAndPreviewXml() {
        $client = static::createClient();
        $url = $client->getContainer()->get('router')->generate('oag_wireframe_upload');
        $crawler = $client->request('GET', $url);

        // Select a file
        $xmlfile = new UploadedFile(
                # Path to the file to send
                dirname(__FILE__) . '/../../Resources/fixtures/after_enrichment_activities.xml',
                # Name of the sent file
                'after_enrichment_activities.xml',
                # MIME type
                'text/html',
                # Size of the file
                221258
        );
        // Upload it
        $form = $crawler->filter('#oag_file_Upload')->form();
        $form['oag_file[documentName]']->upload($xmlfile);
        $crawler = $client->submit($form);

        $crawler = $client->followRedirect();

        $client->getContainer()->get('logger')->info($client->getRequest()->getUri());
        $this->assertTrue(strpos($client->getRequest()->getUri(), 'improveYourData') > 0);

        $link = $crawler
                ->filter('.nav-options a') // find all links with the text "Greet"
                ->eq(0) // select the second link in the list
                ->link()
        ;

// and click it
        $crawler = $client->click($link);
    }

}
