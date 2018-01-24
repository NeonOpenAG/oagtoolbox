<?php

namespace OagBundle\Features\Context;

use Behat\Gherkin\Node\TableNode;
use Behat\MinkExtension\Context\MinkContext;
use Behat\Symfony2Extension\Context\KernelAwareContext;
use Symfony\Component\HttpKernel\KernelInterface;
use \Exception;

class FeatureContext extends MinkContext implements KernelAwareContext {

    private $kernel;

    public function setKernel(KernelInterface $kernel) {
        $this->kernel = $kernel;
    }

    protected function getContainer() {
        return $this->kernel->getContainer();
    }

    /**
     * @Given /^I wait for (\d+) seconds$/
     */
    public function iWaitForSeconds($seconds) {
        $this->getSession()->wait($seconds * 1000);
    }

    /**
     * @Given I am in the group docker
     */
    public function iAmInTheGroupDocker() {
        $user = get_current_user();
        $cmd = sprintf('id -Gn "tobias"|grep -c "docker"', $user);
        $ingroup = exec($cmd);
        if ($ingroup == "0") {
            throw new Exception(sprintf("Command %s returned %s\n", $cmd, $ingroup));
        }
    }

    /**
     * @When I run :command
     */
    public function iRun($command) {
        exec($command, $output);
        $this->output = trim(array_pop($output));
    }

    /**
     * @When check if the docker is running with the following:
     */
    public function checkIfTheDockerIsRunningWithTheFollowing(TableNode $table) {
        $rows = $table->getRows();
        foreach ($rows as $row) {
            $cmd = "docker inspect -f '{{.State.Running}}' " . $row[0];
            $this->iRun($cmd);
        }
    }

    /**
     * @Then I should get :arg1
     */
    public function iShouldGet($arg1) {
        if ((string) $arg1 !== $this->output) {
            throw new Exception(
            "Actual output is:\n" . $this->output
            );
        }
    }

    /**
     * @When I pass the cove docker the following:
     */
    public function iPassTheCoveDockerTheFollowing(TableNode $table)
    {
        $rows = $table->getRows();
        foreach ($rows as $row) {
            $cmd = sprintf("cat %s | docker exec -i --env FILENAME='%s' openag_cove /usr/local/bin/process.sh 2>/dev/null", $row[0], basename($row[0]));
            $this->iRun($cmd);
        }
    }

    /**
     * Click on the element with the provided CSS Selector
     *
     * @When /^I click on the element with css selector "([^"]*)"$/
     */
    public function iClickOnTheElementWithCSSSelector($cssSelector)
    {
        $session = $this->getSession();
        $element = $session->getPage()->find('css', $cssSelector);
 if (null === $element) {
            throw new \InvalidArgumentException(sprintf('Could not evaluate CSS Selector: "%s"', $cssSelector));
        }
 
        $element->click();
 
    }
}

# vim: set expandtab tabstop=4 shiftwidth=4 autoindent smartindent:
