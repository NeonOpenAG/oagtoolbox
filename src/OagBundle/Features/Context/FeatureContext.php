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
        echo $ingroup . "\n";
        if ($ingroup == "0") {
            throw new Exception(sprintf("Command %s returned %s\n", $cmd, $ingroup));
        }
    }

    /**
     * @When I run :command
     */
    public function iRun($command) {
        echo "\n\n$command\n\n";
        exec($command, $output);
        $this->output = trim(implode("\n", $output));
    }

    /**
     * @When I run :arg1 with the following:
     */
    public function iRunWithTheFollowing($arg1, TableNode $table) {
        $rows = $table->getRows();
        foreach ($rows as $row) {
            $this->iRun(str_replace("<DOCKER>", $row[0], $arg1));
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

}

# vim: set expandtab tabstop=4 shiftwidth=4 autoindent smartindent:
