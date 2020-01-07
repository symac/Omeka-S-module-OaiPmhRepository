<?php

namespace OaiPmhRepository\Service\OaiPmh\Metadata;

use Interop\Container\ContainerInterface;
use OaiPmhRepository\OaiPmh\Metadata\OaiDc;
use OaiPmhRepository\OaiPmh\Metadata\OaiDcBnf;
use Zend\ServiceManager\Factory\FactoryInterface;

class OaiDcFactory implements FactoryInterface
{
    /**
     * Prepare the OaiDc format.
     *
     * @return OaiDc
     */
    public function __invoke(ContainerInterface $services, $requestedName, array $options = null)
    {
        $settings = $services->get('Omeka\Settings');
        $oaiSetManager = $services->get('OaiPmhRepository\OaiPmh\OaiSetManager');
        $oaiSet = $oaiSetManager->get($settings->get('oaipmhrepository_oai_set_format', 'base'));
        $frenchNationalLibrary = $settings->get('oaipmhrepository_french_national_library_dc', false);
        if ( $frenchNationalLibrary ) {
            $metadataFormat = new OaiDcBnf();
        } else {
            $metadataFormat = new OaiDc();
        }

        $metadataFormat->setEventManager($services->get('EventManager'));
        $metadataFormat->setSettings($settings);
        $metadataFormat->setOaiSet($oaiSet);
        $isGlobalRepository = !$services->get('ControllerPluginManager')
            ->get('params')->fromRoute('__SITE__', false);
        $metadataFormat->setIsGlobalRepository($isGlobalRepository);
        return $metadataFormat;
    }
}
