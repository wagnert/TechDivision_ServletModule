<?php

/**
 * TechDivision\ServletModule\ServletModuleTest
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Open Software License (OSL 3.0)
 * that is available through the world-wide-web at this URL:
 * http://opensource.org/licenses/osl-3.0.php
 *
 * PHP version 5
 *
 * @category  Appserver
 * @package   TechDivision_ServletModule
 * @author    Tim Wagner <tw@techdivision.com>
 * @copyright 2014 TechDivision GmbH <info@techdivision.com>
 * @license   http://opensource.org/licenses/osl-3.0.php Open Software License (OSL 3.0)
 * @link      http://www.appserver.io
 */

namespace TechDivision\ServletModule;

/**
 * Test for the default session settings implementation.
 *
 * @category  Appserver
 * @package   TechDivision_ServletModule
 * @author    Tim Wagner <tw@techdivision.com>
 * @copyright 2014 TechDivision GmbH <info@techdivision.com>
 * @license   http://opensource.org/licenses/osl-3.0.php Open Software License (OSL 3.0)
 * @link      http://www.appserver.io
 */
class ServletModuleTest extends \PHPUnit_Framework_TestCase
{

    /**
     * The servlet module instance to test.
     * 
     * @var \TechDivision\ServletModule\ServletModule
     */
    protected $servletModule;
    
    /**
     * Initializes the servlet module to test.
     *
     * @return void
     */
    public function setUp()
    {
        $this->servletModule = new ServletModule();
    }
    
    /**
     * Test if the constructor creates an instance of the servlet module.
     *
     * @return void
     */
    public function testInstanceOf()
    {
        $this->assertInstanceOf('\TechDivision\ServletModule\ServletModule', $this->servletModule);
    }
}
