<?php

namespace Basilicom\XmlToolBundle\Controller;

use Basilicom\XmlToolBundle\Api\Exception;
use Basilicom\XmlToolBundle\Service\Xml;
use Basilicom\XmlToolBundle\Service\XmlConfig;
use Pimcore\Analytics\Piwik\Api\Exception\ApiException;
use Pimcore\Controller\FrontendController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class ApiController extends FrontendController
{

    /** @var Xml */
    private $xmlService;

    private $config;

    public function __construct(Xml $xmlService, $config)
    {
        $this->xmlService = $xmlService;
        $this->config = $config;
    }

    /**
     * @Route("/xml-tool/export/{name}", defaults={"_format"="xml"}, name="_demo_hello")
     */
    public function exportAction(Request $request)
    {

        try {

            $isEnabled = ($this->config['api']['enabled'] == 'true');
            if (!$isEnabled) {
                throw new Exception('API disabled', Exception::NOT_IMPLEMENTED);
            }

            $name = $request->get('name');

            $config = $this->config['api']['endpoints'][$name];

            if (!is_array($config)) {
                throw new Exception('Export configuration not found', Exception::NOT_FOUND);
            }

            $token = $request->get('token');
            if (($config['token'] !== '') && $config['token'] !== $token) {
                throw new Exception('Unauthorized access', Exception::UNAUTHORIZED);
            }

            $this->xmlService->setIncludeVariants(($config['include_variants'] == true));
            $this->xmlService->setOmitRelationObjectFields(($config['omit_relation_object_fields'] == true));
            $this->xmlService->setIncludeUnpublished($config['include_unpublished'] == true);

            $this->xmlService->setXslt($config['xslt']);

            $object = \Pimcore\Model\DataObject::getByPath($config['path']);

            if (!is_object($object)) {
                throw new Exception(
                    'Export object path not found',
                    Exception::NOT_FOUND);
            }

            $result = $this->xmlService->exportTree($object, $config['root']);

            return new Response($result->saveXML());

        } catch (Exception $exception) {
            return new Response($exception->toXml()->asXML());
        } catch (\Exception $exception) {
            $errorException = new Exception(
                'API Failure:' . $exception->getMessage(), Exception::INTERNAL_ERROR);
            return new Response($errorException->toXml()->asXML());
        }
    }
}
