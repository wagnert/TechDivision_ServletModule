<?php

/**
 * TechDivision\ServletModule\ServletProcess
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
 * Is thrown no servlet can be found for a request.
 *
 * @category  Appserver
 * @package   TechDivision_ServletModule
 * @author    Tim Wagner <tw@techdivision.com>
 * @copyright 2014 TechDivision GmbH <info@techdivision.com>
 * @license   http://opensource.org/licenses/osl-3.0.php Open Software License (OSL 3.0)
 * @link      http://www.appserver.io
 */
class ServletProcess extends \Thread
{

    protected $engine;
    protected $request;
    protected $response;

    protected $outputStream;

    public function __construct($engine, $request, $response)
    {
        $this->engine = $engine;
        $this->request = $request;
        $this->response = $response;
    }

    public function shutdown()
    {
        $response = $this->response;
        
        $response->appendBodyStream(ob_get_clean());
        
        $this->response = $response;
    }
    
    public function getResponse()
    {
        return $this->response;
    }
    
    public function getRequest()
    {
        return $this->request;
    }

    public function run()
    {

        register_shutdown_function(array(&$this, 'shutdown'));

        ob_start();
        
        $engine = $this->engine;
        $request = $this->request;
        $response = $this->response;

        $engine->registerClassLoader();
        $engine->process($request, $response);

        $response->appendBodyStream(ob_get_clean());
        
        $this->request = $request;
        $this->response = $response;
    }
}