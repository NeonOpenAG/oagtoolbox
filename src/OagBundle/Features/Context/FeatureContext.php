<?php

namespace OagBundle\Features\Context;

use Behat\MinkExtension\Context\MinkContext;
use Behat\Symfony2Extension\Context\KernelAwareContext;
use Symfony\Component\HttpKernel\KernelInterface;

class FeatureContext extends MinkContext implements KernelAwareContext
{
    private $kernel;

    public function setKernel(KernelInterface $kernel)
    {
        $this->kernel = $kernel;
    }

    protected function getContainer()
    {
        return $this->kernel->getContainer();
    }

    /**
     * @Given /^I wait for (\d+) seconds$/
     */
    public function iWaitForSeconds($seconds)
    {
        $this->getSession()->wait($seconds * 1000);
    }
}
