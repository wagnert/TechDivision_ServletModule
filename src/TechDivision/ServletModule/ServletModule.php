<?php

/**
 * TechDivision\ServletModule\ServletModule
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

use TechDivision\Http\HttpRequestInterface;
use TechDivision\Http\HttpResponseInterface;
use TechDivision\WebServer\Interfaces\ModuleInterface;
use TechDivision\WebServer\Interfaces\ServerContextInterface;
use TechDivision\WebServer\Modules\ModuleException;
use TechDivision\ServletEngine\Engine;

/**
 * This is a servlet module that handles a servlet request.
 *
 * @category  Appserver
 * @package   TechDivision_ServletModule
 * @author    Tim Wagner <tw@techdivision.com>
 * @copyright 2014 TechDivision GmbH <info@techdivision.com>
 * @license   http://opensource.org/licenses/osl-3.0.php Open Software License (OSL 3.0)
 * @link      http://www.appserver.io
 */
class ServletModule implements ModuleInterface
{
    
    /**
     * The unique module name in the web server context.
     * 
     * @var string
     */
    const MODULE_NAME = 'servlet';

    /**
     * Returns an array of module names which should be executed first.
     *
     * @return array The array of module names
     */
    public function getDependencies()
    {
        return array();
    }

    /**
     * Returns the module name.
     *
     * @return string The module name
     */
    public function getModuleName()
    {
        return ServletModule::MODULE_NAME;
    }

    /**
     * Initializes the module.
     *
     * @param \TechDivision\WebServer\Interfaces\ServerContextInterface $serverContext The servers context instance
     * 
     * @return void
     * @throws \TechDivision\WebServer\Exceptions\ModuleException
     */
    public function init(ServerContextInterface $serverContext)
    {
        try {
            $this->engine = new Engine();
            $this->engine->init($serverContext);
        } catch (\Exception $e) {
            throw new ModuleException($e);
        }
    }

    /**
     * Process the servlet engine.
     *
     * @param \TechDivision\Http\HttpRequestInterface  $request  The request instance
     * @param \TechDivision\Http\HttpResponseInterface $response The response instance
     * 
     * @return void
     * @throws \TechDivision\WebServer\Exceptions\ModuleException
     */
    public function process(HttpRequestInterface $request, HttpResponseInterface $response)
    {
        try {
            $this->engine->process($request, $response);
        } catch (\Exception $e) {
            throw new ModuleException($e);
        }
    }
}
